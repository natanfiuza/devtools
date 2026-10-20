<?php

namespace App\Console\Commands;

use App\Models\AiCache;
use App\Support\Ai\AiCacheRepository;
use Illuminate\Console\Command;

/**
 * Remove respostas expiradas do cache de IA.
 *
 * A expiracao ja e checada na leitura; a limpeza fisica evita que schemas
 * antigos fiquem guardados indefinidamente. O `--dry-run` serve para conferir o
 * volume antes da primeira execucao em producao.
 */
final class PruneAiCache extends Command
{
    /**
     * @var string
     */
    protected $signature = 'ai:prune-cache {--dry-run : Apenas informa quantas linhas seriam removidas}';

    /**
     * @var string
     */
    protected $description = 'Remove respostas expiradas do cache do AI SQL Builder.';

    public function handle(AiCacheRepository $cache): int
    {
        $pending = AiCache::query()
            ->where('expires_at', '<=', now())
            ->count();

        if ((bool) $this->option('dry-run')) {
            $this->info("Dry run: {$pending} resposta(s) expirada(s) seriam removidas.");

            return self::SUCCESS;
        }

        if ($pending === 0) {
            $this->info('Nenhuma resposta expirada no cache.');

            return self::SUCCESS;
        }

        $deleted = $cache->deleteExpired();

        $this->info("{$deleted} resposta(s) expirada(s) removida(s) do cache.");

        return self::SUCCESS;
    }
}
