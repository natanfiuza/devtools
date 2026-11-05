<?php

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
| Throttle da gestao do BadgeForge (F8, ADR-089).
|
| As acoes de dialogo (POST/PATCH/DELETE) continuam recusando com toast: o
| cliente e o navegador num fluxo Inertia. O export de analytics foge disso -
| e um GET de download - e precisa da recusa em JSON com `Retry-After`.
*/

function managementThrottleAccount(bool $paid = false): User
{
    $user = User::factory()->create();

    if ($paid) {
        $plan = Plan::query()->where('slug', 'pro-monthly')->sole();

        Subscription::factory()->for($user)->for($plan)->create([
            'current_period_start' => now()->subWeek(),
            'current_period_end' => now()->addWeeks(3),
        ]);
    }

    return $user->refresh();
}

function managementThrottleExportUrl(): string
{
    return route('badgeforge.analytics.export', ['window' => 7]);
}

beforeEach(function () {
    config([
        'badgeforge.management.rate_limit' => 1,
        'badgeforge.management.rate_decay_seconds' => 60,
    ]);
});

it('recusa o export GET com 429 JSON e Retry-After', function () {
    $user = managementThrottleAccount(paid: true);

    $this->actingAs($user)->get(managementThrottleExportUrl())->assertOk();

    $this->actingAs($user)
        ->get(managementThrottleExportUrl())
        ->assertStatus(429)
        ->assertHeader('Retry-After')
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonPath('error.code', 'rate_limited');
});

it('mantem o toast nas acoes de dialogo', function () {
    $user = managementThrottleAccount();

    $this->actingAs($user)
        ->from(route('dashboard.badgeforge'))
        ->post(route('badgeforge.presets.store'), ['name' => 'a', 'form' => []])
        ->assertRedirect(route('dashboard.badgeforge'));

    $this->actingAs($user)
        ->from(route('dashboard.badgeforge'))
        ->post(route('badgeforge.presets.store'), ['name' => 'b', 'form' => []])
        ->assertRedirect(route('dashboard.badgeforge'))
        ->assertSessionHas('inertia.flash_data');
});
