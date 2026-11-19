<?php

use App\Models\ColorPalette;
use App\Models\LayoutSystem;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Colors\PaletteQuota;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
| Cota conjunta de itens Colors (F4, ADR-170).
|
| Paletas e sistemas de layout dividem o mesmo teto; arquivados contam e so a
| exclusao definitiva libera uma vaga. Conta paga nao tem limite.
*/

function layoutSystemPaidAccount(User $user): void
{
    $plan = Plan::query()->where('slug', 'pro-monthly')->sole();

    Subscription::factory()->for($user)->for($plan)->create([
        'current_period_start' => now()->subWeek(),
        'current_period_end' => now()->addWeeks(3),
    ]);
}

it('soma paletas e sistemas no total usado', function () {
    $user = User::factory()->create();

    ColorPalette::factory()->count(2)->for($user)->create();
    LayoutSystem::factory()->count(3)->for($user)->create();

    $quota = new PaletteQuota;

    expect($quota->used($user))->toBe(5)
        ->and($quota->summary($user))->toBe(['limit' => 25, 'used' => 5, 'remaining' => 20]);
});

it('bloqueia no teto free somando os dois tipos e contando arquivados', function () {
    config(['colors.palettes.free_limit' => 3]);

    $user = User::factory()->create();

    ColorPalette::factory()->for($user)->create();
    LayoutSystem::factory()->for($user)->create();
    LayoutSystem::factory()->for($user)->archived()->create();

    $quota = new PaletteQuota;

    expect($quota->used($user))->toBe(3)
        ->and($quota->reached($user))->toBeTrue();
});

it('libera a vaga apenas quando o sistema e excluido', function () {
    config(['colors.palettes.free_limit' => 2]);

    $user = User::factory()->create();
    ColorPalette::factory()->for($user)->create();
    $system = LayoutSystem::factory()->for($user)->create();

    $quota = new PaletteQuota;

    expect($quota->reached($user))->toBeTrue();

    $system->forceFill(['archived_at' => now()])->save();

    expect($quota->reached($user))->toBeTrue();

    $system->delete();

    expect($quota->reached($user))->toBeFalse()
        ->and($quota->used($user))->toBe(1);
});

it('nao limita conta paga', function () {
    config(['colors.palettes.free_limit' => 1]);

    $user = User::factory()->create();
    layoutSystemPaidAccount($user);

    ColorPalette::factory()->for($user)->create();
    LayoutSystem::factory()->count(2)->for($user)->create();

    $quota = new PaletteQuota;

    expect($quota->limit($user))->toBeNull()
        ->and($quota->reached($user))->toBeFalse()
        ->and($quota->summary($user)['remaining'])->toBeNull();
});

it('mantem cota acima do teto apos downgrade', function () {
    config(['colors.palettes.free_limit' => 2]);

    $user = User::factory()->create();
    ColorPalette::factory()->count(2)->for($user)->create();
    $system = LayoutSystem::factory()->for($user)->create();

    $quota = new PaletteQuota;

    expect($quota->used($user))->toBe(3)
        ->and($quota->summary($user)['remaining'])->toBe(0)
        ->and($quota->reached($user))->toBeTrue()
        ->and($system->exists)->toBeTrue();
});

it('conta apenas os itens do dono', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    LayoutSystem::factory()->count(2)->for($other)->create();
    LayoutSystem::factory()->for($user)->create();

    expect((new PaletteQuota)->used($user))->toBe(1);
});
