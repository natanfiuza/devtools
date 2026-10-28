<?php

use App\Enums\DonationStatus;
use App\Models\Donation;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/*
| `donations:check {ulid}` (ADR-059).
|
| O comando revela o que o provedor respondeu e roda a mesma sincronizacao do
| webhook. Nao imprime token nem o e-mail completo do pagador: o terminal e
| visivel para quem tem acesso ao VPS, mas PII nao circula em log.
*/

beforeEach(function () {
    config([
        'payments.mercadopago.access_token' => 'token-de-teste',
        'payments.mercadopago.base_url' => 'https://api.mercadopago.com',
    ]);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function pagamentoParaDiagnostico(Donation $donation, array $overrides = []): array
{
    return array_merge([
        'id' => 'pay_9',
        'status' => 'approved',
        'status_detail' => 'accredited',
        'transaction_amount' => $donation->amount_cents / 100,
        'currency_id' => $donation->currency,
        'external_reference' => 'don_'.$donation->ulid,
        'payment_type_id' => 'pix',
        'payment_method_id' => 'pix',
        'live_mode' => true,
        'collector_id' => 78128962,
        'date_created' => '2026-10-21T09:55:00.000-03:00',
        'date_approved' => '2026-10-21T10:00:00.000-03:00',
        'date_of_expiration' => '2026-10-21T11:55:00.000-03:00',
        'payer' => ['email' => 'maria.silva@example.com'],
    ], $overrides);
}

it('consulta o pagamento, imprime o diagnostico e sincroniza a doacao', function () {
    $donation = Donation::factory()->create([
        'gateway_ref' => 'pay_9',
        'amount_cents' => 2500,
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'api.mercadopago.com/v1/payments/pay_9' => Http::response(pagamentoParaDiagnostico($donation)),
    ]);

    $exit = Artisan::call('donations:check', ['ulid' => $donation->ulid]);
    $output = Artisan::output();

    expect($exit)->toBe(Command::SUCCESS)
        ->and($output)->toContain($donation->ulid)
        ->and($output)->toContain('Status no provedor')
        ->and($output)->toContain('accredited')
        ->and($output)->toContain('live_mode')
        ->and($output)->toContain('true')
        ->and($output)->toContain('78128962')
        ->and($output)->toContain('Sincronizacao: confirmed')
        ->and($output)->toContain('paid');

    expect($donation->refresh()->status)->toBe(DonationStatus::Paid)
        ->and($donation->gateway_status)->toBe('approved')
        ->and($donation->gateway_status_detail)->toBe('accredited');
});

it('mascara o e-mail do pagador e nunca imprime o token', function () {
    $donation = Donation::factory()->create([
        'gateway_ref' => 'pay_9',
        'amount_cents' => 2500,
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'api.mercadopago.com/v1/payments/pay_9' => Http::response(pagamentoParaDiagnostico($donation)),
    ]);

    Artisan::call('donations:check', ['ulid' => $donation->ulid]);
    $output = Artisan::output();

    expect($output)->toContain('@example.com')
        ->and($output)->not->toContain('maria.silva@example.com')
        ->and($output)->not->toContain('token-de-teste');
});

it('mostra o detalhe da recusa e marca a doacao como falha', function () {
    $donation = Donation::factory()->create([
        'gateway_ref' => 'pay_9',
        'amount_cents' => 2500,
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'api.mercadopago.com/v1/payments/pay_9' => Http::response(pagamentoParaDiagnostico($donation, [
            'status' => 'rejected',
            'status_detail' => 'rejected_high_risk',
            'date_approved' => null,
            'payer' => ['email' => 'maria.silva@example.com'],
        ])),
    ]);

    $exit = Artisan::call('donations:check', ['ulid' => $donation->ulid]);

    expect($exit)->toBe(Command::SUCCESS)
        ->and(Artisan::output())->toContain('rejected_high_risk')
        ->and($donation->refresh()->status)->toBe(DonationStatus::Failed)
        ->and($donation->gateway_status_detail)->toBe('rejected_high_risk');
});

it('avisa quando o pagamento foi criado com credencial de teste', function () {
    $donation = Donation::factory()->create([
        'gateway_ref' => 'pay_9',
        'amount_cents' => 2500,
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'api.mercadopago.com/v1/payments/pay_9' => Http::response(pagamentoParaDiagnostico($donation, [
            'live_mode' => false,
        ])),
    ]);

    $exit = Artisan::call('donations:check', ['ulid' => $donation->ulid]);

    expect($exit)->toBe(Command::SUCCESS)
        ->and(Artisan::output())->toContain('live_mode=false');
});

it('nao sincroniza pagamento que pertence a outra doacao', function () {
    $donation = Donation::factory()->create([
        'gateway_ref' => 'pay_9',
        'amount_cents' => 2500,
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'api.mercadopago.com/v1/payments/pay_9' => Http::response(pagamentoParaDiagnostico($donation, [
            'external_reference' => 'don_01ARZ3NDEKTSV4RRFFQ69G5FAV',
        ])),
    ]);

    $exit = Artisan::call('donations:check', ['ulid' => $donation->ulid]);

    expect($exit)->toBe(Command::FAILURE)
        ->and(Artisan::output())->toContain('divergente')
        ->and($donation->refresh()->status)->toBe(DonationStatus::Pending)
        ->and($donation->gateway_status)->toBeNull();
});

it('revela a anomalia mas nao reabre doacao marcada como falha', function () {
    $donation = Donation::factory()->create([
        'gateway_ref' => 'pay_9',
        'amount_cents' => 2500,
        'status' => DonationStatus::Failed,
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'api.mercadopago.com/v1/payments/pay_9' => Http::response(pagamentoParaDiagnostico($donation)),
    ]);

    $exit = Artisan::call('donations:check', ['ulid' => $donation->ulid]);

    expect($exit)->toBe(Command::FAILURE)
        ->and(Artisan::output())->toContain('anomalia')
        ->and($donation->refresh()->status)->toBe(DonationStatus::Failed);
});

it('recusa doacao sem cobranca no provedor', function () {
    Http::preventStrayRequests();

    $estatica = Donation::factory()->staticPix()->create();

    $exit = Artisan::call('donations:check', ['ulid' => $estatica->ulid]);

    expect($exit)->toBe(Command::FAILURE)
        ->and(Artisan::output())->toContain('nada para consultar')
        ->and($estatica->refresh()->status)->toBe(DonationStatus::Pending);
});

it('recusa quando o gateway esta sem credenciais', function () {
    config(['payments.mercadopago.access_token' => null]);

    $donation = Donation::factory()->create(['gateway_ref' => 'pay_9']);

    Http::preventStrayRequests();

    $exit = Artisan::call('donations:check', ['ulid' => $donation->ulid]);

    expect($exit)->toBe(Command::FAILURE)
        ->and(Artisan::output())->toContain('sem credenciais');
});

it('recusa ULID invalido ou desconhecido', function (string $ulid, string $message) {
    $exit = Artisan::call('donations:check', ['ulid' => $ulid]);

    expect($exit)->toBe(Command::FAILURE)
        ->and(Artisan::output())->toContain($message);
})->with([
    'formato invalido' => ['nao-e-ulid', 'ULID invalido'],
    'desconhecido' => ['01ARZ3NDEKTSV4RRFFQ69G5FAV', 'Doacao nao encontrada'],
]);
