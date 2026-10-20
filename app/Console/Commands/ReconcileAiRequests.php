<?php

namespace App\Console\Commands;

use App\Actions\Ai\FailAiQuery;
use App\Enums\AiFailureCode;
use App\Models\AiRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Fecha solicitacoes presas em `queued`/`processing`.
 *
 * O job tem `failOnTimeout`, mas um worker morto (OOM, deploy, maquina
 * reiniciada) nao executa `failed()`. Sem este reconciliador, a reserva ficaria
 * debitada para sempre e o dedupe bloquearia pedidos identicos ate o TTL.
 *
 * A folga cobre o tempo entre o worker pegar o job e o timeout dele. O prazo
 * comeca no `created_at` da solicitacao, nao no `started_at` da fila.
 */
final class ReconcileAiRequests extends Command
{
    /**
     * @var string
     */
    protected $signature = 'ai:reconcile {--dry-run : Apenas informa quantas solicitacoes seriam encerradas}';

    /**
     * @var string
     */
    protected $description = 'Falha e estorna solicitacoes de IA presas alem do timeout do job.';

    public function handle(FailAiQuery $fail): int
    {
        $timeout = max(1, (int) config('ai.job.timeout', 90));
        $grace = max(0, (int) config('ai.job.reconcile_grace_seconds', 30));
        $cutoff = now()->subSeconds($timeout + $grace);

        $pending = AiRequest::query()
            ->pending()
            ->where('created_at', '<=', $cutoff);

        $count = (clone $pending)->count();

        if ((bool) $this->option('dry-run')) {
            $this->info("Dry run: {$count} solicitacao(oes) presa(s) antes de {$cutoff->toDateTimeString()} seriam encerradas.");

            return self::SUCCESS;
        }

        if ($count === 0) {
            $this->info('Nenhuma solicitacao presa encontrada.');

            return self::SUCCESS;
        }

        $failed = 0;

        /** @var Collection<int, AiRequest> $requests */
        $requests = $pending->get();

        foreach ($requests as $request) {
            $fail($request, AiFailureCode::Timeout);
            $failed++;
        }

        $this->info("{$failed} solicitacao(oes) encerrada(s) como timeout e com a reserva estornada.");

        return self::SUCCESS;
    }
}
