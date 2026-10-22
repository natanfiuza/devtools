<?php

namespace App\Console\Commands;

use App\Enums\AiRequestStatus;
use App\Models\AiRequest;
use Illuminate\Console\Command;

/**
 * Remove consultas terminais mais antigas que a retencao configurada.
 *
 * O padrao vem de `ai.history.retention_days` (90 dias). A poda nao toca no
 * `usage_ledger` nem no `ai_cache`: cota e cache compartilhado continuam
 * valendo mesmo sem o historico da solicitacao.
 */
final class PruneHistory extends Command
{
    /**
     * @var string
     */
    protected $signature = 'history:prune {--days= : Dias de retencao (padrao: ai.history.retention_days)} {--dry-run : Apenas informa quantas linhas seriam removidas}';

    /**
     * @var string
     */
    protected $description = 'Remove o historico de consultas mais antigo que a retencao configurada.';

    public function handle(): int
    {
        $days = $this->option('days');

        if ($days === null || $days === '') {
            $days = (int) config('ai.history.retention_days', 90);
        } else {
            $days = (int) $days;
        }

        $days = max(1, $days);

        $query = AiRequest::query()
            ->whereIn('status', [
                AiRequestStatus::Done->value,
                AiRequestStatus::Failed->value,
            ])
            ->where('created_at', '<', now()->subDays($days));

        $pending = $query->count();

        if ((bool) $this->option('dry-run')) {
            $this->info("Dry run: {$pending} consulta(s) com mais de {$days} dia(s) seriam removidas.");

            return self::SUCCESS;
        }

        if ($pending === 0) {
            $this->info("Nenhuma consulta com mais de {$days} dia(s) para remover.");

            return self::SUCCESS;
        }

        $deleted = $query->delete();

        $this->info("{$deleted} consulta(s) com mais de {$days} dia(s) removida(s).");

        return self::SUCCESS;
    }
}
