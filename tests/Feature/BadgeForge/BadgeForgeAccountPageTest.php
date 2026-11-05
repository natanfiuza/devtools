<?php

use App\Models\User;
use App\Support\BadgeForge\BadgeForgeToolProps;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
| Pagina `/dashboard/badgeforge` (ADR-079).
|
| A pagina de conta reusa o contrato da tool: os limites e as listas vem de
| `BadgeForgeToolProps::for($user)`, para a tela do painel e o builder nunca
| divergirem sobre o que a conta pode ver.
*/

it('fecha a pagina para visitante, conta nao verificada e suspensa', function () {
    $unverified = User::factory()->unverified()->create();
    $suspended = User::factory()->create(['suspended_at' => now()]);

    $this->get(route('dashboard.badgeforge'))->assertRedirect(route('login'));

    $this->actingAs($unverified)
        ->get(route('dashboard.badgeforge'))
        ->assertRedirect(route('verification.notice'));

    $this->actingAs($suspended)
        ->get(route('dashboard.badgeforge'))
        ->assertRedirect(route('login'));
});

it('entrega a pagina com o mesmo contrato da tool', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard.badgeforge'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/BadgeForge')
            ->where('toolProps', BadgeForgeToolProps::for($user))
            ->where('toolUrl', route('tools.show', ['slug' => 'badgeforge']))
            ->where('upgradeUrl', route('pricing'))
        );
});

it('repassa as props da conta para a tool no builder', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/en/tools/badgeforge')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tools/Show')
            ->where('slug', 'badgeforge')
            ->where('toolProps', BadgeForgeToolProps::for($user))
        );
});

it('devolve o visitante da tool sem gestao', function () {
    $this->get('/en/tools/badgeforge')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tools/Show')
            ->where('toolProps.management', [
                'canManage' => false,
                'canSavePresets' => false,
                'canCreatePersonalBadges' => false,
                'canCreateEndpointBadges' => false,
                'canCreateCustomProviders' => false,
                'isPaid' => false,
            ])
        );
});

it('limita as escritas da gestao com toast em vez de 429', function () {
    config([
        'badgeforge.management.rate_limit' => 1,
        'badgeforge.management.rate_decay_seconds' => 60,
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('dashboard.badgeforge'))
        ->post(route('badgeforge.presets.store'), ['name' => 'a'])
        ->assertRedirect(route('dashboard.badgeforge'))
        ->assertSessionHasErrors(['form']);

    $this->actingAs($user)
        ->from(route('dashboard.badgeforge'))
        ->post(route('badgeforge.presets.store'), ['name' => 'b', 'form' => []])
        ->assertRedirect(route('dashboard.badgeforge'))
        ->assertSessionHasNoErrors();
});
