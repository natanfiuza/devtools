<?php

use App\Enums\SchemaSource;
use App\Enums\SqlDialect;
use App\Jobs\ProcessAiQuery;
use App\Models\AiRequest;
use App\Models\UsageLedger;
use App\Models\User;
use App\Support\Ai\AiCacheRepository;
use App\Support\Ai\AiResult;
use App\Support\Ai\Canonicalizer;
use App\Support\Ai\CanonicalPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();

    config([
        'ai.base_url' => 'https://api.deepseek.test',
        'ai.model' => 'test-model',
        'ai.api_key' => 'test-key',
        'ai.estimate_output_tokens' => 900,
        'ai.inflight_ttl_seconds' => 600,
        'ai.polling.initial_delay_ms' => 1500,
        'ai.rate_limits.post_per_minute' => 6,
        'ai.rate_limits.post_per_hour' => 60,
        'ai.rate_limits.post_per_minute_ip' => 30,
    ]);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function apiAiBody(array $overrides = []): array
{
    return array_merge([
        'schema_source' => 'ddl',
        'dialect' => 'mysql',
        'output_target' => 'sql',
        'schema' => 'CREATE TABLE users (id int, name varchar(80));',
        'description' => 'Liste os usuarios',
    ], $overrides);
}

function apiAiPost(User $user, array $overrides = []): TestResponse
{
    return test()->actingAs($user)->postJson('/api/ai/queries', apiAiBody($overrides));
}

function apiAiPayload(): CanonicalPayload
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

function apiAiResult(): AiResult
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

it('recusa visitante sem sessao', function () {
    $this->postJson('/api/ai/queries', apiAiBody())
        ->assertStatus(401)
        ->assertJsonPath('error.code', 'unauthenticated');
});

it('recusa conta sem e-mail verificado', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->postJson('/api/ai/queries', apiAiBody())
        ->assertStatus(403);

    Queue::assertNothingPushed();
});

it('recusa conta suspensa e derruba a sessao', function () {
    $user = User::factory()->create(['suspended_at' => now()]);

    $this->actingAs($user)
        ->postJson('/api/ai/queries', apiAiBody())
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'account_suspended');

    $this->assertGuest();
});

it('cria a solicitacao do Free e devolve 202', function () {
    $user = User::factory()->create();

    $response = apiAiPost($user);

    $response->assertStatus(202)
        ->assertJsonPath('data.status', 'queued')
        ->assertJsonPath('data.cache_hit', false)
        ->assertJsonPath('data.quota.unit', 'uses')
        ->assertJsonPath('data.quota.used', 1)
        ->assertJsonPath('data.quota.remaining', 4)
        ->assertJsonPath('data.poll_after_ms', 1500)
        ->assertJsonPath('data.error_code', null);

    $id = $response->json('data.id');

    expect($id)->toBeString();

    Queue::assertPushed(
        ProcessAiQuery::class,
        fn (ProcessAiQuery $job): bool => $job->aiRequestId === $id,
    );

    $this->assertDatabaseCount('usage_ledger', 1);
});

it('devolve o cache com 200 sem criar debito', function () {
    $user = User::factory()->create();

    app(AiCacheRepository::class)->store(apiAiPayload(), apiAiResult(), 40, 10);

    $response = apiAiPost($user);

    $response->assertStatus(200)
        ->assertJsonPath('data.status', 'done')
        ->assertJsonPath('data.cache_hit', true)
        ->assertJsonPath('data.result.sql', 'SELECT id FROM users')
        ->assertJsonPath('data.tokens.in', 40)
        ->assertJsonPath('data.tokens.out', 10)
        ->assertJsonPath('data.quota.used', 0);

    $this->assertDatabaseCount('usage_ledger', 0);

    Queue::assertNothingPushed();
});

it('deduplica o mesmo pedido em voo', function () {
    $user = User::factory()->create();

    $first = apiAiPost($user);
    $second = apiAiPost($user);

    expect($second->json('data.id'))->toBe($first->json('data.id'))
        ->and($second->status())->toBe(202)
        ->and($second->json('data.quota.used'))->toBe(1);

    $this->assertDatabaseCount('ai_requests', 1);
    $this->assertDatabaseCount('usage_ledger', 1);

    Queue::assertPushed(ProcessAiQuery::class, 1);
});

it('devolve 422 no envelope de validacao', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/ai/queries', apiAiBody(['description' => '']))
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonStructure(['error' => ['details' => ['errors' => ['description']]]]);
});

it('recusa schema acima do limite do plano', function () {
    $user = User::factory()->create();

    apiAiPost($user, ['schema' => str_repeat('a', 9000)])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'schema_too_large')
        ->assertJsonPath('error.details.field', 'schema');

    $this->assertDatabaseCount('ai_requests', 0);
});

it('recusa alvo desligado ou inexistente', function () {
    $user = User::factory()->create();

    apiAiPost($user, ['output_target' => 'prisma_x'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error');
});

it('devolve 402 quando a cota acabou', function () {
    $user = User::factory()->create();
    UsageLedger::factory()->count(5)->for($user)->create();

    apiAiPost($user)
        ->assertStatus(402)
        ->assertJsonPath('error.code', 'quota_exceeded')
        ->assertJsonPath('error.details.unit', 'uses')
        ->assertJsonPath('error.details.required', 1);

    $this->assertDatabaseCount('ai_requests', 0);
});

it('devolve 503 quando a IA nao esta configurada', function () {
    config(['ai.api_key' => null]);

    $user = User::factory()->create();

    apiAiPost($user)
        ->assertStatus(503)
        ->assertJsonPath('error.code', 'ai_unavailable');

    Queue::assertNothingPushed();
});

it('devolve 429 com Retry-After ao estourar o limite', function () {
    config(['ai.rate_limits.post_per_minute' => 1]);

    $user = User::factory()->create();

    apiAiPost($user)->assertStatus(202);

    $response = apiAiPost($user);

    $response->assertStatus(429)
        ->assertJsonPath('error.code', 'rate_limited');

    expect($response->headers->get('Retry-After'))->not->toBeNull();
});

it('devolve 404 para solicitacao de outra conta', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $request = AiRequest::factory()->done()->for($owner)->create();

    $this->actingAs($other)
        ->getJson('/api/ai/queries/'.$request->id)
        ->assertStatus(404)
        ->assertJsonPath('error.code', 'not_found');
});

it('devolve a solicitacao concluida para o dono', function () {
    $user = User::factory()->create();
    $request = AiRequest::factory()->done()->for($user)->create();

    $this->actingAs($user)
        ->getJson('/api/ai/queries/'.$request->id)
        ->assertStatus(200)
        ->assertJsonPath('data.id', $request->id)
        ->assertJsonPath('data.status', 'done')
        ->assertJsonPath('data.result.sql', 'SELECT id, name FROM users')
        ->assertJsonPath('data.poll_after_ms', null)
        ->assertJsonPath('data.quota.unit', 'uses');
});

it('devolve 200 com poll_after no estado em fila', function () {
    $user = User::factory()->create();
    $request = AiRequest::factory()->free()->for($user)->create();

    $this->actingAs($user)
        ->getJson('/api/ai/queries/'.$request->id)
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'queued')
        ->assertJsonPath('data.poll_after_ms', 1500);
});
