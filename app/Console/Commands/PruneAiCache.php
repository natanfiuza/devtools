<?php

namespace App\Console\Commands;

use App\Models\AiCache;
use Illuminate\Console\Command;

/**
 * Remove respostas expiradas do cache de IA.
 *
 * A expiracao ja e checada na leitura; a limpeza fisica evita que respostas
 * antigas fiquem guardadas indefinidamente. O `--dry-run` serve para conferir o
 * volume antes da primeira execucao em producao.
 *
 * Com a moderacao Colors na mesma tabela (F7, ADR-140), `--purpose` restringe
 * a poda a um uso e `--all` apaga tambem as linhas permanentes daquele uso
 * (por exemplo `colors.comment_moderation`). `--all` exige `--purpose` para
 * nunca apagar o cache inteiro por engano.
 */
final class PruneAiCache extends Command
{
    /**
     * @var string
     */
    protected $signature = 'ai:prune-cache
        {--dry-run : Apenas informa quantas linhas seriam removidas}
        {--purpose= : Restringe a um purpose (ex.: colors.comment_moderation)}
        {--all : Remove todas as linhas do purpose, inclusive as permanentes}';

    /**
     * @var string
     */
    protected $description = 'Remove respostas expiradas do cache de IA (opcionalmente por purpose).';

    public function handle(): int
    {
        $purpose = $this->option('purpose');
        $purpose = is_string($purpose) ? trim($purpose) : '';
        $all = (bool) $this->option('all');

        if ($all && $purpose === '') {
            $this->error('--all exige --purpose para nao apagar o cache inteiro.');

            return self::FAILURE;
        }

        $query = AiCache::query();

        if ($purpose !== '') {
            $query->where('purpose', $purpose);
        }

        if (! $all) {
            $query->whereNotNull('expires_at')
                ->where('expires_at', '<=', now());
        }

        $pending = (clone $query)->count();

        if ((bool) $this->option('dry-run')) {
            $this->info("Dry run: {$pending} resposta(s) seriam removidas.");

            return self::SUCCESS;
        }

        if ($pending === 0) {
            $this->info('Nenhuma resposta para remover no cache.');

            return self::SUCCESS;
        }

        $deleted = $query->delete();

        $this->info("{$deleted} resposta(s) removida(s) do cache.");

        return self::SUCCESS;
    }
}
