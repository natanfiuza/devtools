<?php

use App\Actions\Ai\FailAiQuery;
use App\Actions\Ai\SettleAiQuery;
use App\Actions\Ai\StartAiQuery;
use App\Enums\AiFailureCode;
use App\Enums\AiRequestStatus;
use App\Enums\LedgerEntryType;
use App\Enums\QuotaUnit;
use App\Enums\SchemaSource;
use App\Enums\SqlDialect;
use App\Exceptions\Ai\AiInputException;
use App\Exceptions\Ai\QuotaExceededException;
use App\Jobs\ProcessAiQuery;
use App\Models\AiCache;
use App\Models\AiRequest;
use App\Models\OutputTarget;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\UsageLedger;
use App\Models\User;
use App\Support\Ai\AiCacheRepository;
use App\Support\Ai\AiResult;
use App\Support\Ai\Canonicalizer;
use App\Support\Ai\CanonicalPayload;
use App\Support\Ai\StartResult;
use App\Support\Quota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();

    config([
        'ai.base_url' => 'https://api.deepseek.test',
        'ai.model' => 'test-model',
        'ai.api_key' => 'test-key',
        'ai.estimate_output_tokens' => 900,
        'ai.inflight_ttl_seconds' => 600,
    ]);
});

function aiActionTarget(): OutputTarget
{
    return OutputTarget::query()->where('slug', 'sql')->sole();
}

function aiActionPayload(
    string $schema = 'CREATE TABLE users (id int);',
    string $description = 'Liste os usuarios',
): CanonicalPayload {
    return (new Canonicalizer)->canonicalize(
        SchemaSource::Ddl,
        SqlDialect::Mysql,
        'sql',
        $schema,
        $description,
        'test-model',
        1,
    );
}

function aiActionResult(): AiResult
{
    return new AiResult(
        'SELECT id FROM users',
        SqlDialect::Mysql,
        [],
        [],
        ['users'],
        false,
        null,
        ['sql' => 'SELECT id FROM users'],
    );
}

function aiStart(
    User $user,
    ?OutputTarget $target = null,
    string $schema = 'CREATE TABLE users (id int);',
    string $description = 'Liste os usuarios',
    SqlDialect $dialect = SqlDialect::Mysql,
): StartResult {
    return app(StartAiQuery::class)(
        $user,
        $target ?? aiActionTarget(),
        SchemaSource::Ddl,
        $dialect,
        $schema,
        $description,
    );
}

/**
 * @return array{user: User, plan: Plan}
 */
function aiPaidUser(int $monthlyTokens = 100000): array
{
    $plan = Plan::factory()->create([
        'monthly_tokens' => $monthlyTokens,
        'max_schema_bytes' => 65536,
        'max_prompt_chars' => 4000,
    ]);

    $user = User::factory()->create();

    Subscription::factory()->for($user)->for($plan)->create([
        'current_period_start' => now()->subDay(),
        'current_period_end' => now()->addMonth(),
    ]);

    return ['user' => $user, 'plan' => $plan];
}

it('debita um uso do Free ao criar a solicitacao', function () {
    $user = User::factory()->create();

    $start = aiStart($user);

    expect($start->request->status)->toBe(AiRequestStatus::Queued)
        ->and($start->request->quota_unit)->toBe(QuotaUnit::Uses)
        ->and($start->request->reserved_uses)->toBe(1)
        ->and($start->request->reserved_tokens)->toBe(0)
        ->and($start->request->canonical_payload)->not->toBeNull()
        ->and($start->httpStatus())->toBe(202)
        ->and($start->cacheHit)->toBeFalse();

    $debit = UsageLedger::query()->sole();

    expect($debit->type)->toBe(LedgerEntryType::Debit)
        ->and($debit->uses)->toBe(1)
        ->and($debit->tokens)->toBe(0)
        ->and($debit->ai_request_ulid)->toBe($start->request->id)
        ->and($debit->created_at?->equalTo($start->request->created_at))->toBeTrue()
        ->and(Quota::for($user)->used())->toBe(1);
});

it('reserva a estimativa em tokens do plano pago', function () {
    ['user' => $user] = aiPaidUser();

    $start = aiStart($user);

    $reserved = $start->request->reserved_tokens;

    expect($start->request->quota_unit)->toBe(QuotaUnit::Tokens)
        ->and($start->request->reserved_uses)->toBe(0)
        ->and($reserved)->toBe($start->request->estimated_tokens)
        ->and($reserved)->toBeGreaterThan(0)
        ->and(Quota::for($user)->unit())->toBe('tokens')
        ->and(Quota::for($user)->used())->toBe($reserved);
});

it('devolve o cache sem debitar e conta o hit', function () {
    $user = User::factory()->create();
    $payload = aiActionPayload();
    $result = aiActionResult();
    $cache = app(AiCacheRepository::class)->store($payload, $result, 40, 10);

    $start = aiStart($user);

    expect($start->cacheHit)->toBeTrue()
        ->and($start->httpStatus())->toBe(200)
        ->and($start->request->status)->toBe(AiRequestStatus::Done)
        ->and($start->request->cache_hit)->toBeTrue()
        ->and($start->request->tokens_in)->toBe(40)
        ->and($start->request->tokens_out)->toBe(10)
        ->and($start->request->result()['sql'] ?? null)->toBe('SELECT id FROM users');

    $this->assertDatabaseCount('usage_ledger', 0);

    expect(Quota::for($user)->used())->toBe(0)
        ->and($cache->refresh()->hits)->toBe(1);

    Queue::assertNothingPushed();
});

it('deduplica uma solicitacao em voo sem debitar de novo', function () {
    $user = User::factory()->create();

    $first = aiStart($user);
    $second = aiStart($user);

    expect($second->deduplicated)->toBeTrue()
        ->and($second->request->id)->toBe($first->request->id)
        ->and($second->httpStatus())->toBe(202);

    $this->assertDatabaseCount('ai_requests', 1);
    $this->assertDatabaseCount('usage_ledger', 1);

    expect(Quota::for($user)->used())->toBe(1);
});

it('enfileira somente a solicitacao nova', function () {
    $user = User::factory()->create();

    $start = aiStart($user);

    Queue::assertPushed(
        ProcessAiQuery::class,
        fn (ProcessAiQuery $job): bool => $job->aiRequestId === $start->request->id,
    );

    aiStart($user);

    Queue::assertPushed(ProcessAiQuery::class, 1);
});

it('recusa schema acima do limite do plano', function () {
    $user = User::factory()->create();
    $exception = null;

    try {
        aiStart($user, schema: str_repeat('a', 9000));
    } catch (AiInputException $caught) {
        $exception = $caught;
    }

    expect($exception)->not->toBeNull()
        ->and($exception?->field)->toBe('schema')
        ->and($exception?->reason)->toBe('schema_too_large');

    $this->assertDatabaseCount('ai_requests', 0);
    $this->assertDatabaseCount('usage_ledger', 0);
});

it('recusa descricao acima do limite do plano', function () {
    $user = User::factory()->create();
    $exception = null;

    try {
        aiStart($user, description: str_repeat('a', 501));
    } catch (AiInputException $caught) {
        $exception = $caught;
    }

    expect($exception)->not->toBeNull()
        ->and($exception?->field)->toBe('description')
        ->and($exception?->reason)->toBe('description_too_long');
});

it('recusa dialeto que o alvo nao suporta', function () {
    $user = User::factory()->create();
    $target = aiActionTarget();
    $target->update(['supported_dialects' => [SqlDialect::Mysql->value]]);
    $exception = null;

    try {
        aiStart($user, $target, dialect: SqlDialect::Pgsql);
    } catch (AiInputException $caught) {
        $exception = $caught;
    }

    expect($exception)->not->toBeNull()
        ->and($exception?->field)->toBe('dialect')
        ->and($exception?->reason)->toBe('unsupported_dialect');
});

it('recusa quando o Free esgotou os usos', function () {
    $user = User::factory()->create();
    UsageLedger::factory()->count(5)->for($user)->create();

    expect(fn () => aiStart($user))->toThrow(QuotaExceededException::class);
});

it('recusa quando o plano pago nao tem tokens suficientes', function () {
    ['user' => $user] = aiPaidUser(monthlyTokens: 10);

    expect(fn () => aiStart($user))->toThrow(QuotaExceededException::class);
});

it('liquida o Free sem ajuste de tokens e grava o cache', function () {
    $user = User::factory()->create();
    $start = aiStart($user);

    $settled = app(SettleAiQuery::class)($start->request, aiActionResult(), 100, 20);

    expect($settled->status)->toBe(AiRequestStatus::Done)
        ->and($settled->tokens_in)->toBe(100)
        ->and($settled->tokens_out)->toBe(20)
        ->and($settled->canonical_payload)->toBeNull()
        ->and($settled->cache_hit)->toBeFalse()
        ->and($settled->result()['sql'] ?? null)->toBe('SELECT id FROM users')
        ->and(Quota::for($user)->used())->toBe(1);

    $this->assertDatabaseCount('usage_ledger', 1);
    $this->assertDatabaseHas('ai_cache', [
        'prompt_hash' => $start->request->prompt_hash,
        'tokens_in' => 100,
        'tokens_out' => 20,
    ]);
});

it('estorna a diferenca quando o plano pago consome menos que a reserva', function () {
    ['user' => $user] = aiPaidUser();
    $start = aiStart($user);
    $reserved = $start->request->reserved_tokens;

    app(SettleAiQuery::class)($start->request, aiActionResult(), 10, 5);

    $entries = UsageLedger::query()->orderBy('id')->get();

    expect($entries)->toHaveCount(2)
        ->and($entries[1]->type)->toBe(LedgerEntryType::Refund)
        ->and($entries[1]->tokens)->toBe($reserved - 15)
        ->and($entries[0]->created_at?->equalTo($entries[1]->created_at))->toBeTrue()
        ->and(Quota::for($user)->used())->toBe(15);
});

it('cobra a diferenca quando o plano pago consome mais que a reserva', function () {
    ['user' => $user] = aiPaidUser();
    $start = aiStart($user);
    $reserved = $start->request->reserved_tokens;

    $settled = app(SettleAiQuery::class)($start->request, aiActionResult(), $reserved + 50, 0);

    $entries = UsageLedger::query()->orderBy('id')->get();

    expect($settled->tokens_in)->toBe($reserved + 50)
        ->and($entries)->toHaveCount(2)
        ->and($entries[1]->type)->toBe(LedgerEntryType::Debit)
        ->and($entries[1]->tokens)->toBe(50)
        ->and(Quota::for($user)->used())->toBe($reserved + 50);
});

it('nao ajusta uma liquidacao repetida', function () {
    ['user' => $user] = aiPaidUser();
    $start = aiStart($user);

    $settle = app(SettleAiQuery::class);
    $first = $settle($start->request, aiActionResult(), 10, 5);
    $second = $settle($start->request, aiActionResult(), 9999, 9999);

    expect($second->tokens_in)->toBe($first->tokens_in)
        ->and($second->tokens_out)->toBe($first->tokens_out)
        ->and(UsageLedger::query()->count())->toBe(2);
});

it('estorna a reserva inteira quando o job encontra cache', function () {
    $user = User::factory()->create();
    $start = aiStart($user);
    $payload = aiActionPayload();
    $repository = app(AiCacheRepository::class);

    $repository->store($payload, aiActionResult(), 30, 10);
    $hit = $repository->find($payload, SqlDialect::Mysql, 'sql');

    expect($hit)->not->toBeNull();

    if ($hit === null) {
        return;
    }

    $settled = app(SettleAiQuery::class)->fromCache($start->request, $hit);

    expect($settled->status)->toBe(AiRequestStatus::Done)
        ->and($settled->cache_hit)->toBeTrue()
        ->and($settled->tokens_in)->toBe(30)
        ->and($settled->tokens_out)->toBe(10)
        ->and($settled->canonical_payload)->toBeNull()
        ->and(Quota::for($user)->used())->toBe(0);

    $this->assertDatabaseCount('usage_ledger', 2);
    $this->assertDatabaseHas('ai_cache', [
        'prompt_hash' => $payload->hash(),
        'hits' => 1,
    ]);
});

it('estorna a reserva e marca a falha', function () {
    $user = User::factory()->create();
    $start = aiStart($user);

    $failed = app(FailAiQuery::class)($start->request, AiFailureCode::ProviderUnavailable);

    expect($failed->status)->toBe(AiRequestStatus::Failed)
        ->and($failed->error_code)->toBe(AiFailureCode::ProviderUnavailable)
        ->and($failed->canonical_payload)->toBeNull()
        ->and($failed->response_json)->toBeNull()
        ->and(Quota::for($user)->used())->toBe(0);

    $this->assertDatabaseCount('usage_ledger', 2);

    app(FailAiQuery::class)($start->request, AiFailureCode::Internal);

    expect(UsageLedger::query()->count())->toBe(2);
});

it('estorna a reserva em tokens quando o plano pago falha', function () {
    ['user' => $user] = aiPaidUser();
    $start = aiStart($user);
    $reserved = $start->request->reserved_tokens;

    app(FailAiQuery::class)($start->request, AiFailureCode::Timeout);

    $refund = UsageLedger::query()
        ->where('type', LedgerEntryType::Refund)
        ->sole();

    expect($refund->tokens)->toBe($reserved)
        ->and(Quota::for($user)->used())->toBe(0);
});

it('mantem a solicitacao fora do historico enquanto a fase nao expoe historico', function () {
    $user = User::factory()->create();

    $start = aiStart($user);

    expect($start->request->history_visible)->toBeFalse();

    app(SettleAiQuery::class)($start->request, aiActionResult(), 10, 5);

    expect(AiRequest::query()->sole()->history_visible)->toBeFalse()
        ->and(AiCache::query()->count())->toBe(1);
});
