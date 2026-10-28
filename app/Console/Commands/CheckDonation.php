<?php

namespace App\Console\Commands;

use App\Actions\Donations\SyncDonationPayment;
use App\Contracts\Payments\PaymentGateway;
use App\Enums\DonationStatus;
use App\Exceptions\Payments\PaymentGatewayException;
use App\Models\Donation;
use App\Support\Format;
use App\Support\Payments\GatewayPayment;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Diagnostico de uma doacao contra o provedor (ADR-059).
 *
 * Le o pagamento pelo id gravado na doacao, mostra o que o Mercado Pago
 * responde (status, detalhe, modo de operacao) e roda a mesma sincronizacao do
 * webhook e do botao "Ja paguei". **Escreve estado** - nao e somente leitura.
 *
 * Nunca imprime token, corpo cru nem o e-mail completo do pagador: o e-mail
 * sai mascarado e o resto e so o que o DTO de diagnostico carrega.
 */
final class CheckDonation extends Command
{
    /**
     * @var string
     */
    protected $signature = 'donations:check {ulid : ULID da doacao}';

    /**
     * @var string
     */
    protected $description = 'Consulta o pagamento no Mercado Pago e sincroniza o estado da doacao.';

    public function handle(PaymentGateway $gateway, SyncDonationPayment $sync): int
    {
        $ulid = $this->argument('ulid');

        if (! is_string($ulid) || preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', trim($ulid)) !== 1) {
            $this->error('ULID invalido.');

            return self::FAILURE;
        }

        $donation = Donation::query()->where('ulid', trim($ulid))->first();

        if (! $donation instanceof Donation) {
            $this->error('Doacao nao encontrada.');

            return self::FAILURE;
        }

        $this->localSnapshot($donation);

        if ($donation->gateway !== 'mercadopago'
            || ! is_string($donation->gateway_ref)
            || $donation->gateway_ref === ''
        ) {
            $this->warn('Doacao sem cobranca no Mercado Pago (Pix estatico ou criacao incompleta): nada para consultar.');

            return self::FAILURE;
        }

        if (! $gateway->isAvailable()) {
            $this->error('Gateway sem credenciais: o Pix dinamico esta desligado.');

            return self::FAILURE;
        }

        try {
            $payment = $gateway->payment($donation->gateway_ref);
        } catch (PaymentGatewayException $exception) {
            // A excecao carrega so operacao e status, entao o contexto e seguro.
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->providerSnapshot($payment);

        if ($payment->externalReference !== 'don_'.$donation->ulid) {
            $this->error('external_reference divergente: o pagamento nao pertence a esta doacao. Nada foi sincronizado.');

            return self::FAILURE;
        }

        if ($payment->liveMode === false) {
            $this->warn('live_mode=false: este pagamento foi criado com credencial de teste.');
        }

        $outcome = $sync($donation, $payment);

        $this->info(sprintf(
            'Sincronizacao: %s. Doacao agora esta como %s.',
            $outcome->value,
            $donation->fresh()?->status->value ?? 'desconhecido',
        ));

        // `failed` e terminal por decisao (ADR-048): o comando revela a
        // anomalia, mas nao reabre a doacao.
        if ($payment->isApproved() && $donation->status === DonationStatus::Failed) {
            $this->warn('Pagamento aprovado no provedor em doacao marcada como falha: estado mantido (anomalia de dados).');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function localSnapshot(Donation $donation): void
    {
        $rows = [
            ['ULID', $donation->ulid],
            ['Status', $donation->status->value],
            ['Valor', Format::money($donation->amount_cents, $donation->currency)],
            ['Gateway', $donation->gateway],
            ['Cobranca', $donation->gateway_ref ?? '-'],
            ['Status no provedor', $donation->gateway_status ?? '-'],
            ['Detalhe no provedor', $donation->gateway_status_detail ?? '-'],
            ['Criada em', $donation->created_at?->toDateTimeString() ?? '-'],
            ['Expira em', $donation->expires_at?->toDateTimeString() ?? '-'],
            ['Paga em', $donation->paid_at?->toDateTimeString() ?? '-'],
        ];

        $effective = $donation->effectiveStatus();

        if ($effective !== $donation->status) {
            $rows[] = ['Status efetivo', $effective->value.' (vencida na leitura)'];
        }

        $this->table(['Campo', 'Valor'], $rows);
    }

    private function providerSnapshot(GatewayPayment $payment): void
    {
        $this->table(['Campo', 'Valor'], [
            ['Pagamento', $payment->id],
            ['Status', $payment->status],
            ['Detalhe', $payment->statusDetail ?? '-'],
            ['live_mode', $payment->liveMode === null ? '-' : ($payment->liveMode ? 'true' : 'false')],
            ['Meio', $payment->method->value.($payment->methodId !== null ? ' ('.$payment->methodId.')' : '')],
            ['Valor', Format::money($payment->amountCents, $payment->currency)],
            ['Referencia', $payment->externalReference ?? '-'],
            ['Criado em', $payment->createdAt?->toDateTimeString() ?? '-'],
            ['Expira em', $payment->expiresAt?->toDateTimeString() ?? '-'],
            ['Aprovado em', $payment->paidAt?->toDateTimeString() ?? '-'],
            ['Recebedor', $payment->collectorId ?? '-'],
            ['Pagador', $this->maskEmail($payment->payerEmail) ?? '-'],
        ]);
    }

    /**
     * Mantem a primeira letra e o dominio: o suficiente para reconhecer a conta
     * e comparar com o painel, sem expor o endereco inteiro no terminal.
     */
    private function maskEmail(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        $masked = Str::mask($local, '*', 1);

        return $domain === '' ? $masked : $masked.'@'.$domain;
    }
}
