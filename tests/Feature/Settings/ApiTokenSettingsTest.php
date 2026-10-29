<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
| Tokens da API publica (ADR-050).
|
| O texto puro so existe uma vez: no flash do POST. A lista mostra nome,
| abilities e datas, nunca o valor. Revogar e definitivo e so alcanca os
| tokens da propria conta.
*/

it('lista os tokens da conta sem expor o valor', function () {
    $user = User::factory()->create([
        'api_requests_count' => 14,
        'api_sql_queries_count' => 5,
    ]);
    $token = $user->createToken('integracao', ['ai:query']);
    $token->accessToken->forceFill([
        'requests_count' => 9,
        'sql_queries_count' => 3,
    ])->save();

    $this->actingAs($user)
        ->get(route('api-tokens.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/ApiTokens')
            ->where('plainTextToken', null)
            ->where('stats.totalRequests', 14)
            ->where('stats.totalSqlQueries', 5)
            ->where('stats.activeTokens', 1)
            ->has('tokens', 1)
            ->where('tokens.0.name', 'integracao')
            ->where('tokens.0.abilities', ['ai:query'])
            ->where('tokens.0.requestsCount', 9)
            ->where('tokens.0.sqlQueriesCount', 3)
            ->missing('tokens.0.plainTextToken'));
});

it('cria o token e mostra o texto puro apenas na requisicao seguinte', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('api-tokens.store'), [
        'name' => 'integracao',
    ]);

    $response->assertRedirect(route('api-tokens.index'));

    $plainTextToken = $response->getSession()->get('api_token');

    expect($plainTextToken)->toBeString()
        ->and($plainTextToken)->toContain('|');

    $this->assertDatabaseHas('personal_access_tokens', [
        'tokenable_id' => $user->id,
        'tokenable_type' => $user->getMorphClass(),
        'name' => 'integracao',
    ]);

    $this->actingAs($user)
        ->get(route('api-tokens.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('plainTextToken', $plainTextToken)
            ->has('tokens', 1));

    // O flash vale uma vez: recarregar a tela nao devolve o valor.
    $this->actingAs($user)
        ->get(route('api-tokens.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('plainTextToken', null));
});

it('nao aceita nome repetido na mesma conta', function () {
    $user = User::factory()->create();
    $user->createToken('integracao', ['ai:query']);

    $this->actingAs($user)
        ->from(route('api-tokens.index'))
        ->post(route('api-tokens.store'), ['name' => 'integracao'])
        ->assertSessionHasErrors('name');

    expect($user->tokens()->count())->toBe(1);
});

it('permite o mesmo nome em contas diferentes', function () {
    User::factory()->create()->createToken('integracao', ['ai:query']);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('api-tokens.store'), ['name' => 'integracao'])
        ->assertSessionHasNoErrors();

    expect($user->tokens()->count())->toBe(1);
});

it('revoga somente um token da propria conta', function () {
    $user = User::factory()->create();
    $proprio = $user->createToken('proprio', ['ai:query'])->accessToken;
    $outro = User::factory()->create()->createToken('alheio', ['ai:query'])->accessToken;

    $this->actingAs($user)
        ->delete(route('api-tokens.destroy', ['token' => $outro->id]))
        ->assertNotFound();

    $this->actingAs($user)
        ->delete(route('api-tokens.destroy', ['token' => $proprio->id]))
        ->assertSessionHasNoErrors();

    expect($user->tokens()->count())->toBe(0)
        ->and($outro->fresh())->not->toBeNull();
});

it('token revogado deixa de autenticar', function () {
    $user = User::factory()->create();
    $token = $user->createToken('cli', ['ai:query']);

    $this->withToken($token->plainTextToken)
        ->getJson('/api/v1/me')
        ->assertOk();

    $token->accessToken->delete();

    // O guard guarda o usuario resolvido entre requisicoes no mesmo teste.
    app('auth')->forgetGuards();

    $this->withToken($token->plainTextToken)
        ->getJson('/api/v1/me')
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'unauthenticated');
});

it('preserva os contadores acumulados da conta ao revogar um token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('cli', ['ai:query']);

    $this->withToken($token->plainTextToken)
        ->getJson('/api/v1/me')
        ->assertOk();

    $this->withToken($token->plainTextToken)
        ->getJson('/api/v1/output-targets')
        ->assertOk();

    expect($token->accessToken->fresh()->requests_count)->toBe(2)
        ->and($user->fresh()->api_requests_count)->toBe(2);

    $this->actingAs($user)
        ->delete(route('api-tokens.destroy', ['token' => $token->accessToken->id]))
        ->assertSessionHasNoErrors();

    expect($user->tokens()->count())->toBe(0)
        ->and($user->fresh()->api_requests_count)->toBe(2);

    $this->actingAs($user->fresh())
        ->get(route('api-tokens.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.totalRequests', 2)
            ->where('stats.activeTokens', 0));
});

it('entrega metricas de uso com estatisticas diarias de tokens e requisicoes', function () {
    $user = User::factory()->create();
    $token = $user->createToken('servidor-node', ['ai:query']);

    \App\Models\AiRequest::factory()->done()->create([
        'user_id' => $user->id,
        'api_token_id' => $token->accessToken->id,
        'tokens_in' => 450,
        'tokens_out' => 150,
        'created_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('api-tokens.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('usageStats')
            ->where('usageStats.period.type', 'current_month')
            ->where('usageStats.summary.totalTokens', 600)
            ->where('usageStats.summary.totalRequests', 1)
            ->has('usageStats.days')
            ->has('usageStats.tokens', 1)
            ->where('usageStats.tokens.0.name', 'servidor-node'));
});

it('permite filtrar metricas de uso pelo mes anterior ou periodo customizado', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('api-tokens.index', ['period' => 'previous_month']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('usageStats.period.type', 'previous_month'));

    $this->actingAs($user)
        ->get(route('api-tokens.index', [
            'period' => 'custom',
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-15',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('usageStats.period.type', 'custom')
            ->where('usageStats.period.startDate', '2026-05-01')
            ->where('usageStats.period.endDate', '2026-05-15'));
});
