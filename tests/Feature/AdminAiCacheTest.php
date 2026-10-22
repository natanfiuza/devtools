<?php

use App\Enums\AdminActionType;
use App\Models\AdminAction;
use App\Models\AiCache;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
| Cache de IA (F7): a tela mostra metadados e poda as linhas vencidas.
|
| A prova central e negativa: prompt, schema, payload canonico e resposta nunca
| chegam nas props. A tela pode existir sem eles - e e assim que tem de ser.
*/

it('fecha a tela de cache para visitante', function () {
    $this->get(route('admin.ai-cache.index'))
        ->assertRedirect(route('login'));
});

it('fecha a tela de cache para usuario comum', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.ai-cache.index'))
        ->assertForbidden();
});

it('lista so metadados da entrada', function () {
    $admin = User::factory()->admin()->create();

    AiCache::factory()->create([
        'prompt_hash' => str_repeat('a', 32),
        'prompt_version' => 7,
        'tokens_in' => 900,
        'tokens_out' => 100,
        'hits' => 3,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.ai-cache.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/AiCache')
            ->has('entries.data', 1)
            ->where('entries.data.0.hash', str_repeat('a', 12))
            ->where('entries.data.0.promptVersion', 7)
            ->where('entries.data.0.tokensIn', 900)
            ->where('entries.data.0.tokensOut', 100)
            ->where('entries.data.0.hits', 3)
            ->where('entries.data.0.expired', false)
            ->missing('entries.data.0.prompt_hash')
            ->missing('entries.data.0.canonical_payload')
            ->missing('entries.data.0.response_json')
            ->where('filters.state', '')
            ->where('expired', 0));
});

it('filtra pelas expiradas e informa a contagem', function () {
    $admin = User::factory()->admin()->create();

    AiCache::factory()->create();
    AiCache::factory()->expired()->create();

    $this->actingAs($admin)
        ->get(route('admin.ai-cache.index', ['state' => 'expired']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('entries.data', 1)
            ->where('entries.data.0.expired', true)
            ->where('filters.state', 'expired')
            ->where('expired', 1)
            ->where('total', 1));
});

it('poda so as expiradas e registra a auditoria', function () {
    $admin = User::factory()->admin()->create();

    $valida = AiCache::factory()->create();
    AiCache::factory()->expired()->create();
    AiCache::factory()->expired()->create();

    $this->actingAs($admin)
        ->delete(route('admin.ai-cache.destroy-expired'))
        ->assertRedirect();

    expect(AiCache::query()->count())->toBe(1)
        ->and(AiCache::query()->whereKey($valida->id)->exists())->toBeTrue();

    $registro = AdminAction::query()->sole();

    expect($registro->action)->toBe(AdminActionType::AiCachePruned)
        ->and($registro->target_id)->toBeNull()
        ->and($registro->meta)->toMatchArray(['removidas' => 2]);
});

it('nao registra auditoria quando nao ha o que podar', function () {
    $admin = User::factory()->admin()->create();

    AiCache::factory()->create();

    $this->actingAs($admin)
        ->delete(route('admin.ai-cache.destroy-expired'))
        ->assertRedirect();

    expect(AiCache::query()->count())->toBe(1)
        ->and(AdminAction::query()->count())->toBe(0);
});
