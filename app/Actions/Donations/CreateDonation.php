<?php

namespace App\Actions\Donations;

use App\Contracts\Payments\PaymentGateway;
use App\Enums\DonationStatus;
use App\Exceptions\Payments\DonationException;
use App\Exceptions\Payments\PaymentGatewayException;
use App\Models\Donation;
use App\Support\Payments\PixChargeRequest;
use App\Support\Payments\PixPayload;
use App\Support\Payments\PixQrCode;
use Illuminate\Support\Facades\Route;

/**
 * Cria a doacao e o QR do Pix (F6).
 *
 * Com credenciais do provedor, o Pix e dinamico: o QR e do proprio provedor e
 * o webhook confirma o pagamento sozinho. Sem credenciais, cai no Pix estatico
 * de `config/donations.php`, montado localmente - que nao tem confirmacao
 * automatica e a tela diz isso.
 *
 * A doacao nasce `pending`; nenhuma doacao vira `paid` aqui, exceto quando o
 * provedor ja devolve o Pix aprovado na criacao (raro, mas possivel).
 */
final class CreateDonation
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly ConfirmDonation $confirmDonation,
    ) {}

    /**
     * @param  array{amount_cents: int, name?: string|null, email?: string|null, user_id?: int|null}  $data
     */
    public function __invoke(array $data): Donation
    {
        $online = $this->gateway->isAvailable();

        if (! $online && ! PixPayload::isConfigured()) {
            throw DonationException::unavailable();
        }

        $donation = Donation::query()->create([
            'user_id' => $data['user_id'] ?? null,
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
            'locale' => app()->getLocale(),
            'amount_cents' => (int) $data['amount_cents'],
            'currency' => mb_strtoupper((string) config('donations.currency', 'BRL')),
            'status' => DonationStatus::Pending,
            'gateway' => $online ? 'mercadopago' : 'manual',
            'expires_at' => null,
        ]);

        return $online ? $this->dynamic($donation) : $this->static($donation);
    }

    private function dynamic(Donation $donation): Donation
    {
        $expiresAt = now()->addMinutes(max(5, (int) config('donations.expires_minutes', 60)));
        $payerEmail = $this->payerEmail($donation);

        if ($payerEmail === null) {
            $donation->forceFill(['status' => DonationStatus::Failed])->save();

            throw DonationException::payerMissing();
        }

        try {
            $charge = $this->gateway->createPixDonation(new PixChargeRequest(
                idempotencyKey: 'don_'.$donation->ulid,
                externalReference: 'don_'.$donation->ulid,
                description: (string) __('donations.pix_description', [
                    'app' => (string) config('app.name'),
                ]),
                amountCents: $donation->amount_cents,
                currency: $donation->currency,
                payerEmail: $payerEmail,
                notificationUrl: $this->webhookUrl(),
                expiresAt: $expiresAt,
            ));
        } catch (PaymentGatewayException $exception) {
            // Guarda o rastro da tentativa; a tela mostra um erro generico.
            $donation->forceFill(['status' => DonationStatus::Failed])->save();

            throw $exception;
        }

        $donation->forceFill([
            'gateway_ref' => $charge->id,
            'qr_code' => $charge->qrCode,
            'qr_code_base64' => $charge->qrCodeBase64,
            'ticket_url' => $charge->ticketUrl,
            'expires_at' => $charge->expiresAt ?? $expiresAt,
        ])->save();

        if ($charge->status === 'approved') {
            ($this->confirmDonation)(
                $donation,
                $donation->amount_cents,
                $donation->currency,
            );
        }

        return $donation;
    }

    private function static(Donation $donation): Donation
    {
        $payload = PixPayload::make(
            key: (string) config('donations.static_pix.key'),
            holder: (string) config('donations.static_pix.holder'),
            city: (string) config('donations.static_pix.city'),
            amountCents: $donation->amount_cents,
            txid: $donation->ulid,
        );

        $donation->forceFill([
            'gateway' => 'manual',
            'qr_code' => $payload,
            'qr_code_base64' => PixQrCode::dataUri($payload),
        ])->save();

        return $donation;
    }

    /**
     * O provedor exige e-mail do pagador; o doador anonimo usa o fallback.
     */
    private function payerEmail(Donation $donation): ?string
    {
        $email = $donation->email;

        if (is_string($email) && trim($email) !== '') {
            return trim($email);
        }

        $fallback = config('donations.payer_email');

        return is_string($fallback) && trim($fallback) !== '' ? trim($fallback) : null;
    }

    /**
     * URL publica do webhook: a configurada ou a rota do site.
     */
    private function webhookUrl(): ?string
    {
        $configured = config('payments.checkout.webhook_url');

        if (is_string($configured) && trim($configured) !== '') {
            return $configured;
        }

        return Route::has('webhooks.mercadopago')
            ? route('webhooks.mercadopago')
            : null;
    }
}
