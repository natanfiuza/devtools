<?php

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\UsageLedger;
use App\Models\User;
use App\Support\Quota;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('conta usos vitalicios no Free', function () {
    $user = User::factory()->create();

    UsageLedger::factory()->count(2)->for($user)->create();

    $quota = Quota::for($user);

    expect($quota->unit())->toBe('uses')
        ->and($quota->limit())->toBe(5)
        ->and($quota->used())->toBe(2)
        ->and($quota->remaining())->toBe(3)
        ->and($quota->exhausted())->toBeFalse()
        ->and($quota->windowStart())->toBeNull()
        ->and($quota->windowEnd())->toBeNull();
});

it('devolve cota no estorno e na concessao', function () {
    $user = User::factory()->create();

    UsageLedger::factory()->count(3)->for($user)->create();
    UsageLedger::factory()->for($user)->refund()->create();
    UsageLedger::factory()->for($user)->grant()->create();

    $quota = Quota::for($user);

    expect($quota->used())->toBe(1)
        ->and($quota->remaining())->toBe(4);
});

it('esgota a cota quando o consumo alcanca a franquia', function () {
    $user = User::factory()->create();

    UsageLedger::factory()->count(5)->for($user)->create();

    $quota = Quota::for($user);

    expect($quota->used())->toBe(5)
        ->and($quota->remaining())->toBe(0)
        ->and($quota->exhausted())->toBeTrue();
});

it('nao devolve saldo negativo quando o consumo passa da franquia', function () {
    $user = User::factory()->create();

    UsageLedger::factory()->count(7)->for($user)->create();

    expect(Quota::for($user)->remaining())->toBe(0);
});

it('conta tokens do periodo na assinatura vigente', function () {
    $plano = Plan::factory()->create(['monthly_tokens' => 1000]);
    $user = User::factory()->create();

    Subscription::factory()->for($user)->for($plano)->create([
        'current_period_start' => now()->subWeek(),
        'current_period_end' => now()->addWeeks(3),
    ]);

    UsageLedger::factory()->for($user)->create(['tokens' => 300]);
    UsageLedger::factory()->for($user)->create(['tokens' => 200]);

    $quota = Quota::for($user);

    expect($quota->unit())->toBe('tokens')
        ->and($quota->limit())->toBe(1000)
        ->and($quota->used())->toBe(500)
        ->and($quota->remaining())->toBe(500)
        ->and($quota->plan->monthly_tokens)->toBe(1000);
});

it('ignora o consumo anterior ao periodo corrente', function () {
    $plano = Plan::factory()->create(['monthly_tokens' => 1000]);
    $user = User::factory()->create();

    $inicio = now()->subWeek();

    Subscription::factory()->for($user)->for($plano)->create([
        'current_period_start' => $inicio,
        'current_period_end' => now()->addWeeks(3),
    ]);

    // Gasto do periodo passado: a franquia do mes nao carrega o que ja passou.
    UsageLedger::factory()->for($user)->create([
        'tokens' => 900,
        'created_at' => $inicio->copy()->subDay(),
    ]);

    UsageLedger::factory()->for($user)->create(['tokens' => 100]);

    expect(Quota::for($user)->used())->toBe(100)
        ->and(Quota::for($user)->remaining())->toBe(900);
});

it('soma estorno e concessao ao saldo do assinante', function () {
    $plano = Plan::factory()->create(['monthly_tokens' => 1000]);
    $user = User::factory()->create();

    Subscription::factory()->for($user)->for($plano)->create([
        'current_period_start' => now()->subWeek(),
        'current_period_end' => now()->addWeeks(3),
    ]);

    UsageLedger::factory()->for($user)->create(['tokens' => 300]);
    UsageLedger::factory()->for($user)->refund()->create(['tokens' => 100]);

    expect(Quota::for($user)->remaining())->toBe(800);

    // Concessao manual do admin: entra como cota a mais, nao como consumo - por
    // isso o consumo fica negativo e o saldo passa da franquia do plano.
    UsageLedger::factory()->for($user)->grant()->create(['tokens' => 400]);

    expect(Quota::for($user)->used())->toBe(-200)
        ->and(Quota::for($user)->remaining())->toBe(1200);
});

it('nao fecha a janela quando o periodo nao tem fim', function () {
    $plano = Plan::factory()->create(['monthly_tokens' => 1000]);
    $user = User::factory()->create();

    Subscription::factory()->for($user)->for($plano)->lifetime()->create([
        'current_period_start' => now()->subWeek(),
    ]);

    // Sem fim, o periodo corrente e do inicio da concessao para frente - o que
    // veio antes dela nao entra.
    UsageLedger::factory()->for($user)->create([
        'tokens' => 250,
        'created_at' => now()->subYear(),
    ]);

    UsageLedger::factory()->for($user)->create(['tokens' => 40]);

    expect(Quota::for($user)->windowEnd())->toBeNull()
        ->and(Quota::for($user)->used())->toBe(40);
});

it('espelha o periodo da assinatura como janela da cota', function () {
    $plano = Plan::factory()->create(['monthly_tokens' => 1000]);
    $user = User::factory()->create();

    $inicio = now()->subWeek();
    $fim = now()->addWeeks(3);

    Subscription::factory()->for($user)->for($plano)->create([
        'current_period_start' => $inicio,
        'current_period_end' => $fim,
    ]);

    $quota = Quota::for($user);

    expect($quota->windowStart()?->toDateString())->toBe($inicio->toDateString())
        ->and($quota->windowEnd()?->toDateString())->toBe($fim->toDateString());
});
