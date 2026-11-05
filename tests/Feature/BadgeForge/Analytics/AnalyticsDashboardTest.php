<?php

use App\Enums\BadgeViewSubject;
use App\Models\BadgeAsset;
use App\Models\BadgeView;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
| Secao de analytics da dashboard (F7, ADR-086).
|
| A pagina entrega a prop `analytics` ja resolvida: Free em 30 dias sem
| quebras, pago com a janela pedida. Janela invalida nunca vira 422 e uma
| migracao ausente devolve a secao indisponivel, nunca um 500.
*/

function analyticsDashboardAccount(bool $paid = false, string $email = 'dashboard@example.com'): User
{
    $user = User::factory()->create(['email' => $email]);

    if ($paid) {
        $plan = Plan::query()->where('slug', 'pro-monthly')->sole();

        Subscription::factory()->for($user)->for($plan)->create([
            'current_period_start' => now()->subWeek(),
            'current_period_end' => now()->addWeeks(3),
        ]);
    }

    return $user->refresh();
}

it('convidado nao acessa a dashboard', function () {
    $this->get(route('dashboard.badgeforge'))->assertRedirect(route('login'));
});

it('free recebe a janela de 30 dias e so os contadores por imagem', function () {
    $user = analyticsDashboardAccount();
    $asset = BadgeAsset::factory()->for($user)->create(['name' => 'readme']);

    BadgeView::factory()->create([
        'user_id' => $user->getKey(),
        'subject_type' => BadgeViewSubject::Personal,
        'subject_id' => (string) $asset->getKey(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.badgeforge'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/BadgeForge')
            ->where('analytics.isPaid', false)
            ->where('analytics.window', 30)
            ->where('analytics.windows', [])
            ->where('analytics.totals.views', 1)
            ->where('analytics.subjects.0.name', 'readme')
            ->where('analytics.days', [])
            ->where('analytics.cache', null)
            ->where('analytics.exportUrl', null)
        );
});

it('pago recebe a janela pedida e cai no padrao em valor invalido', function () {
    $user = analyticsDashboardAccount(paid: true);

    $this->actingAs($user)
        ->get(route('dashboard.badgeforge', ['window' => 7]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('analytics.isPaid', true)
            ->where('analytics.window', 7)
            ->where('analytics.windows', [7, 30, 90])
            ->has('analytics.days', 7)
            ->where('analytics.exportUrl', route('badgeforge.analytics.export', ['window' => 7]))
        );

    $this->actingAs($user)
        ->get(route('dashboard.badgeforge', ['window' => 'hack']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('analytics.window', 30)
            ->has('analytics.days', 30)
        );
});

it('migracao ausente devolve a secao indisponivel sem derrubar a pagina', function () {
    $user = analyticsDashboardAccount();

    Schema::rename('badge_views', 'badge_views_ausente');

    try {
        $this->actingAs($user)
            ->get(route('dashboard.badgeforge'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard/BadgeForge')
                ->where('analytics', null)
            );
    } finally {
        Schema::rename('badge_views_ausente', 'badge_views');
    }
});
