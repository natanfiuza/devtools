<?php

use App\Enums\AiRequestStatus;
use App\Enums\SchemaSource;
use App\Enums\SqlDialect;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/*
| API publica v1 (ADR-050).
|
| A autenticacao e Bearer token do Sanctum com a ability `ai:query`; a cota e a
| mesma do plano e a leitura e escopada pelo token que criou a consulta.
*/

beforeEach(function () {
    Queue::fake();

    config([
        'ai.base_url' => 'https://api.deepseek.test',
        'ai.model' => 'test-model',
        'ai.api_key' => 'test-key',
        'ai.estimate_output_tokens' => 900,
        'ai.inflight_ttl_seconds' => 600,
        'ai.polling.initial_delay_ms' => 1500,
        'api.rate_limits.post_per_minute' => 30,
        'api.rate_limits.post_per_hour' => 300,
        'api.rate_limits.read_per_minute' => 120,
    ]);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function apiV1Body(array $overrides = []): array
{
    return array_merge([
        'schema_source' => 'ddl',
        'dialect' => 'mysql',
        'output_target' => 'sql',
        'schema' => 'CREATE TABLE users (id int, name varchar(80));',
        'description' => 'Liste os usuarios',
    ], $overrides);
}

/**
 * @param  list<string>  $abilities
 */
function apiV1Token(User $user, array $abilities = ['ai:query']): string
{
    return $user->createToken('cli', $abilities)->plainTextToken;
}

/**
 * O guard guarda o usuario resolvido entre requisicoes no mesmo teste; trocar
 * de Bearer token exige esquecer o guard para o Sanctum reler o token.
 */
function apiV1ForgetGuards(): void
{
    app('auth')->forgetGuards();
}

function apiV1PaidUser(): User
{
    $plan = Plan::factory()->create([
        'monthly_tokens' => 100000,
        'max_schema_bytes' => 65536,
        'max_prompt_chars' => 4000,
    ]);

    $user = User::factory()->create();

    Subscription::factory()->for($user)->for($plan)->create([
        'current_period_start' => now()->subDay(),
        'current_period_end' => now()->addMonth(),
    ]);

    return $user;
}

function apiV1Payload(): CanonicalPayload
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

function apiV1Result(): AiResult
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

it('recusa pedido sem token', function () {
    $this->getJson('/api/v1/me')
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'unauthenticated');
});

it('recusa token sem a ability da API', function () {
    $user = User::factory()->create();

    $this->withToken(apiV1Token($user, ['outra:coisa']))
        ->getJson('/api/v1/me')
        ->assertForbidden()
        ->assertJsonPath('error.code', 'forbidden');
});

it('devolve conta, plano e cota do dono do token', function () {
    $user = User::factory()->create();

    $this->withToken(apiV1Token($user))
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.email', $user->email)
        ->assertJsonPath('data.plan.slug', 'free')
        ->assertJsonPath('data.plan.is_free', true)
        ->assertJsonPath('data.quota.unit', 'uses')
        ->assertJsonPath('data.quota.limit', 5)
        ->assertJsonPath('data.quota.used', 0)
        ->assertJsonMissingPath('data.role');
});

it('lista somente os alvos habilitados', function () {
    OutputTarget::factory()->create([
        'slug' => 'alvo-desligado',
        'enabled' => false,
    ]);

    $user = User::factory()->create();

    $response = $this->withToken(apiV1Token($user))
        ->getJson('/api/v1/output-targets')
        ->assertOk();

    $slugs = array_column($response->json('data'), 'slug');

    expect($slugs)->toContain('sql')
        ->and($slugs)->not->toContain('alvo-desligado');

    $sql = collect($response->json('data'))->firstWhere('slug', 'sql');

    expect($sql)->toHaveKeys(['slug', 'label', 'language', 'supported_dialects'])
        ->and($sql)->not->toHaveKey('prompt_template');
});

it('cria a consulta do Free e devolve 202 com o poll url', function () {
    $user = User::factory()->create();

    $response = $this->withToken(apiV1Token($user))
        ->postJson('/api/v1/sql-queries', apiV1Body())
        ->assertStatus(202)
        ->assertJsonPath('data.status', 'queued')
        ->assertJsonPath('data.cache_hit', false)
        ->assertJsonPath('data.output_target', 'sql')
        ->assertJsonPath('data.quota.unit', 'uses')
        ->assertJsonPath('data.quota.used', 1);

    $id = $response->json('data.id');

    expect($response->json('data.poll_url'))
        ->toContain('/api/v1/sql-queries/'.$id)
        ->and($response->json('data.created_at'))->not->toBeNull();

    $request = AiRequest::query()->whereKey($id)->sole();

    expect($request->api_token_id)->toBe($user->tokens()->sole()->id)
        ->and($request->user_id)->toBe($user->id)
        ->and($request->history_visible)->toBeTrue();

    $this->assertDatabaseCount('usage_ledger', 1);
});

it('segue o opt-in do historico da conta', function () {
    $user = User::factory()->create(['history_enabled' => false]);

    $this->withToken(apiV1Token($user))
        ->postJson('/api/v1/sql-queries', apiV1Body())
        ->assertStatus(202);

    expect(AiRequest::query()->sole()->history_visible)->toBeFalse();
});

it('devolve 200 quando o cache responde', function () {
    $user = User::factory()->create();
    $repository = app(AiCacheRepository::class);

    $repository->store(apiV1Payload(), apiV1Result(), 40, 10);

    $this->withToken(apiV1Token($user))
        ->postJson('/api/v1/sql-queries', apiV1Body())
        ->assertOk()
        ->assertJsonPath('data.status', 'done')
        ->assertJsonPath('data.cache_hit', true)
        ->assertJsonPath('data.result.sql', 'SELECT id FROM users')
        ->assertJsonPath('data.tokens.in', 40)
        ->assertJsonPath('data.quota.used', 0);

    $this->assertDatabaseCount('usage_ledger', 0);
});

it('consulta o status pelo poll url e para de pollar no fim', function () {
    $user = User::factory()->create();
    $token = apiV1Token($user);

    $id = $this->withToken($token)
        ->postJson('/api/v1/sql-queries', apiV1Body())
        ->json('data.id');

    $this->withToken($token)
        ->getJson('/api/v1/sql-queries/'.$id)
        ->assertOk()
        ->assertJsonPath('data.id', $id)
        ->assertJsonPath('data.status', 'queued');

    AiRequest::query()->whereKey($id)->update([
        'status' => AiRequestStatus::Done->value,
        'response_json' => json_encode(['sql' => 'SELECT 1'], JSON_THROW_ON_ERROR),
    ]);

    $this->withToken($token)
        ->getJson('/api/v1/sql-queries/'.$id)
        ->assertOk()
        ->assertJsonPath('data.status', 'done')
        ->assertJsonPath('data.poll_url', null);
});

it('permite que qualquer token da conta leia consultas da mesma conta (inclusive da web)', function () {
    $user = User::factory()->create();
    $tokenA = apiV1Token($user);
    $tokenB = apiV1Token($user);

    $id = $this->withToken($tokenA)
        ->postJson('/api/v1/sql-queries', apiV1Body())
        ->json('data.id');

    $web = AiRequest::factory()->for($user)->done()->create([
        'title' => 'Consulta Web',
        'prompt' => 'Listar usuarios ativos',
        'schema' => 'CREATE TABLE users (id int);',
    ]);

    apiV1ForgetGuards();

    $this->withToken($tokenB)
        ->getJson('/api/v1/sql-queries/'.$id)
        ->assertOk()
        ->assertJsonPath('data.id', $id);

    apiV1ForgetGuards();

    $this->withToken($tokenB)
        ->getJson('/api/v1/sql-queries/'.$web->id)
        ->assertOk()
        ->assertJsonPath('data.id', $web->id)
        ->assertJsonPath('data.title', 'Consulta Web')
        ->assertJsonPath('data.description', 'Listar usuarios ativos')
        ->assertJsonPath('data.schema', 'CREATE TABLE users (id int);')
        ->assertJsonPath('data.schema_source', 'ddl')
        ->assertJsonPath('data.dialect', 'mysql');

    apiV1ForgetGuards();

    $this->withToken($tokenB)
        ->getJson('/api/v1/sql-queries')
        ->assertOk()
        ->assertJsonPath('meta.total', 2);
});

it('nao expoe consulta de outra conta', function () {
    $user = User::factory()->create();
    $token = apiV1Token($user);

    $alheia = AiRequest::factory()->done()->create();

    $this->withToken($token)
        ->getJson('/api/v1/sql-queries/'.$alheia->id)
        ->assertNotFound();

    $this->withToken($token)
        ->getJson('/api/v1/sql-queries')
        ->assertOk()
        ->assertJsonPath('meta.total', 0)
        ->assertJsonPath('data', []);
});

it('filtra a lista por status', function () {
    $user = User::factory()->create();
    $token = apiV1Token($user);
    $tokenId = $user->tokens()->sole()->id;

    AiRequest::factory()->for($user)->done()->create(['api_token_id' => $tokenId]);
    AiRequest::factory()->for($user)->create(['api_token_id' => $tokenId]);

    $this->withToken($token)
        ->getJson('/api/v1/sql-queries?status=done')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.status', 'done');

    $this->withToken($token)
        ->getJson('/api/v1/sql-queries?status=invalido')
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error');
});

it('valida o corpo com o mesmo contrato da web', function () {
    $user = User::factory()->create();

    $this->withToken(apiV1Token($user))
        ->postJson('/api/v1/sql-queries', apiV1Body(['description' => '']))
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonStructure(['error' => ['code', 'message', 'details' => ['errors' => ['description']]]]);

    $this->assertDatabaseCount('ai_requests', 0);
});

it('devolve 402 quando a cota do plano acabou', function () {
    $user = User::factory()->create();
    UsageLedger::factory()->count(5)->for($user)->create();

    $this->withToken(apiV1Token($user))
        ->postJson('/api/v1/sql-queries', apiV1Body())
        ->assertStatus(402)
        ->assertJsonPath('error.code', 'quota_exceeded')
        ->assertJsonPath('error.details.unit', 'uses');

    Queue::assertNothingPushed();
});

it('aplica o limite de taxa por token e devolve Retry-After', function () {
    config([
        'api.rate_limits.post_per_minute' => 1,
        'api.rate_limits.post_per_hour' => 1,
    ]);

    $user = apiV1PaidUser();
    $token = apiV1Token($user);

    $this->withToken($token)
        ->postJson('/api/v1/sql-queries', apiV1Body())
        ->assertStatus(202);

    $response = $this->withToken($token)
        ->postJson('/api/v1/sql-queries', apiV1Body(['description' => 'Outra descricao']))
        ->assertStatus(429)
        ->assertJsonPath('error.code', 'rate_limited');

    expect($response->headers->has('Retry-After'))->toBeTrue();
});

it('nega conta suspensa mesmo com token valido', function () {
    $user = User::factory()->create(['suspended_at' => now()]);

    $this->withToken(apiV1Token($user))
        ->getJson('/api/v1/me')
        ->assertForbidden()
        ->assertJsonPath('error.code', 'account_suspended');
});

it('nao escreve schema nem descricao no log', function () {
    Log::spy();

    $user = User::factory()->create();

    $this->withToken(apiV1Token($user))
        ->postJson('/api/v1/sql-queries', apiV1Body([
            'schema' => 'CREATE TABLE segredo_muito_unico (id int);',
            'description' => 'descricao_confidencial_unica',
        ]))
        ->assertStatus(202);

    foreach (['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'] as $level) {
        Log::shouldNotHaveReceived($level, function (mixed $message): bool {
            $text = is_string($message) ? $message : (string) json_encode($message);

            return str_contains($text, 'segredo_muito_unico')
                || str_contains($text, 'descricao_confidencial_unica');
        });
    }
});
