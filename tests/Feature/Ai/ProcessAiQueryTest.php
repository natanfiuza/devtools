<?php

use App\Actions\Ai\StartAiQuery;
use App\Enums\AiFailureCode;
use App\Enums\AiRequestStatus;
use App\Enums\LedgerEntryType;
use App\Enums\SchemaSource;
use App\Enums\SqlDialect;
use App\Exceptions\Ai\ProviderException;
use App\Jobs\ProcessAiQuery;
use App\Models\AiRequest;
use App\Models\OutputTarget;
use App\Models\UsageLedger;
use App\Models\User;
use App\Support\Ai\AiCacheRepository;
use App\Support\Ai\AiResult;
use App\Support\Ai\Canonicalizer;
use App\Support\Ai\CanonicalPayload;
use App\Support\Quota;
use Illuminate\Contracts\Queue\Job as QueueJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\TimeoutExceededException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();

    config([
        'ai.base_url' => 'https://api.deepseek.test',
        'ai.model' => 'test-model',
        'ai.api_key' => 'test-key',
        'ai.estimate_output_tokens' => 900,
        'ai.inflight_ttl_seconds' => 600,
        'ai.job.timeout' => 90,
        'ai.job.tries' => 2,
        'ai.job.backoff' => [5, 15],
    ]);
});

function processAiRequest(string $schema = 'CREATE TABLE users (id int, name varchar(80));'): AiRequest
{
    $user = User::factory()->create();
    $target = OutputTarget::query()->where('slug', 'sql')->sole();

    return app(StartAiQuery::class)(
        $user,
        $target,
        SchemaSource::Ddl,
        SqlDialect::Mysql,
        $schema,
        'Liste os usuarios',
    )->request;
}

function processAiContract(string $sql = 'SELECT id FROM users', SqlDialect $dialect = SqlDialect::Mysql): array
{
    return [
        'sql' => $sql,
        'dialect' => $dialect->value,
        'assumptions' => [],
        'warnings' => [],
        'tables_used' => ['users'],
        'needs_clarification' => false,
        'clarification' => null,
        'outputs' => ['sql' => $sql],
    ];
}

/**
 * @param  array<string, mixed>  $contract
 * @return array<string, mixed>
 */
function processAiProviderBody(array $contract, int $tokensIn = 30, int $tokensOut = 10): array
{
    return [
        'model' => 'test-model',
        'choices' => [
            ['message' => ['content' => json_encode($contract, JSON_THROW_ON_ERROR)]],
        ],
        'usage' => ['prompt_tokens' => $tokensIn, 'completion_tokens' => $tokensOut],
    ];
}

function processAiPayload(): CanonicalPayload
{
    return (new Canonicalizer)->canonicalize(
        SchemaSource::Ddl,
        SqlDialect::Mysql,
        'sql',
        'CREATE TABLE users (id int, name varchar(80));',
        'Liste os usuarios',
        'test-model',
        1,
    );
}

function processAiResult(): AiResult
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

it('processa a solicitacao, liquida e grava o cache', function () {
    $request = processAiRequest();

    Http::fake([
        '*' => Http::response(processAiProviderBody(processAiContract()), 200),
    ]);

    app()->call([new ProcessAiQuery($request->id), 'handle']);

    $request->refresh();

    expect($request->status)->toBe(AiRequestStatus::Done)
        ->and($request->tokens_in)->toBe(30)
        ->and($request->tokens_out)->toBe(10)
        ->and($request->cache_hit)->toBeFalse()
        ->and($request->canonical_payload)->toBeNull()
        ->and($request->result()['sql'] ?? null)->toBe('SELECT id FROM users')
        ->and(Quota::for($request->user)->used())->toBe(1);

    $this->assertDatabaseHas('ai_cache', [
        'prompt_hash' => $request->prompt_hash,
        'tokens_in' => 30,
        'tokens_out' => 10,
    ]);

    $this->assertDatabaseCount('usage_ledger', 1);
});

it('estorna a reserva quando encontra a resposta no cache durante o job', function () {
    $request = processAiRequest();
    $payload = processAiPayload();

    app(AiCacheRepository::class)->store($payload, processAiResult(), 30, 10);

    Http::fake();

    app()->call([new ProcessAiQuery($request->id), 'handle']);

    $request->refresh();

    expect($request->status)->toBe(AiRequestStatus::Done)
        ->and($request->cache_hit)->toBeTrue()
        ->and($request->tokens_in)->toBe(30)
        ->and($request->tokens_out)->toBe(10)
        ->and($request->canonical_payload)->toBeNull()
        ->and(Quota::for($request->user)->used())->toBe(0);

    Http::assertNothingSent();

    $refund = UsageLedger::query()->where('type', LedgerEntryType::Refund)->sole();

    expect($refund->uses)->toBe(1)
        ->and(UsageLedger::query()->count())->toBe(2);
});

it('marca contrato invalido e estorna a reserva', function () {
    $request = processAiRequest();

    Http::fake([
        '*' => Http::response(processAiProviderBody(['sql' => 'SELECT 1']), 200),
    ]);

    app()->call([new ProcessAiQuery($request->id), 'handle']);

    $request->refresh();

    expect($request->status)->toBe(AiRequestStatus::Failed)
        ->and($request->error_code)->toBe(AiFailureCode::InvalidContract)
        ->and($request->canonical_payload)->toBeNull()
        ->and(Quota::for($request->user)->used())->toBe(0);

    $this->assertDatabaseCount('usage_ledger', 2);
});

it('recusa SQL de escrita vindo do provedor', function () {
    $request = processAiRequest();

    Http::fake([
        '*' => Http::response(processAiProviderBody(processAiContract('DELETE FROM users')), 200),
    ]);

    app()->call([new ProcessAiQuery($request->id), 'handle']);

    $request->refresh();

    expect($request->status)->toBe(AiRequestStatus::Failed)
        ->and($request->error_code)->toBe(AiFailureCode::UnsafeSql)
        ->and(Quota::for($request->user)->used())->toBe(0);
});

it('relanca erro retentavel do provedor enquanto houver tentativa', function () {
    $request = processAiRequest();

    Http::fake([
        '*' => Http::response('', 503),
    ]);

    expect(fn () => app()->call([new ProcessAiQuery($request->id), 'handle']))
        ->toThrow(ProviderException::class);

    $request->refresh();

    expect($request->status)->toBe(AiRequestStatus::Processing)
        ->and(Quota::for($request->user)->used())->toBe(1);

    $this->assertDatabaseCount('usage_ledger', 1);
});

it('falha e estorna quando o erro retentavel esgota as tentativas', function () {
    $request = processAiRequest();

    Http::fake([
        '*' => Http::response('', 503),
    ]);

    $job = new ProcessAiQuery($request->id);

    /** @var QueueJob&MockInterface $queueJob */
    $queueJob = $this->mock(QueueJob::class);
    $queueJob->shouldReceive('attempts')->andReturn(2);
    $job->setJob($queueJob);

    app()->call([$job, 'handle']);

    $request->refresh();

    expect($request->status)->toBe(AiRequestStatus::Failed)
        ->and($request->error_code)->toBe(AiFailureCode::ProviderUnavailable)
        ->and(Quota::for($request->user)->used())->toBe(0);

    $this->assertDatabaseCount('usage_ledger', 2);
});

it('nao repete erro definitivo do provedor', function () {
    $request = processAiRequest();

    Http::fake([
        '*' => Http::response('', 401),
    ]);

    app()->call([new ProcessAiQuery($request->id), 'handle']);

    $request->refresh();

    expect($request->status)->toBe(AiRequestStatus::Failed)
        ->and($request->error_code)->toBe(AiFailureCode::ProviderUnavailable)
        ->and(Quota::for($request->user)->used())->toBe(0);
});

it('encerra como timeout no hook de falha do worker', function () {
    $request = processAiRequest();

    (new ProcessAiQuery($request->id))->failed(new TimeoutExceededException('tempo esgotado'));

    $request->refresh();

    expect($request->status)->toBe(AiRequestStatus::Failed)
        ->and($request->error_code)->toBe(AiFailureCode::Timeout)
        ->and($request->canonical_payload)->toBeNull()
        ->and(Quota::for($request->user)->used())->toBe(0);

    $this->assertDatabaseCount('usage_ledger', 2);
});

it('nao reprocessa solicitacao terminal', function () {
    $user = User::factory()->create();
    $request = AiRequest::factory()->done()->for($user)->create();

    Http::fake();

    app()->call([new ProcessAiQuery($request->id), 'handle']);

    Http::assertNothingSent();

    expect($request->refresh()->status)->toBe(AiRequestStatus::Done);
});

it('falha quando o payload da solicitacao nao pode ser reidratado', function () {
    $request = processAiRequest();

    $request->forceFill(['canonical_payload' => null])->save();

    Http::fake();

    app()->call([new ProcessAiQuery($request->id), 'handle']);

    $request->refresh();

    expect($request->status)->toBe(AiRequestStatus::Failed)
        ->and($request->error_code)->toBe(AiFailureCode::Internal)
        ->and(Quota::for($request->user)->used())->toBe(0);

    Http::assertNothingSent();
});
