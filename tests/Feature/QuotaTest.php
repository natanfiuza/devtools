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

it('acumula a franquia e o consumo entre periodos sucessivos de assinatura mensal', function () {
    $plano = Plan::factory()->create(['monthly_tokens' => 2_000_000]);
    $user = User::factory()->create();

    $inicioAnterior = now()->subMonths(2)->addWeek();
    $fimAnterior = now()->subWeek();

    Subscription::factory()->for($user)->for($plano)->create([
        'current_period_start' => $inicioAnterior,
        'current_period_end' => $fimAnterior,
    ]);

    Subscription::factory()->for($user)->for($plano)->create([
        'current_period_start' => $fimAnterior,
        'current_period_end' => now()->addWeeks(3),
    ]);

    UsageLedger::factory()->for($user)->create([
        'tokens' => 500_000,
        'created_at' => $inicioAnterior->copy()->addDay(),
    ]);

    UsageLedger::factory()->for($user)->create(['tokens' => 100_000]);

    $quota = Quota::for($user);

    expect($quota->limit())->toBe(4_000_000)
        ->and($quota->used())->toBe(600_000)
        ->and($quota->remaining())->toBe(3_400_000);
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

    expect(Quota::for($user)->used())->toBe(200)
        ->and(Quota::for($user)->remaining())->toBe(800);

    // Concessao manual do admin: soma na franquia total (limit) e aumenta o saldo restante.
    UsageLedger::factory()->for($user)->grant()->create(['tokens' => 400]);

    expect(Quota::for($user)->limit())->toBe(1400)
        ->and(Quota::for($user)->used())->toBe(200)
        ->and(Quota::for($user)->remaining())->toBe(1200);
});

it('credita o plano anual mes a mes conforme cada mes calendario inicia', function () {
    $planoAnual = Plan::factory()->create([
        'interval' => 'year',
        'monthly_tokens' => 2_000_000,
    ]);
    $user = User::factory()->create();

    // Assinatura anual iniciada ha 2 meses e 10 dias: ja iniciaram os meses 1, 2 e 3 (3 lotes).
    $inicio = now()->subMonthsNoOverflow(2)->subDays(10);
    $fim = $inicio->copy()->addYear();

    Subscription::factory()->for($user)->for($planoAnual)->create([
        'current_period_start' => $inicio,
        'current_period_end' => $fim,
    ]);

    $quota = Quota::for($user);

    expect($quota->limit())->toBe(6_000_000)
        ->and($quota->nextCreditAt()?->toDateString())
        ->toBe($inicio->copy()->addMonthsNoOverflow(3)->toDateString());
});

it('remove o credito de assinatura estornada e mantem os meses ja iniciados em troca de plano', function () {
    $plano = Plan::factory()->create(['monthly_tokens' => 2_000_000]);
    $user = User::factory()->create();

    // Assinatura encerrada por troca de plano (ended_at preenchido, refunded_at nulo): mantem 1 mes iniciado.
    $trocada = Subscription::factory()->for($user)->for($plano)->create([
        'current_period_start' => now()->subDays(20),
        'current_period_end' => now()->addDays(10),
    ]);
    $trocada->end(refunded: false);

    // Assinatura estornada (refunded_at preenchido): credita 0.
    $estornada = Subscription::factory()->for($user)->for($plano)->create([
        'current_period_start' => now()->subDays(10),
        'current_period_end' => now()->addDays(20),
    ]);
    $estornada->end(refunded: true);

    // Assinatura ativa atual: credita 1 mes.
    Subscription::factory()->for($user)->for($plano)->create([
        'current_period_start' => now()->subDay(),
        'current_period_end' => now()->addMonth(),
    ]);

    expect(Quota::for($user)->limit())->toBe(4_000_000);
});

it('volta para a franquia do Free quando a assinatura vence mas reativa o saldo acumulado ao assinar novamente', function () {
    $plano = Plan::factory()->create(['monthly_tokens' => 2_000_000]);
    $user = User::factory()->create();

    // Assinatura passada vencida (nao estornada): rendeu 2M, usuario gastou 500k.
    Subscription::factory()->for($user)->for($plano)->create([
        'current_period_start' => now()->subMonths(2),
        'current_period_end' => now()->subMonth(),
    ]);
    UsageLedger::factory()->for($user)->create([
        'tokens' => 500_000,
        'created_at' => now()->subMonths(2)->addDay(),
    ]);

    // Sem assinatura ativa: esta no Free (conta uses).
    $quotaFree = Quota::for($user);
    expect($quotaFree->unit())->toBe('uses')
        ->and($quotaFree->limit())->toBe(5);

    // Assina novamente: soma os 2M antigos + 2M novos = 4M, menos 500k usados = 3.5M restantes.
    Subscription::factory()->for($user)->for($plano)->create([
        'current_period_start' => now()->subDay(),
        'current_period_end' => now()->addMonth(),
    ]);

    $quotaReativada = Quota::for($user->refresh());
    expect($quotaReativada->unit())->toBe('tokens')
        ->and($quotaReativada->limit())->toBe(4_000_000)
        ->and($quotaReativada->used())->toBe(500_000)
        ->and($quotaReativada->remaining())->toBe(3_500_000);
});

it('nao fecha a janela quando o periodo nao tem fim', function () {
    $plano = Plan::factory()->create(['monthly_tokens' => 1000]);
    $user = User::factory()->create();

    Subscription::factory()->for($user)->for($plano)->lifetime()->create([
        'current_period_start' => now()->subWeek(),
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
