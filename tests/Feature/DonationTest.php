<?php

use App\Actions\Donations\ConfirmDonation;
use App\Actions\Payments\HandleWebhookEvent;
use App\Enums\DonationStatus;
use App\Enums\WebhookEventStatus;
use App\Exceptions\Payments\PaymentVerificationException;
use App\Models\Donation;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Notifications\DonationReceipt;
use App\Support\Locales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
| Doacao por Pix (F6).
|
| A pagina publica nao exige conta e o doador pode ser anonimo. Com o provedor
| configurado o Pix e dinamico e o webhook confirma; sem ele, o QR estatico de
| `config/donations.php` funciona sem confirmacao automatica. A pagina de
| status e publica por link, mas nao mostra nome nem e-mail e pede `noindex`.
*/

beforeEach(function () {
    config([
        'payments.mercadopago.access_token' => null,
        'payments.mercadopago.base_url' => 'https://api.mercadopago.com',
        'donations.static_pix' => [
            'key' => 'chave-pix@exemplo.com',
            'holder' => 'Devtools',
            'city' => 'Recife',
        ],
        'donations.payer_email' => 'recibo@exemplo.com',
        'donations.send_receipts' => true,
        'donations.min_cents' => 100,
        'donations.max_cents' => 100000,
        'donations.suggested' => [500, 1000, 2500, 5000],
    ]);
});

/**
 * Payload de um pagamento Pix da doacao, como o provedor devolve.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function pagamentoDaDoacao(Donation $donation, array $overrides = []): array
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

it('atende a pagina de doacao nos cinco idiomas', function () {
    foreach (Locales::all() as $code => $locale) {
        $this->get('/'.$locale['slug'].'/donate')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Donate')
                ->where('locale', $code)
                ->where('seo.noindex', false)
                ->where('online', false)
                ->where('staticPix', true)
                ->where('minCents', 100)
                ->where('maxCents', 100000)
                ->has('suggested', 4)
                ->where('suggested.0.cents', 500)
                ->where('suggested.3.cents', 5000)
            );
    }
});

it('lista so os valores sugeridos dentro do intervalo', function () {
    config(['donations.suggested' => [50, 500, 100000, 200000]]);

    $this->get('/pt-br/donate')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('suggested', 2)
            ->where('suggested.0.cents', 500)
            ->where('suggested.1.cents', 100000)
        );
});

it('deixa a pagina em modo informativo sem gateway nem pix estatico', function () {
    config(['donations.static_pix' => ['key' => null, 'holder' => null, 'city' => null]]);

    $this->get('/pt-br/donate')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Donate')
            ->where('online', false)
            ->where('staticPix', false)
        );
});

it('preenche nome e e-mail da conta logada', function () {
    $user = User::factory()->create([
        'name' => 'Doadora',
        'email' => 'doadora@exemplo.com',
    ]);

    $this->actingAs($user)
        ->get('/pt-br/donate')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('name', 'Doadora')
            ->where('email', 'doadora@exemplo.com')
        );
});

it('recusa valor fora do intervalo', function (int $cents) {
    $this->from('/pt-br/donate')
        ->post('/pt-br/donate', ['amount_cents' => $cents])
        ->assertRedirect('/pt-br/donate')
        ->assertSessionHasErrors('amount_cents');

    expect(Donation::query()->count())->toBe(0);
})->with([
    'abaixo do minimo' => [50],
    'acima do maximo' => [100001],
]);

it('gera o pix estatico e manda para a pagina de status', function () {
    $response = $this->post('/pt-br/donate', [
        'amount_cents' => 2500,
        'name' => 'Doador Teste',
        'email' => 'doador@exemplo.com',
    ]);

    $donation = Donation::query()->sole();

    $response->assertRedirect(route('donate.status', [
        'locale' => 'pt-br',
        'donation' => $donation->ulid,
    ]));

    expect($donation->status)->toBe(DonationStatus::Pending)
        ->and($donation->gateway)->toBe('manual')
        ->and($donation->amount_cents)->toBe(2500)
        ->and($donation->currency)->toBe('BRL')
        ->and($donation->locale)->toBe('pt_BR')
        ->and($donation->user_id)->toBeNull()
        ->and($donation->qr_code)->toStartWith('000201')
        ->and($donation->qr_code)->toContain('br.gov.bcb.pix')
        ->and($donation->qr_code_base64)->toStartWith('data:image/svg+xml;base64,');
});

it('mostra o qr estatico na pagina de status sem dados do doador', function () {
    $this->post('/pt-br/donate', [
        'amount_cents' => 2500,
        'name' => 'Doador Teste',
        'email' => 'doador@exemplo.com',
    ]);

    $donation = Donation::query()->sole();

    $html = $this->get('/pt-br/donate/'.$donation->ulid)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('DonationStatus')
            ->where('seo.noindex', true)
            ->where('donation.ulid', $donation->ulid)
            ->where('donation.status', 'pending')
            ->where('donation.static', true)
            ->where('donation.qrCode', $donation->qr_code)
            ->where('donation.qrImage', fn ($value): bool => is_string($value)
                && str_starts_with($value, 'data:image/svg+xml;base64,'))
        )
        ->getContent();

    expect($html)
        ->toContain('<meta name="robots" content="noindex, follow">')
        ->not->toContain('Doador Teste')
        ->not->toContain('doador@exemplo.com');
});

it('esconde o qr de uma doacao ja paga', function () {
    $donation = Donation::factory()->paid()->create();

    $this->get('/pt-br/donate/'.$donation->ulid)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('donation.status', 'paid')
            ->where('donation.qrCode', null)
            ->where('donation.qrImage', null)
            ->where('donation.ticketUrl', null)
        );
});

it('vence o pix dinamico na leitura, mas nao o estatico', function () {
    $dinamico = Donation::factory()->create(['expires_at' => now()->subMinute()]);
    $estatico = Donation::factory()->staticPix()->create(['expires_at' => now()->subMinute()]);

    $this->get('/pt-br/donate/'.$dinamico->ulid)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('donation.status', 'expired')
            ->where('donation.qrCode', null)
        );

    $this->get('/pt-br/donate/'.$estatico->ulid)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('donation.status', 'pending')
            ->where('donation.qrCode', $estatico->qr_code)
        );
});

it('cria o pix dinamico e manda para o status da doacao', function () {
    config(['payments.mercadopago.access_token' => 'token-de-teste']);

    Http::fake([
        'api.mercadopago.com/v1/payments' => Http::response([
            'id' => 555,
            'status' => 'pending',
            // Relativo ao relogio do teste: uma data fixa vence com o tempo e
            // a doacao nasce expirada, escondendo o QR que o teste confere.
            'date_of_expiration' => now()->addHour()->toIso8601String(),
            'point_of_interaction' => [
                'transaction_data' => [
                    'qr_code' => '00020126...6304ABCD',
                    'qr_code_base64' => 'iVBORw0KGgo=',
                    'ticket_url' => 'https://www.mercadopago.com.br/sandbox/pix?payment_id=555',
                ],
            ],
        ], 201),
    ]);

    $response = $this->post('/pt-br/donate', [
        'amount_cents' => 2500,
        'email' => 'doador@exemplo.com',
    ]);

    $donation = Donation::query()->sole();

    $response->assertRedirect(route('donate.status', [
        'locale' => 'pt-br',
        'donation' => $donation->ulid,
    ]));

    expect($donation->gateway)->toBe('mercadopago')
        ->and($donation->gateway_ref)->toBe('555')
        ->and($donation->qr_code)->toBe('00020126...6304ABCD')
        ->and($donation->qr_code_base64)->toBe('iVBORw0KGgo=')
        ->and($donation->ticket_url)->toContain('payment_id=555')
        ->and($donation->expires_at)->not->toBeNull();

    Http::assertSent(function (Request $request) use ($donation): bool {
        $body = $request->data();

        return $request->url() === 'https://api.mercadopago.com/v1/payments'
            && $request->hasHeader('X-Idempotency-Key', 'don_'.$donation->ulid)
            && $body['payment_method_id'] === 'pix'
            && (float) $body['transaction_amount'] === 25.0
            && $body['external_reference'] === 'don_'.$donation->ulid
            && $body['payer']['email'] === 'doador@exemplo.com'
            && $body['notification_url'] === route('webhooks.mercadopago');
    });

    $this->get('/pt-br/donate/'.$donation->ulid)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('donation.static', false)
            ->where('donation.qrImage', fn ($value): bool => is_string($value)
                && str_starts_with($value, 'data:image/png;base64,'))
        );
});

it('usa o e-mail de fallback quando o doador e anonimo', function () {
    config(['payments.mercadopago.access_token' => 'token-de-teste']);

    Http::fake([
        'api.mercadopago.com/v1/payments' => Http::response([
            'id' => 556,
            'status' => 'pending',
            'point_of_interaction' => [
                'transaction_data' => ['qr_code' => '000201...', 'qr_code_base64' => 'abc'],
            ],
        ], 201),
    ]);

    $this->post('/pt-br/donate', ['amount_cents' => 1000]);

    Http::assertSent(fn (Request $request): bool => $request->data()['payer']['email'] === 'recibo@exemplo.com');
});

it('marca a doacao como falha quando nem o doador nem o fallback tem e-mail', function () {
    config([
        'payments.mercadopago.access_token' => 'token-de-teste',
        'donations.payer_email' => null,
    ]);

    Http::fake();

    $this->from('/pt-br/donate')
        ->post('/pt-br/donate', ['amount_cents' => 1000])
        ->assertRedirect('/pt-br/donate');

    expect(Donation::query()->sole()->status)->toBe(DonationStatus::Failed);

    Http::assertNothingSent();

    $this->get('/pt-br/donate')->assertInertia(fn (Assert $page) => $page
        ->hasFlash('toast.type', 'error')
        ->hasFlash('toast.message', trans('donations.errors.payer', [], 'pt_BR'))
    );
});

it('recusa a doacao quando nao ha gateway nem pix estatico', function () {
    config(['donations.static_pix' => ['key' => null, 'holder' => null, 'city' => null]]);

    $this->from('/pt-br/donate')
        ->post('/pt-br/donate', ['amount_cents' => 1000])
        ->assertRedirect('/pt-br/donate');

    expect(Donation::query()->count())->toBe(0);

    $this->get('/pt-br/donate')->assertInertia(fn (Assert $page) => $page
        ->hasFlash('toast.type', 'error')
        ->hasFlash('toast.message', trans('donations.errors.unavailable', [], 'pt_BR'))
    );
});

it('limita a criacao de doacoes por minuto', function () {
    // O throttle roda antes da validacao: dez POSTs invalidos gastam a cota.
    for ($attempt = 0; $attempt < 10; $attempt++) {
        $this->post('/pt-br/donate', ['amount_cents' => 50])
            ->assertSessionHasErrors('amount_cents');
    }

    $this->post('/pt-br/donate', ['amount_cents' => 50])->assertStatus(429);
});

it('confirma a doacao uma vez e envia o recibo uma vez', function () {
    Notification::fake();

    $donation = Donation::factory()->create([
        'email' => 'doador@exemplo.com',
        'amount_cents' => 2500,
    ]);

    $confirm = app(ConfirmDonation::class);

    expect($confirm($donation, 2500, 'BRL'))->toBeTrue()
        ->and($confirm($donation, 2500, 'brl'))->toBeFalse()
        ->and($donation->status)->toBe(DonationStatus::Paid)
        ->and($donation->paid_at)->not->toBeNull();

    Notification::assertSentTimes(DonationReceipt::class, 1);
});

it('nao manda recibo para doacao anonima nem com o envio desligado', function (bool $sendReceipts, ?string $email) {
    Notification::fake();

    config(['donations.send_receipts' => $sendReceipts]);

    $donation = Donation::factory()->create([
        'email' => $email,
        'amount_cents' => 1000,
    ]);

    app(ConfirmDonation::class)($donation, 1000, 'BRL');

    Notification::assertNothingSent();

    expect($donation->fresh()->status)->toBe(DonationStatus::Paid);
})->with([
    'anonima' => [true, null],
    'envio desligado' => [false, 'doador@exemplo.com'],
]);

it('recusa confirmar com valor divergente', function () {
    $donation = Donation::factory()->create(['amount_cents' => 2500]);

    expect(fn () => app(ConfirmDonation::class)($donation, 2400, 'BRL'))
        ->toThrow(PaymentVerificationException::class);

    expect($donation->refresh()->status)->toBe(DonationStatus::Pending);
});

it('confirma a doacao aprovada pelo webhook', function () {
    config(['payments.mercadopago.access_token' => 'token-de-teste']);

    $donation = Donation::factory()->create([
        'gateway_ref' => 'pay_9',
        'amount_cents' => 2500,
    ]);

    Http::fake([
        'api.mercadopago.com/v1/payments/pay_9' => Http::response(pagamentoDaDoacao($donation)),
    ]);

    $event = WebhookEvent::factory()->create([
        'topic' => 'payment',
        'resource_id' => 'pay_9',
    ]);

    expect(app(HandleWebhookEvent::class)($event))->toBe(WebhookEventStatus::Processed);
    expect($donation->refresh()->status)->toBe(DonationStatus::Paid)
        ->and($donation->paid_at)->not->toBeNull();
});

it('encontra a doacao pelo id do pagamento quando falta a referencia', function () {
    config(['payments.mercadopago.access_token' => 'token-de-teste']);

    $donation = Donation::factory()->create([
        'gateway_ref' => 'pay_9',
        'amount_cents' => 1000,
    ]);

    Http::fake([
        'api.mercadopago.com/v1/payments/pay_9' => Http::response(
            pagamentoDaDoacao($donation, ['external_reference' => null]),
        ),
    ]);

    $event = WebhookEvent::factory()->create([
        'topic' => 'payment',
        'resource_id' => 'pay_9',
    ]);

    expect(app(HandleWebhookEvent::class)($event))->toBe(WebhookEventStatus::Processed);
    expect($donation->refresh()->status)->toBe(DonationStatus::Paid);
});

it('nao confirma nem manda recibo duas vezes pelo mesmo pagamento', function () {
    Notification::fake();

    config(['payments.mercadopago.access_token' => 'token-de-teste']);

    $donation = Donation::factory()->create([
        'gateway_ref' => 'pay_9',
        'amount_cents' => 2500,
        'email' => 'doador@exemplo.com',
    ]);

    Http::fake([
        'api.mercadopago.com/v1/payments/pay_9' => Http::response(pagamentoDaDoacao($donation)),
    ]);

    $event = WebhookEvent::factory()->create([
        'topic' => 'payment',
        'resource_id' => 'pay_9',
    ]);

    $handle = app(HandleWebhookEvent::class);

    expect($handle($event))->toBe(WebhookEventStatus::Processed)
        ->and($handle($event))->toBe(WebhookEventStatus::Ignored);

    Notification::assertSentTimes(DonationReceipt::class, 1);
});

it('recusa valor divergente no webhook sem confirmar', function () {
    config(['payments.mercadopago.access_token' => 'token-de-teste']);

    $donation = Donation::factory()->create([
        'gateway_ref' => 'pay_9',
        'amount_cents' => 2500,
    ]);

    Http::fake([
        'api.mercadopago.com/v1/payments/pay_9' => Http::response(
            pagamentoDaDoacao($donation, ['transaction_amount' => 10.0]),
        ),
    ]);

    $event = WebhookEvent::factory()->create([
        'topic' => 'payment',
        'resource_id' => 'pay_9',
    ]);

    expect(fn () => app(HandleWebhookEvent::class)($event))
        ->toThrow(PaymentVerificationException::class);

    expect($donation->refresh()->status)->toBe(DonationStatus::Pending);
});

it('marca a doacao como devolvida no estorno do provedor', function () {
    config(['payments.mercadopago.access_token' => 'token-de-teste']);

    $donation = Donation::factory()->paid()->create([
        'gateway_ref' => 'pay_9',
        'amount_cents' => 2500,
    ]);

    Http::fake([
        'api.mercadopago.com/v1/payments/pay_9' => Http::response(
            pagamentoDaDoacao($donation, ['status' => 'refunded']),
        ),
    ]);

    $event = WebhookEvent::factory()->create([
        'topic' => 'payment',
        'resource_id' => 'pay_9',
    ]);

    expect(app(HandleWebhookEvent::class)($event))->toBe(WebhookEventStatus::Processed);
    expect($donation->refresh()->status)->toBe(DonationStatus::Refunded);
});

it('marca a doacao como falha quando o pix e recusado', function () {
    config(['payments.mercadopago.access_token' => 'token-de-teste']);

    $donation = Donation::factory()->create([
        'gateway_ref' => 'pay_9',
        'amount_cents' => 2500,
    ]);

    Http::fake([
        'api.mercadopago.com/v1/payments/pay_9' => Http::response(
            pagamentoDaDoacao($donation, ['status' => 'rejected']),
        ),
    ]);

    $event = WebhookEvent::factory()->create([
        'topic' => 'payment',
        'resource_id' => 'pay_9',
    ]);

    expect(app(HandleWebhookEvent::class)($event))->toBe(WebhookEventStatus::Processed);
    expect($donation->refresh()->status)->toBe(DonationStatus::Failed);
});

it('nao altera a doacao com pagamento ainda pendente', function () {
    config(['payments.mercadopago.access_token' => 'token-de-teste']);

    $donation = Donation::factory()->create([
        'gateway_ref' => 'pay_9',
        'amount_cents' => 2500,
    ]);

    Http::fake([
        'api.mercadopago.com/v1/payments/pay_9' => Http::response(
            pagamentoDaDoacao($donation, ['status' => 'pending']),
        ),
    ]);

    $event = WebhookEvent::factory()->create([
        'topic' => 'payment',
        'resource_id' => 'pay_9',
    ]);

    expect(app(HandleWebhookEvent::class)($event))->toBe(WebhookEventStatus::Ignored);
    expect($donation->refresh()->status)->toBe(DonationStatus::Pending);
});
