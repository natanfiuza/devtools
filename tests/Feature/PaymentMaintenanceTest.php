<?php

use App\Enums\CheckoutStatus;
use App\Enums\DonationStatus;
use App\Models\CheckoutSession;
use App\Models\Donation;
use App\Models\Payment;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('vence checkouts e doacoes pendentes com prazo estourado', function () {
    $checkoutVencido = CheckoutSession::factory()->create(['expires_at' => now()->subMinute()]);
    $checkoutAberto = CheckoutSession::factory()->create(['expires_at' => now()->addHour()]);
    $recorrenciaAutorizada = CheckoutSession::factory()->recurring()->create([
        'expires_at' => null,
        'authorized_at' => now(),
    ]);
    $checkoutAprovado = CheckoutSession::factory()->approved()->create([
        'expires_at' => now()->subMinute(),
    ]);

    $doacaoVencida = Donation::factory()->create(['expires_at' => now()->subMinute()]);
    $doacaoAberta = Donation::factory()->create(['expires_at' => now()->addHour()]);
    $doacaoPaga = Donation::factory()->paid()->create(['expires_at' => now()->subMinute()]);
    $pixEstatico = Donation::factory()->staticPix()->create(['expires_at' => null]);

    $this->artisan('payments:expire')->assertSuccessful();

    expect($checkoutVencido->refresh()->status)->toBe(CheckoutStatus::Expired)
        ->and($checkoutAberto->refresh()->status)->toBe(CheckoutStatus::Pending)
        ->and($recorrenciaAutorizada->refresh()->status)->toBe(CheckoutStatus::Pending)
        ->and($checkoutAprovado->refresh()->status)->toBe(CheckoutStatus::Approved)
        ->and($doacaoVencida->refresh()->status)->toBe(DonationStatus::Expired)
        ->and($doacaoAberta->refresh()->status)->toBe(DonationStatus::Pending)
        ->and($doacaoPaga->refresh()->status)->toBe(DonationStatus::Paid)
        ->and($pixEstatico->refresh()->status)->toBe(DonationStatus::Pending);
});

it('conta no dry-run sem vencer nada', function () {
    $checkout = CheckoutSession::factory()->create(['expires_at' => now()->subMinute()]);
    $doacao = Donation::factory()->create(['expires_at' => now()->subMinute()]);

    $this->artisan('payments:expire', ['--dry-run' => true])->assertSuccessful();

    expect($checkout->refresh()->status)->toBe(CheckoutStatus::Pending)
        ->and($doacao->refresh()->status)->toBe(DonationStatus::Pending);
});

it('poda apenas eventos de webhook antigos', function () {
    $antigo = WebhookEvent::factory()->create();
    $antigo->forceFill(['created_at' => now()->subDays(40)])->save();

    $recente = WebhookEvent::factory()->create();
    $recente->forceFill(['created_at' => now()->subDay()])->save();

    // Registros fiscais e doacoes ficam onde estao, independentemente da idade.
    $pagamento = Payment::factory()->create();
    $pagamento->forceFill(['created_at' => now()->subDays(40)])->save();

    $doacao = Donation::factory()->create();
    $doacao->forceFill(['created_at' => now()->subDays(40)])->save();

    $this->artisan('webhooks:prune')->assertSuccessful();

    $this->assertDatabaseMissing('webhook_events', ['id' => $antigo->id]);
    $this->assertDatabaseHas('webhook_events', ['id' => $recente->id]);
    $this->assertDatabaseHas('payments', ['id' => $pagamento->id]);
    $this->assertDatabaseHas('donations', ['id' => $doacao->id]);
});

it('respeita a janela de dias informada', function () {
    $vinteDias = WebhookEvent::factory()->create();
    $vinteDias->forceFill(['created_at' => now()->subDays(20)])->save();

    $this->artisan('webhooks:prune', ['--days' => 10])->assertSuccessful();

    $this->assertDatabaseMissing('webhook_events', ['id' => $vinteDias->id]);
});

it('nunca trata zero dias como apagar tudo', function () {
    $recente = WebhookEvent::factory()->create();
    $recente->forceFill(['created_at' => now()->subHour()])->save();

    $this->artisan('webhooks:prune', ['--days' => 0])->assertSuccessful();

    $this->assertDatabaseHas('webhook_events', ['id' => $recente->id]);
});

it('conta no dry-run sem apagar eventos', function () {
    $antigo = WebhookEvent::factory()->create();
    $antigo->forceFill(['created_at' => now()->subDays(40)])->save();

    $this->artisan('webhooks:prune', ['--dry-run' => true])->assertSuccessful();

    $this->assertDatabaseHas('webhook_events', ['id' => $antigo->id]);
});
