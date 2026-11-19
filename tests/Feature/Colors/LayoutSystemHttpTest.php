<?php

use App\Models\ColorPalette;
use App\Models\LayoutSystem;
use App\Models\User;
use Database\Factories\LayoutSystemFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
| Area da conta do Layout Harmonizer por HTTP (F4, ADR-168).
|
| Recurso de outra conta responde 404; o estado e validado no servidor; o
| ciclo de vida usa as mesmas rotas das paletas.
*/

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function layoutSystemHttpPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Sistema HTTP',
        'description' => null,
        'visibility' => 'private',
        'tags' => [],
        'state' => LayoutSystemFactory::stateArray(),
    ], $overrides);
}

it('redireciona visitante ao login', function () {
    $this->get(route('dashboard.colors.systems.index'))->assertRedirect(route('login'));
    $this->get(route('dashboard.colors.systems.create'))->assertRedirect(route('login'));
});

it('lista apenas os sistemas do dono com filtros e cota', function () {
    $user = User::factory()->create();
    LayoutSystem::factory()->for($user)->create(['name' => 'Ativo']);
    LayoutSystem::factory()->for($user)->archived()->create(['name' => 'Arquivado']);
    LayoutSystem::factory()->create(['name' => 'De Outro']);

    $this->actingAs($user)
        ->get(route('dashboard.colors.systems.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/colors/LayoutSystemsIndex')
            ->has('systems.data', 1)
            ->where('systems.data.0.name', 'Ativo')
            ->where('filters.status', 'active')
            ->where('limits.limit', 25)
            ->where('limits.used', 2)
            ->has('form.nameMaxLength')
            ->has('tags'));

    $this->actingAs($user)
        ->get(route('dashboard.colors.systems.index', ['status' => 'archived']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('systems.data', 1)
            ->where('systems.data.0.name', 'Arquivado'));
});

it('responde 404 para sistema de outra conta', function () {
    $system = LayoutSystem::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard.colors.systems.edit', $system))
        ->assertNotFound();

    $this->actingAs($user)
        ->patch(route('dashboard.colors.systems.update', $system), layoutSystemHttpPayload())
        ->assertNotFound();

    $this->actingAs($user)
        ->delete(route('dashboard.colors.systems.destroy', $system), ['confirm_name' => $system->name])
        ->assertNotFound();
});

it('cria o sistema e abre o editor vinculado', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->post(route('dashboard.colors.systems.store'), layoutSystemHttpPayload([
            'tags' => ['Design', 'Web'],
        ]));

    $system = LayoutSystem::sole();

    $response->assertRedirect(route('dashboard.colors.systems.edit', $system));

    expect($system->user_id)->toBe($user->id)
        ->and($system->name)->toBe('Sistema HTTP')
        ->and($system->tokens_count)->toBe(66)
        ->and($system->slug)->toBeNull()
        ->and($system->tags()->count())->toBe(2);
});

it('recusa estado invalido e origem inacessivel', function () {
    $user = User::factory()->create();
    $invalid = layoutSystemHttpPayload();
    $invalid['state']['snapshot']['snapshotVersion'] = 99;

    $this->actingAs($user)
        ->post(route('dashboard.colors.systems.store'), $invalid)
        ->assertSessionHasErrors('state');

    $palette = ColorPalette::factory()->create();

    $this->actingAs($user)
        ->post(route('dashboard.colors.systems.store'), layoutSystemHttpPayload([
            'source_palette_id' => $palette->id,
        ]))
        ->assertSessionHasErrors('source_palette_id');
});

it('edita o sistema e recusa alteracao de arquivado', function () {
    $user = User::factory()->create();
    $system = LayoutSystem::factory()->for($user)->create(['name' => 'Antigo']);
    $archived = LayoutSystem::factory()->for($user)->archived()->create();

    $this->actingAs($user)
        ->patch(route('dashboard.colors.systems.update', $system), layoutSystemHttpPayload([
            'name' => 'Novo',
            'visibility' => 'public',
        ]))
        ->assertRedirect();

    expect($system->refresh()->name)->toBe('Novo')
        ->and($system->slug)->toBe('novo');

    $this->actingAs($user)
        ->patch(route('dashboard.colors.systems.update', $archived), layoutSystemHttpPayload())
        ->assertSessionHasErrors('layout_system');
});

it('arquiva, restaura e exclui com confirmacao pelo nome', function () {
    $user = User::factory()->create();
    $system = LayoutSystem::factory()->for($user)->public()->create(['name' => 'Meu Sistema']);

    $this->actingAs($user)
        ->patch(route('dashboard.colors.systems.archive', $system))
        ->assertRedirect();

    expect($system->refresh()->isArchived())->toBeTrue();

    $this->actingAs($user)
        ->patch(route('dashboard.colors.systems.restore', $system))
        ->assertRedirect();

    expect($system->refresh()->isArchived())->toBeFalse();

    $this->actingAs($user)
        ->delete(route('dashboard.colors.systems.destroy', $system), ['confirm_name' => 'Errado'])
        ->assertSessionHasErrors('confirm_name');

    $this->actingAs($user)
        ->delete(route('dashboard.colors.systems.destroy', $system), ['confirm_name' => 'Meu Sistema'])
        ->assertRedirect(route('dashboard.colors.systems.index'));

    expect(LayoutSystem::query()->count())->toBe(0);
});

it('duplica o proprio sistema pela area da conta', function () {
    $user = User::factory()->create();
    $system = LayoutSystem::factory()->for($user)->public()->create(['name' => 'Original']);

    $response = $this->actingAs($user)
        ->post(route('dashboard.colors.systems.duplicate', $system));

    $copy = LayoutSystem::query()->whereKeyNot($system->id)->sole();

    $response->assertRedirect(route('dashboard.colors.systems.edit', $copy));

    expect($copy->name)->toContain('Original')
        ->and($copy->slug)->toBeNull()
        ->and($copy->duplicated_from_id)->toBe($system->id);
});
