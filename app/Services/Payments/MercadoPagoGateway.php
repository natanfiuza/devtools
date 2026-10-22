<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentGateway;
use App\Exceptions\Payments\PaymentGatewayException;
use App\Support\Payments\CheckoutRequest;
use App\Support\Payments\GatewayAuthorizedPayment;
use App\Support\Payments\GatewayPayment;
use App\Support\Payments\GatewayPixCharge;
use App\Support\Payments\GatewayPreapproval;
use App\Support\Payments\GatewayPreference;
use App\Support\Payments\PaymentMethodMapper;
use App\Support\Payments\PixChargeRequest;
use App\Support\Payments\PreapprovalRequest;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use JsonException;

/**
 * Cliente HTTP fino da Mercado Pago (F6).
 *
 * Sem SDK, pelo mesmo motivo do provedor de IA (ADR-039): sao poucos endpoints
 * e o projeto nao ganha nada carregando o SDK inteiro. Nao ha log de corpo,
 * token ou resposta - a excecao carrega so operacao e status.
 *
 * O corpo da notificacao nunca e fonte de verdade: os `get` deste cliente sao
 * o que as actions usam para decidir qualquer coisa.
 */
final class MercadoPagoGateway implements PaymentGateway
{
    public function isAvailable(): bool
    {
        return $this->token() !== '';
    }

    public function createPreference(CheckoutRequest $request): GatewayPreference
    {
        $payload = [
            'items' => [[
                'id' => $request->externalReference,
                'title' => $request->title,
                'quantity' => 1,
                'currency_id' => $request->currency,
                'unit_price' => $this->decimal($request->amountCents),
            ]],
            'external_reference' => $request->externalReference,
        ];

        if ($request->payerEmail !== null) {
            $payload['payer'] = ['email' => $request->payerEmail];
        }

        if ($request->notificationUrl !== null) {
            $payload['notification_url'] = $request->notificationUrl;
        }

        if ($request->backUrl !== null) {
            $payload['back_urls'] = [
                'success' => $request->backUrl,
                'failure' => $request->backUrl,
                'pending' => $request->backUrl,
            ];
            $payload['auto_return'] = 'approved';
        }

        if ($request->expiresAt !== null) {
            $payload['expires'] = true;
            $payload['expiration_date_from'] = now()->toIso8601String();
            $payload['expiration_date_to'] = $request->expiresAt->toIso8601String();
        }

        if ($request->statementDescriptor !== null && $request->statementDescriptor !== '') {
            $payload['statement_descriptor'] = mb_substr($request->statementDescriptor, 0, 22);
        }

        $data = $this->send('checkout.preference', 'POST', '/checkout/preferences', $payload, $request->idempotencyKey);

        $id = $this->text($data['id'] ?? null);

        if ($id === null) {
            throw PaymentGatewayException::invalidResponse('checkout.preference');
        }

        return new GatewayPreference($id, $this->text($data['init_point'] ?? null));
    }

    public function createPreapproval(PreapprovalRequest $request): GatewayPreapproval
    {
        $payload = [
            'reason' => mb_substr($request->reason, 0, 255),
            'external_reference' => $request->externalReference,
            'payer_email' => $request->payerEmail,
            'auto_recurring' => [
                'frequency' => $request->frequency,
                'frequency_type' => $request->frequencyType,
                'transaction_amount' => $this->decimal($request->amountCents),
                'currency_id' => $request->currency,
            ],
        ];

        if ($request->backUrl !== null) {
            $payload['back_url'] = $request->backUrl;
        }

        if ($request->notificationUrl !== null) {
            $payload['notification_url'] = $request->notificationUrl;
        }

        $data = $this->send('checkout.preapproval', 'POST', '/preapproval', $payload, $request->idempotencyKey);

        return $this->preapprovalFromArray($data, 'checkout.preapproval');
    }

    public function createPixDonation(PixChargeRequest $request): GatewayPixCharge
    {
        $payload = [
            'transaction_amount' => $this->decimal($request->amountCents),
            'description' => mb_substr($request->description, 0, 255),
            'payment_method_id' => 'pix',
            'external_reference' => $request->externalReference,
            'payer' => ['email' => $request->payerEmail],
        ];

        if ($request->notificationUrl !== null) {
            $payload['notification_url'] = $request->notificationUrl;
        }

        if ($request->expiresAt !== null) {
            $payload['date_of_expiration'] = $request->expiresAt->toIso8601String();
        }

        $data = $this->send('donation.pix', 'POST', '/v1/payments', $payload, $request->idempotencyKey);

        $id = $this->text($data['id'] ?? null);

        if ($id === null) {
            throw PaymentGatewayException::invalidResponse('donation.pix');
        }

        $transaction = $data['point_of_interaction']['transaction_data'] ?? null;
        $transaction = is_array($transaction) ? $transaction : [];

        return new GatewayPixCharge(
            $id,
            $this->text($data['status'] ?? null) ?? 'pending',
            $this->text($transaction['qr_code'] ?? null),
            $this->text($transaction['qr_code_base64'] ?? null),
            $this->text($transaction['ticket_url'] ?? null),
            $this->date($data['date_of_expiration'] ?? null),
        );
    }

    public function payment(string $id): GatewayPayment
    {
        $data = $this->send('payment', 'GET', '/v1/payments/'.rawurlencode($id));

        $paymentId = $this->text($data['id'] ?? null);

        if ($paymentId === null) {
            throw PaymentGatewayException::invalidResponse('payment');
        }

        return new GatewayPayment(
            $paymentId,
            $this->text($data['status'] ?? null) ?? 'unknown',
            $this->text($data['status_detail'] ?? null),
            $this->cents($data['transaction_amount'] ?? null),
            $this->text($data['currency_id'] ?? null) ?? 'BRL',
            $this->text($data['external_reference'] ?? null),
            PaymentMethodMapper::fromGateway(
                $this->text($data['payment_type_id'] ?? null),
                $this->text($data['payment_method_id'] ?? null),
            ),
            $this->text($data['payment_method_id'] ?? null),
            $this->date($data['date_approved'] ?? null),
        );
    }

    public function preapproval(string $id): GatewayPreapproval
    {
        $data = $this->send('preapproval', 'GET', '/preapproval/'.rawurlencode($id));

        return $this->preapprovalFromArray($data, 'preapproval');
    }

    public function authorizedPayment(string $id): GatewayAuthorizedPayment
    {
        $data = $this->send('authorized_payment', 'GET', '/authorized_payments/'.rawurlencode($id));

        $payment = $data['payment'] ?? null;
        $payment = is_array($payment) ? $payment : [];

        $authorizedId = $this->text($data['id'] ?? null);
        $preapprovalId = $this->text($data['preapproval_id'] ?? null);

        if ($authorizedId === null || $preapprovalId === null) {
            throw PaymentGatewayException::invalidResponse('authorized_payment');
        }

        $status = $this->text($payment['status'] ?? null)
            ?? $this->text($data['status'] ?? null)
            ?? 'unknown';

        return new GatewayAuthorizedPayment(
            $authorizedId,
            $status,
            $preapprovalId,
            $this->text($payment['id'] ?? null) ?? $this->text($data['payment_id'] ?? null),
            $this->cents($payment['transaction_amount'] ?? $data['transaction_amount'] ?? null),
            $this->text($payment['currency_id'] ?? $data['currency_id'] ?? null) ?? 'BRL',
            (int) ($data['retry_attempt'] ?? 0),
        );
    }

    public function refund(string $paymentId): void
    {
        $this->send(
            'refund',
            'POST',
            '/v1/payments/'.rawurlencode($paymentId).'/refunds',
            [],
            'refund_'.$paymentId,
        );
    }

    public function cancelPreapproval(string $preapprovalId): void
    {
        $this->send(
            'preapproval.cancel',
            'PUT',
            '/preapproval/'.rawurlencode($preapprovalId),
            ['status' => 'canceled'],
            'cancel_'.$preapprovalId,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function preapprovalFromArray(array $data, string $operation): GatewayPreapproval
    {
        $id = $this->text($data['id'] ?? null);

        if ($id === null) {
            throw PaymentGatewayException::invalidResponse($operation);
        }

        $recurring = $data['auto_recurring'] ?? null;
        $recurring = is_array($recurring) ? $recurring : [];

        return new GatewayPreapproval(
            $id,
            $this->text($data['status'] ?? null) ?? 'pending',
            $this->text($data['init_point'] ?? null),
            isset($recurring['transaction_amount']) ? $this->cents($recurring['transaction_amount']) : null,
            $this->text($recurring['currency_id'] ?? null),
            $this->text($data['external_reference'] ?? null),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function send(
        string $operation,
        string $method,
        string $path,
        array $payload = [],
        ?string $idempotencyKey = null,
    ): array {
        $token = $this->token();

        if ($token === '') {
            throw PaymentGatewayException::unavailable($operation);
        }

        $url = $this->baseUrl().$path;

        try {
            $response = $this->client($token, $idempotencyKey)
                ->send($method, $url, $payload === [] ? [] : ['json' => $payload]);
        } catch (ConnectionException) {
            throw PaymentGatewayException::connection($operation);
        }

        if (! $response->successful()) {
            throw PaymentGatewayException::fromStatus($operation, $response->status());
        }

        if (trim($response->body()) === '') {
            return [];
        }

        try {
            $data = json_decode($response->body(), true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw PaymentGatewayException::invalidResponse($operation);
        }

        if (! is_array($data)) {
            throw PaymentGatewayException::invalidResponse($operation);
        }

        return $data;
    }

    private function client(string $token, ?string $idempotencyKey): PendingRequest
    {
        $client = Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->connectTimeout(max(1, (int) config('payments.mercadopago.connect_timeout', 5)))
            ->timeout(max(1, (int) config('payments.mercadopago.timeout', 20)));

        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $client = $client->withHeaders(['X-Idempotency-Key' => $idempotencyKey]);
        }

        return $client;
    }

    private function token(): string
    {
        return trim((string) config('payments.mercadopago.access_token'));
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('payments.mercadopago.base_url', 'https://api.mercadopago.com'), '/');
    }

    private function decimal(int $cents): float
    {
        return round($cents / 100, 2);
    }

    private function cents(mixed $value): int
    {
        return is_numeric($value) ? (int) round(((float) $value) * 100) : 0;
    }

    private function text(mixed $value): ?string
    {
        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : $value;
        }

        return is_int($value) ? (string) $value : null;
    }

    private function date(mixed $value): ?CarbonInterface
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Date::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
