<?php

use App\Enums\DonationStatus;
use App\Models\Donation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
| Consulta sob demanda da doacao ("Ja paguei", ADR-059).
|
| O botao consulta o provedor pelo id gravado na doacao e roda a mesma
| sincronizacao do webhook. Doacao resolvida nao gasta consulta; referencia
| divergente nao sincroniza; falha do provedor vira toast e nao muda o estado.
*/

beforeEach(function () {
    config([
        'payments.mercadopago.access_token' => 'token-de-teste',
        'payments.mercadopago.base_url' => 'https://api.mercadopago.com',
        'donations.check_per_minute' => 5,
    ]);
});

/**
 * Payload de um pagamento Pix da doacao, como o provedor devolve.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function pagamentoParaConsulta(Donation $donation, array $overrides = []): array
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
        'date_approved' => '2026-10-21T10:00:00.000-03:00',
    ], $overrides);
}

it('consulta o provedor e confirma a doacao aprovada', function () {
    $donation = Donation::factory()->create([
        'gateway_ref' => 'pay_9',
        'amount_cents' => 2500,
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'api.mercadopago.com/v1/payments/pay_9' => Http::response(pagamentoParaConsulta($donation)),
    ]);

    $this->from('/pt-br/donate/'.$donation->ulid)
        ->post('/pt-br/donate/'.$donation->ulid.'/check')
        ->assertRedirect('/pt-br/donate/'.$donation->ulid);

    expect($donation->refresh()->status)->toBe(DonationStatus::Paid)
        ->and($donation->paid_at)->not->toBeNull()
        ->and($donation->gateway_status)->toBe('approved')
        ->and($donation->gateway_status_detail)->toBe('accredited');

    $this->get('/pt-br/donate/'.$donation->ulid)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('donation.status', 'paid')
            ->hasFlash('toast.type', 'success')
            ->hasFlash('toast.message', trans('donations.check.success', [], 'pt_BR'))
        );
});

it('mantem a doacao pendente enquanto o provedor nao confirma', function () {
    $donation = Donation::factory()->create([
        'gateway_ref' => 'pay_9',
        'amount_cents' => 2500,
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'api.mercadopago.com/v1/payments/pay_9' => Http::response(pagamentoParaConsulta($donation, [
            'status' => 'pending',
            'status_detail' => 'pending_waiting_transfer',
            'date_approved' => null,
        ])),
    ]);

    $this->from('/pt-br/donate/'.$donation->ulid)
        ->post('/pt-br/donate/'.$donation->ulid.'/check')
        ->assertRedirect('/pt-br/donate/'.$donation->ulid);

    expect($donation->refresh()->status)->toBe(DonationStatus::Pending)
        ->and($donation->gateway_status)->toBe('pending')
        ->and($donation->gateway_status_detail)->toBe('pending_waiting_transfer');

    $this->get('/pt-br/donate/'.$donation->ulid)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->hasFlash('toast.type', 'info')
            ->hasFlash('toast.message', trans('donations.check.pending', [], 'pt_BR'))
        );
});

it('marca a doacao como falha e mostra o motivo da recusa', function () {
    $donation = Donation::factory()->create([
        'gateway_ref' => 'pay_9',
        'amount_cents' => 2500,
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'api.mercadopago.com/v1/payments/pay_9' => Http::response(pagamentoParaConsulta($donation, [
            'status' => 'rejected',
            'status_detail' => 'rejected_by_bank',
            'date_approved' => null,
        ])),
    ]);

    $this->from('/pt-br/donate/'.$donation->ulid)
        ->post('/pt-br/donate/'.$donation->ulid.'/check')
        ->assertRedirect('/pt-br/donate/'.$donation->ulid);

    expect($donation->refresh()->status)->toBe(DonationStatus::Failed)
        ->and($donation->gateway_status)->toBe('rejected')
        ->and($donation->gateway_status_detail)->toBe('rejected_by_bank');

    $html = $this->get('/pt-br/donate/'.$donation->ulid)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('donation.status', 'failed')
            ->where('donation.failureReason', trans('donations.reasons.bank', [], 'pt_BR'))
            ->hasFlash('toast.type', 'warning')
            ->hasFlash('toast.message', trans('donations.check.failed', [], 'pt_BR'))
        )
        ->getContent();

    // O codigo cru fica no banco e no admin; o doador so ve o motivo traduzido.
    expect($html)->not->toContain('rejected_by_bank');
});

it('confirma uma doacao vencida que foi paga dentro do prazo do provedor', function () {
    $donation = Donation::factory()->expired()->create([
        'gateway_ref' => 'pay_9',
        'amount_cents' => 2500,
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'api.mercadopago.com/v1/payments/pay_9' => Http::response(pagamentoParaConsulta($donation)),
    ]);

    $this->from('/pt-br/donate/'.$donation->ulid)
        ->post('/pt-br/donate/'.$donation->ulid.'/check')
        ->assertRedirect('/pt-br/donate/'.$donation->ulid);

    expect($donation->refresh()->status)->toBe(DonationStatus::Paid);
});

it('nao consulta o provedor para doacao ja resolvida', function (DonationStatus $status) {
    $donation = Donation::factory()->create([
        'gateway_ref' => 'pay_9',
        'status' => $status,
    ]);

    Http::preventStrayRequests();

    $this->from('/pt-br/donate/'.$donation->ulid)
        ->post('/pt-br/donate/'.$donation->ulid.'/check')
        ->assertRedirect('/pt-br/donate/'.$donation->ulid);

    Http::assertNothingSent();

    expect($donation->refresh()->status)->toBe($status);

    $this->get('/pt-br/donate/'.$donation->ulid)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->hasFlash('toast.type', 'info')
            ->hasFlash('toast.message', trans('donations.check.already', [], 'pt_BR'))
        );
})->with([
    'paga' => [DonationStatus::Paid],
    'estornada' => [DonationStatus::Refunded],
    'falha' => [DonationStatus::Failed],
]);

it('nao consulta doacao sem cobranca no provedor', function () {
    Http::preventStrayRequests();

    $estatica = Donation::factory()->staticPix()->create();
    $semReferencia = Donation::factory()->create(['gateway_ref' => null]);

    foreach ([$estatica, $semReferencia] as $donation) {
        $this->from('/pt-br/donate/'.$donation->ulid)
            ->post('/pt-br/donate/'.$donation->ulid.'/check')
            ->assertRedirect('/pt-br/donate/'.$donation->ulid);
    }

    Http::assertNothingSent();

    expect($estatica->refresh()->status)->toBe(DonationStatus::Pending)
        ->and($semReferencia->refresh()->status)->toBe(DonationStatus::Pending);

    $this->get('/pt-br/donate/'.$semReferencia->ulid)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->hasFlash('toast.type', 'warning')
            ->hasFlash('toast.message', trans('donations.check.unavailable', [], 'pt_BR'))
        );
});

it('mostra erro e mantem a doacao quando o provedor falha', function () {
    $donation = Donation::factory()->create([
        'gateway_ref' => 'pay_9',
        'amount_cents' => 2500,
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'api.mercadopago.com/v1/payments/pay_9' => Http::response(['message' => 'erro'], 500),
    ]);

    $this->from('/pt-br/donate/'.$donation->ulid)
        ->post('/pt-br/donate/'.$donation->ulid.'/check')
        ->assertRedirect('/pt-br/donate/'.$donation->ulid);

    expect($donation->refresh()->status)->toBe(DonationStatus::Pending)
        ->and($donation->gateway_status)->toBeNull();

    $this->get('/pt-br/donate/'.$donation->ulid)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->hasFlash('toast.type', 'error')
            ->hasFlash('toast.message', trans('donations.check.error', [], 'pt_BR'))
        );
});

it('nao confirma quando o valor do provedor diverge da doacao', function () {
    $donation = Donation::factory()->create([
        'gateway_ref' => 'pay_9',
        'amount_cents' => 2500,
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'api.mercadopago.com/v1/payments/pay_9' => Http::response(pagamentoParaConsulta($donation, [
            'transaction_amount' => 10.0,
        ])),
    ]);

    $this->from('/pt-br/donate/'.$donation->ulid)
        ->post('/pt-br/donate/'.$donation->ulid.'/check')
        ->assertRedirect('/pt-br/donate/'.$donation->ulid);

    expect($donation->refresh()->status)->toBe(DonationStatus::Pending);

    $this->get('/pt-br/donate/'.$donation->ulid)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->hasFlash('toast.type', 'error')
            ->hasFlash('toast.message', trans('donations.check.error', [], 'pt_BR'))
        );
});

it('nao sincroniza pagamento de outra doacao', function () {
    $donation = Donation::factory()->create([
        'gateway_ref' => 'pay_9',
        'amount_cents' => 2500,
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'api.mercadopago.com/v1/payments/pay_9' => Http::response(pagamentoParaConsulta($donation, [
            'external_reference' => 'don_01ARZ3NDEKTSV4RRFFQ69G5FAV',
        ])),
    ]);

    $this->from('/pt-br/donate/'.$donation->ulid)
        ->post('/pt-br/donate/'.$donation->ulid.'/check')
        ->assertRedirect('/pt-br/donate/'.$donation->ulid);

    expect($donation->refresh()->status)->toBe(DonationStatus::Pending)
        ->and($donation->gateway_status)->toBeNull();

    $this->get('/pt-br/donate/'.$donation->ulid)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->hasFlash('toast.type', 'error')
            ->hasFlash('toast.message', trans('donations.check.mismatch', [], 'pt_BR'))
        );
});

it('limita as consultas por IP', function () {
    config(['donations.check_per_minute' => 2]);

    $donation = Donation::factory()->create([
        'gateway_ref' => 'pay_9',
        'amount_cents' => 2500,
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'api.mercadopago.com/v1/payments/pay_9' => Http::response(pagamentoParaConsulta($donation, [
            'status' => 'pending',
            'status_detail' => 'pending_waiting_transfer',
            'date_approved' => null,
        ])),
    ]);

    $url = '/pt-br/donate/'.$donation->ulid.'/check';

    foreach ([1, 2] as $attempt) {
        $this->from('/pt-br/donate/'.$donation->ulid)
            ->post($url)
            ->assertRedirect('/pt-br/donate/'.$donation->ulid);
    }

    $this->from('/pt-br/donate/'.$donation->ulid)
        ->post($url)
        ->assertRedirect('/pt-br/donate/'.$donation->ulid);

    // A terceira consulta nao chega ao provedor: o limiter respondeu antes.
    Http::assertSentCount(2);

    $this->get('/pt-br/donate/'.$donation->ulid)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->hasFlash('toast.type', 'warning')
            ->hasFlash('toast.message', trans('donations.check.throttled', [], 'pt_BR'))
        );
});

it('nao aceita identificador fora do formato', function () {
    // A rota so casa com ULID; sem casar, o POST cai no fallback (GET) e leva 405.
    $this->post('/pt-br/donate/nao-e-ulid/check')->assertStatus(405);
});

it('responde 404 para ULID desconhecido', function () {
    $this->post('/pt-br/donate/01ARZ3NDEKTSV4RRFFQ69G5FAV/check')->assertNotFound();
});
