<?php

namespace App\Console\Commands;

use App\Enums\CheckoutStatus;
use App\Enums\DonationStatus;
use App\Models\CheckoutSession;
use App\Models\Donation;
use Illuminate\Console\Command;

/**
 * Vence checkouts e doacoes pendentes com prazo estourado (F6).
 *
 * A expiracao tambem e preguicosa na leitura: uma sessao pendente com prazo
 * vencido ja nao vale antes desta varredura, porque a confirmacao so acontece
 * com webhook assinado e valor conferido. A rotina existe para o estado
 * gravado alcancar o que as telas mostram, e para o admin enxergar o que
 * morreu sem abrir linha por linha.
 *
 * So mexe em `pending`: aprovado, pago, estornado ou falho nunca regridem.
 */
final class ExpirePayments extends Command
{
    /**
     * @var string
     */
    protected $signature = 'payments:expire {--dry-run : Apenas informa quantas linhas seriam vencidas}';

    /**
     * @var string
     */
    protected $description = 'Marca como vencidos os checkouts e as doacoes pendentes com prazo expirado.';

    public function handle(): int
    {
        $now = now();

        $sessions = CheckoutSession::query()
            ->where('status', CheckoutStatus::Pending->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now);

        $donations = Donation::query()
            ->where('status', DonationStatus::Pending->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now);

        if ((bool) $this->option('dry-run')) {
            $this->info(sprintf(
                'Dry run: %d checkout(s) e %d doacao(oes) venceriam.',
                (clone $sessions)->count(),
                (clone $donations)->count(),
            ));

            return self::SUCCESS;
        }

        $expiredSessions = $sessions->update(['status' => CheckoutStatus::Expired->value]);
        $expiredDonations = $donations->update(['status' => DonationStatus::Expired->value]);

        $this->info(sprintf(
            '%d checkout(s) e %d doacao(oes) marcados como vencidos.',
            $expiredSessions,
            $expiredDonations,
        ));

        return self::SUCCESS;
    }
}
