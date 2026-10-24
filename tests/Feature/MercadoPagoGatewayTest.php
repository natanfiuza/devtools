<?php

use App\Contracts\Payments\PaymentGateway;
use App\Enums\PaymentMethod;
use App\Exceptions\Payments\PaymentGatewayException;
use App\Services\Payments\MercadoPagoGateway;
use App\Support\Payments\CheckoutRequest;
use App\Support\Payments\PixChargeRequest;
use App\Support\Payments\PreapprovalRequest;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'payments.mercadopago.access_token' => 'token-de-teste',
        'payments.mercadopago.base_url' => 'https://api.mercadopago.com',
    ]);
});

it('resolve o gateway pelo contrato', function () {
    expect(app(PaymentGateway::class))->toBeInstanceOf(MercadoPagoGateway::class)
        ->and(app(PaymentGateway::class)->isAvailable())->toBeTrue();
});

it('cria a preferencia com valor, referencia, retorno e idempotencia', function () {
    Http::fake([
        'api.mercadopago.com/checkout/preferences' => Http::response([
            'id' => 'pref_123',
            'init_point' => 'https://www.mercadopago.com.br/checkout/v1/redirect?pref_id=pref_123',
        ], 201),
    ]);

    $preferencia = app(PaymentGateway::class)->createPreference(new CheckoutRequest(
        idempotencyKey: 'chk_01ABC',
        externalReference: 'chk_01ABC',
        title: 'Pro mensal',
        amountCents: 890,
        currency: 'BRL',
        payerEmail: 'conta@exemplo.com',
        notificationUrl: 'https://app.test/webhooks/mercadopago',
        backUrl: 'https://app.test/pt_BR/subscribe/checkout/01ABC/return',
        expiresAt: now()->addHour(),
        statementDescriptor: 'DEVTOOLS',
    ));

    expect($preferencia->id)->toBe('pref_123')
        ->and($preferencia->initPoint)->toContain('pref_123');

    Http::assertSent(function (Request $request): bool {
        $body = $request->data();

        return $request->method() === 'POST'
            && $request->url() === 'https://api.mercadopago.com/checkout/preferences'
            && $request->hasHeader('X-Idempotency-Key', 'chk_01ABC')
            && $body['items'][0]['unit_price'] === 8.9
            && $body['items'][0]['currency_id'] === 'BRL'
            && $body['external_reference'] === 'chk_01ABC'
            && $body['payer']['email'] === 'conta@exemplo.com'
            && $body['notification_url'] === 'https://app.test/webhooks/mercadopago'
            && $body['back_urls']['success'] === 'https://app.test/pt_BR/subscribe/checkout/01ABC/return'
            && $body['auto_return'] === 'approved'
            && $body['expires'] === true
            && $body['statement_descriptor'] === 'DEVTOOLS';
    });
});

it('cria a preapproval mensal e anual sem plano associado', function (int $frequency, int $amountCents) {
    Http::fake([
        'api.mercadopago.com/preapproval' => Http::response([
            'id' => 'pre_123',
            'status' => 'pending',
            'init_point' => 'https://www.mercadopago.com.br/subscriptions/checkout?preapproval_id=pre_123',
            'external_reference' => 'chk_01REC',
            'auto_recurring' => ['transaction_amount' => $amountCents / 100, 'currency_id' => 'BRL'],
        ], 201),
    ]);

    $preapproval = app(PaymentGateway::class)->createPreapproval(new PreapprovalRequest(
        idempotencyKey: 'chk_01REC',
        externalReference: 'chk_01REC',
        reason: 'Pro',
        amountCents: $amountCents,
        currency: 'BRL',
        payerEmail: 'conta@exemplo.com',
        frequency: $frequency,
        frequencyType: 'months',
        notificationUrl: 'https://app.test/webhooks/mercadopago',
        backUrl: 'https://app.test/pt_BR/subscribe/checkout/01REC/return',
    ));

    expect($preapproval->id)->toBe('pre_123')
        ->and($preapproval->status)->toBe('pending')
        ->and($preapproval->amountCents)->toBe($amountCents)
        ->and($preapproval->externalReference)->toBe('chk_01REC');

    Http::assertSent(function (Request $request) use ($frequency, $amountCents): bool {
        $body = $request->data();

        return $request->url() === 'https://api.mercadopago.com/preapproval'
            && $body['auto_recurring']['frequency'] === $frequency
            && $body['auto_recurring']['frequency_type'] === 'months'
            && (int) round(((float) $body['auto_recurring']['transaction_amount']) * 100) === $amountCents
            && $body['payer_email'] === 'conta@exemplo.com'
            && ! isset($body['preapproval_plan_id']);
    });
})->with([
    'mensal' => [1, 890],
    'anual' => [12, 8900],
]);

it('cria a cobranca pix da doacao com qr e copia e cola', function () {
    Http::fake([
        'api.mercadopago.com/v1/payments' => Http::response([
            'id' => 555,
            'status' => 'pending',
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

    $cobranca = app(PaymentGateway::class)->createPixDonation(new PixChargeRequest(
        idempotencyKey: 'don_01ABC',
        externalReference: 'don_01ABC',
        description: 'Doacao',
        amountCents: 1000,
        currency: 'BRL',
        payerEmail: 'doador@exemplo.com',
        notificationUrl: 'https://app.test/webhooks/mercadopago',
        expiresAt: now()->addHour(),
    ));

    expect($cobranca->id)->toBe('555')
        ->and($cobranca->status)->toBe('pending')
        ->and($cobranca->qrCode)->toBe('00020126...6304ABCD')
        ->and($cobranca->qrCodeBase64)->toBe('iVBORw0KGgo=')
        ->and($cobranca->ticketUrl)->toContain('payment_id=555');

    Http::assertSent(function (Request $request): bool {
        $body = $request->data();

        return $request->url() === 'https://api.mercadopago.com/v1/payments'
            && $body['payment_method_id'] === 'pix'
            && (float) $body['transaction_amount'] === 10.0
            && $body['external_reference'] === 'don_01ABC'
            && $body['payer']['email'] === 'doador@exemplo.com';
    });
});

it('mapeia o pagamento aprovado do provedor', function () {
    Http::fake([
        'api.mercadopago.com/v1/payments/987' => Http::response([
            'id' => 987,
            'status' => 'approved',
            'status_detail' => 'accredited',
            'transaction_amount' => 29.9,
            'currency_id' => 'BRL',
            'external_reference' => 'chk_01ABC',
            'payment_type_id' => 'credit_card',
            'payment_method_id' => 'visa',
            'date_approved' => '2026-10-21T10:00:00.000-03:00',
        ]),
    ]);

    $pagamento = app(PaymentGateway::class)->payment('987');

    expect($pagamento->id)->toBe('987')
        ->and($pagamento->isApproved())->toBeTrue()
        ->and($pagamento->amountCents)->toBe(2990)
        ->and($pagamento->currency)->toBe('BRL')
        ->and($pagamento->externalReference)->toBe('chk_01ABC')
        ->and($pagamento->method)->toBe(PaymentMethod::CreditCard)
        ->and($pagamento->methodId)->toBe('visa')
        ->and($pagamento->paidAt)->not->toBeNull();
});

it('mapeia a cobranca da preapproval com o pagamento subjacente', function () {
    Http::fake([
        'api.mercadopago.com/authorized_payments/55' => Http::response([
            'id' => 55,
            'preapproval_id' => 'pre_123',
            'retry_attempt' => 1,
            'payment' => [
                'id' => 987,
                'status' => 'approved',
                'transaction_amount' => 8.9,
                'currency_id' => 'BRL',
            ],
        ]),
    ]);

    $cobranca = app(PaymentGateway::class)->authorizedPayment('55');

    expect($cobranca->id)->toBe('55')
        ->and($cobranca->preapprovalId)->toBe('pre_123')
        ->and($cobranca->paymentId)->toBe('987')
        ->and($cobranca->isApproved())->toBeTrue()
        ->and($cobranca->amountCents)->toBe(890)
        ->and($cobranca->retryAttempt)->toBe(1);
});

it('estorna e cancela a preapproval com chave de idempotencia', function () {
    Http::fake([
        'api.mercadopago.com/v1/payments/987/refunds' => Http::response(['id' => 1], 201),
        'api.mercadopago.com/preapproval/pre_123' => Http::response(['id' => 'pre_123', 'status' => 'canceled']),
    ]);

    $gateway = app(PaymentGateway::class);
    $gateway->refund('987');
    $gateway->cancelPreapproval('pre_123');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://api.mercadopago.com/v1/payments/987/refunds'
        && $request->hasHeader('X-Idempotency-Key', 'refund_987'));

    Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
        && $request->url() === 'https://api.mercadopago.com/preapproval/pre_123'
        && $request->data() === ['status' => 'canceled']);
});

it('separa falha transitoria de falha de contrato', function () {
    Http::fake([
        'api.mercadopago.com/v1/payments/500' => Http::response(['message' => 'erro'], 500),
        'api.mercadopago.com/v1/payments/404' => Http::response(['message' => 'nao encontrado'], 404),
    ]);

    $gateway = app(PaymentGateway::class);

    try {
        $gateway->payment('500');
        $this->fail('Esperava PaymentGatewayException.');
    } catch (PaymentGatewayException $exception) {
        expect($exception->retryable)->toBeTrue()
            ->and($exception->statusCode)->toBe(500)
            ->and($exception->getMessage())->not->toContain('erro');
    }

    try {
        $gateway->payment('404');
        $this->fail('Esperava PaymentGatewayException.');
    } catch (PaymentGatewayException $exception) {
        expect($exception->retryable)->toBeFalse()
            ->and($exception->isAlreadyGone())->toBeTrue();
    }
});

it('recusa chamadas sem credenciais', function () {
    config(['payments.mercadopago.access_token' => null]);

    $gateway = app(PaymentGateway::class);

    expect($gateway->isAvailable())->toBeFalse();

    expect(fn () => $gateway->payment('1'))
        ->toThrow(PaymentGatewayException::class, 'sem credenciais');
});

it('inclui os codigos estruturados de causa no contexto da excecao sem vazar na mensagem', function () {
    Http::fake([
        'api.mercadopago.com/v1/payments' => Http::response([
            'message' => 'Collector cannot be the same as payer',
            'error' => 'bad_request',
            'status' => 400,
            'cause' => [
                [
                    'code' => 2067,
                    'description' => 'Invalid payer email',
                ],
            ],
        ], 400),
    ]);

    try {
        app(PaymentGateway::class)->createPixDonation(new PixChargeRequest(
            idempotencyKey: 'don_01ERR',
            externalReference: 'don_01ERR',
            description: 'Doacao',
            amountCents: 1000,
            currency: 'BRL',
            payerEmail: 'mesmo-email@exemplo.com',
            notificationUrl: 'https://app.test/webhooks/mercadopago',
            expiresAt: now()->addHour(),
        ));
        $this->fail('Esperava PaymentGatewayException.');
    } catch (PaymentGatewayException $exception) {
        expect($exception->getMessage())->toBe('Payments: donation.pix respondeu HTTP 400.')
            ->and($exception->statusCode)->toBe(400)
            ->and($exception->gatewayError)->toBe('bad_request')
            ->and($exception->gatewayCauses)->toBe([
                ['code' => '2067', 'description' => 'Invalid payer email'],
            ])
            ->and($exception->context())->toMatchArray([
                'operation' => 'donation.pix',
                'status_code' => 400,
                'gateway_error' => 'bad_request',
            ]);
    }
});
