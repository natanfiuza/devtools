<?php

namespace App\Console\Commands;

use App\Models\WebhookEvent;
use Illuminate\Console\Command;

/**
 * Poda eventos de webhook fora da janela de retencao (F6).
 *
 * O payload cru serve para auditoria e reprocessamento por 30 dias; depois
 * disso e lixo operacional. A poda toca **somente** `webhook_events`: registros
 * fiscais (`payments`), periodos (`subscriptions`) e doacoes (`donations`) ficam
 * onde estao, independentemente da idade.
 */
final class PruneWebhookEvents extends Command
{
    /**
     * @var string
     */
    protected $signature = 'webhooks:prune {--days=30 : Retencao em dias} {--dry-run : Apenas informa quantas linhas seriam removidas}';

    /**
     * @var string
     */
    protected $description = 'Remove eventos de webhook mais antigos que a janela de retencao.';

    public function handle(): int
    {
        // Menos de um dia nao e retencao, e acidente: `--days=0` apagaria tudo.
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);

        $query = WebhookEvent::query()->where('created_at', '<', $cutoff);

        if ((bool) $this->option('dry-run')) {
            $this->info(sprintf(
                'Dry run: %d evento(s) com mais de %d dia(s) seriam removidos.',
                (clone $query)->count(),
                $days,
            ));

            return self::SUCCESS;
        }

        $deleted = WebhookEvent::query()->where('created_at', '<', $cutoff)->delete();

        $this->info(sprintf('%d evento(s) removidos (retencao de %d dia(s)).', $deleted, $days));

        return self::SUCCESS;
    }
}
