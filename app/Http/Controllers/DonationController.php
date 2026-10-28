<?php

namespace App\Http\Controllers;

use App\Actions\Donations\CreateDonation;
use App\Actions\Donations\SyncDonationPayment;
use App\Contracts\Payments\PaymentGateway;
use App\Enums\DonationFailureReason;
use App\Enums\DonationStatus;
use App\Enums\DonationSyncOutcome;
use App\Exceptions\Payments\DonationException;
use App\Exceptions\Payments\PaymentGatewayException;
use App\Exceptions\Payments\PaymentVerificationException;
use App\Http\Requests\StoreDonationRequest;
use App\Models\Donation;
use App\Models\User;
use App\Support\Format;
use App\Support\Payments\PixPayload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Doacao por Pix (`/{locale}/donate`, F6).
 *
 * A pagina e publica: doar nao exige conta e o doador pode ficar anonimo. O
 * POST cria a doacao e manda para a pagina de status daquela doacao (ULID),
 * que e publica por link mas nao mostra nome nem e-mail.
 *
 * Com credenciais do provedor o Pix e dinamico e o webhook confirma; sem elas,
 * o fallback estatico mostra QR e copia-e-cola sem confirmacao automatica. Sem
 * nenhum dos dois, a pagina fica em modo informativo.
 */
class DonationController extends Controller
{
    public function __construct(private readonly PaymentGateway $gateway) {}

    public function index(Request $request): Response
    {
        $currency = mb_strtoupper((string) config('donations.currency', 'BRL'));
        $min = (int) config('donations.min_cents');
        $max = (int) config('donations.max_cents');
        $online = $this->gateway->isAvailable();

        $suggested = [];

        foreach ((array) config('donations.suggested', []) as $cents) {
            $cents = (int) $cents;

            if ($cents < $min || $cents > $max) {
                continue;
            }

            $suggested[] = [
                'cents' => $cents,
                'formatted' => Format::money($cents, $currency),
            ];
        }

        $user = $request->user();

        return Inertia::render('Donate', [
            'suggested' => $suggested,
            'minCents' => $min,
            'maxCents' => $max,
            'min' => Format::money($min, $currency),
            'max' => Format::money($max, $currency),
            'currency' => $currency,
            'online' => $online,
            'staticPix' => ! $online && PixPayload::isConfigured(),
            'name' => $user instanceof User ? $user->name : null,
            'email' => $user instanceof User ? $user->email : null,
        ]);
    }

    public function store(StoreDonationRequest $request, CreateDonation $createDonation): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        // O array e montado chave a chave (e nao espalhado) porque `validated()`
        // devolve `mixed`: aqui os valores entram no formato que a action
        // declara, sem depender de o cliente ter mandado o tipo certo.
        $name = $validated['name'] ?? null;
        $email = $validated['email'] ?? null;

        try {
            $donation = $createDonation([
                'amount_cents' => (int) $validated['amount_cents'],
                'name' => is_string($name) ? $name : null,
                'email' => is_string($email) ? $email : null,
                'user_id' => $user instanceof User ? $user->id : null,
            ]);
        } catch (DonationException $exception) {
            return $this->refuse($exception->messageKey);
        } catch (PaymentGatewayException $exception) {
            // A excecao nao carrega corpo nem token, entao pode ir para o log.
            report($exception);

            return $this->refuse('donations.errors.gateway');
        }

        return redirect()->route('donate.status', ['donation' => $donation->ulid]);
    }

    public function status(Request $request): Response
    {
        // A busca e pelo ULID da URL, e nao por binding implicito: com
        // `Donation $donation` na assinatura, o Laravel preencheria o parametro
        // pela posicao e o modelo receberia o `{locale}` (mesmo motivo do
        // ToolController).
        $donation = Donation::query()
            ->where('ulid', (string) $request->route('donation'))
            ->firstOrFail();

        $status = $donation->effectiveStatus();
        $pending = $status === DonationStatus::Pending;

        return Inertia::render('DonationStatus', [
            // O auto-refresh da pagina so relê o banco; quem fala com o
            // provedor e o botao "Ja paguei" (ADR-059).
            'pollMs' => max(0, (int) config('donations.status_poll_ms', 15000)),
            'donation' => [
                'ulid' => $donation->ulid,
                'status' => $status->value,
                'amount' => Format::money($donation->amount_cents, $donation->currency),
                'createdAt' => Format::dateTime($donation->created_at),
                'paidAt' => Format::dateTime($donation->paid_at),
                'expiresAt' => Format::dateTime($donation->expires_at),
                // O QR so existe enquanto a doacao vale: depois de paga ou
                // vencida ele nao serve mais para nada.
                'qrCode' => $pending ? $donation->qr_code : null,
                'qrImage' => $pending ? $this->qrImage($donation->qr_code_base64) : null,
                'ticketUrl' => $pending ? $donation->ticket_url : null,
                'static' => $donation->gateway === 'manual',
                'canCheck' => $this->canCheck($donation, $status),
                // Motivo traduzido; o `status_detail` cru continua so no banco.
                'failureReason' => $status === DonationStatus::Failed
                    ? __(DonationFailureReason::fromStatusDetail($donation->gateway_status_detail)->translationKey())
                    : null,
            ],
        ]);
    }

    /**
     * Consulta o provedor sob demanda ("Ja paguei", ADR-059).
     *
     * O efeito fica no `SyncDonationPayment`, o mesmo do webhook: a pagina so
     * descobre mais cedo o que o provedor ja sabe. Doacao resolvida nao gasta
     * consulta, e uma referencia divergente nao sincroniza nada.
     */
    public function check(Request $request, SyncDonationPayment $sync): RedirectResponse
    {
        $donation = Donation::query()
            ->where('ulid', (string) $request->route('donation'))
            ->firstOrFail();

        $status = $donation->effectiveStatus();

        if (! in_array($status, [DonationStatus::Pending, DonationStatus::Expired], true)) {
            return $this->redirectToStatus($donation, 'info', 'donations.check.already');
        }

        if (! $this->canCheck($donation, $status)) {
            return $this->redirectToStatus($donation, 'warning', 'donations.check.unavailable');
        }

        try {
            $payment = $this->gateway->payment((string) $donation->gateway_ref);
        } catch (PaymentGatewayException $exception) {
            report($exception);

            return $this->redirectToStatus($donation, 'error', 'donations.check.error');
        }

        if ($payment->externalReference !== 'don_'.$donation->ulid) {
            // O id gravado aponta para outro pagamento: nao mexe no estado.
            return $this->redirectToStatus($donation, 'error', 'donations.check.mismatch');
        }

        try {
            $outcome = $sync($donation, $payment);
        } catch (PaymentVerificationException $exception) {
            report($exception);

            return $this->redirectToStatus($donation, 'error', 'donations.check.error');
        }

        [$type, $messageKey] = match ($outcome) {
            DonationSyncOutcome::Confirmed => ['success', 'donations.check.success'],
            DonationSyncOutcome::Declined => ['warning', 'donations.check.failed'],
            DonationSyncOutcome::Refunded => ['info', 'donations.check.refunded'],
            DonationSyncOutcome::Unchanged => ['info', 'donations.check.pending'],
        };

        return $this->redirectToStatus($donation, $type, $messageKey);
    }

    /**
     * Só a doacao dinamica ainda aberta tem o que consultar: Pix estatico nao
     * tem cobranca no provedor e doacao paga/estornada/falha ja e terminal.
     */
    private function canCheck(Donation $donation, DonationStatus $status): bool
    {
        return in_array($status, [DonationStatus::Pending, DonationStatus::Expired], true)
            && $donation->gateway === 'mercadopago'
            && is_string($donation->gateway_ref)
            && $donation->gateway_ref !== ''
            && $this->gateway->isAvailable();
    }

    private function redirectToStatus(
        Donation $donation,
        string $type,
        string $messageKey,
    ): RedirectResponse {
        Inertia::flash('toast', [
            'type' => $type,
            'message' => __($messageKey),
        ]);

        // `locale` vem do `URL::defaults()` do ResolveLocale, como no `store()`.
        return redirect()->route('donate.status', ['donation' => $donation->ulid]);
    }

    private function refuse(string $messageKey): RedirectResponse
    {
        Inertia::flash('toast', [
            'type' => 'error',
            'message' => __($messageKey),
        ]);

        return back();
    }

    /**
     * O Pix dinamico devolve o PNG em base64 puro; o estatico ja vem como
     * `data:` URI. A tela so precisa de uma URL pronta.
     */
    private function qrImage(?string $stored): ?string
    {
        if (! is_string($stored) || $stored === '') {
            return null;
        }

        return str_starts_with($stored, 'data:')
            ? $stored
            : 'data:image/png;base64,'.$stored;
    }
}
