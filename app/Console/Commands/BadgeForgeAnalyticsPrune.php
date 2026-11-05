<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Poda da retencao do analytics do BadgeForge (F7, ADR-086).
 *
 * O bruto fica 90 dias (fonte do dono) e o agregado diario fica 400 (admin e
 * historico longo). A janela conta o dia de hoje: `--raw-days=90` preserva os
 * ultimos 90 dias e apaga o que veio antes. A poda roda depois da agregacao,
 * uma vez por dia, para nunca remover bruto ainda nao agregado.
 */
final class BadgeForgeAnalyticsPrune extends Command
{
    /**
     * @var string
     */
    protected $signature = 'badgeforge:analytics:prune
        {--raw-days= : Dias de bruto a manter (padrao: badgeforge.analytics.raw_retention_days)}
        {--daily-days= : Dias do agregado diario a manter (padrao: badgeforge.analytics.daily_retention_days)}
        {--dry-run : Apenas informa quantas linhas seriam removidas}';

    /**
     * @var string
     */
    protected $description = 'Remove as visualizacoes do BadgeForge fora da retencao de bruto e do agregado diario.';

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');

        $rawCutoff = now()->startOfDay()->subDays($this->days('raw-days', 'raw_retention_days', 90) - 1);
        $dailyCutoff = now()->startOfDay()->subDays($this->days('daily-days', 'daily_retention_days', 400) - 1);

        $raw = DB::table('badge_views')->where('viewed_at', '<', $rawCutoff);
        $daily = DB::table('badge_view_dailies')->where('day', '<', $dailyCutoff->toDateString());

        if ($isDryRun) {
            $this->info(
                'Dry run: '.$raw->count().' visualizacao(oes) bruta(s) e '.
                $daily->count().' linha(s) diaria(s) seriam removidas.',
            );

            return self::SUCCESS;
        }

        $rawRemoved = $raw->delete();
        $dailyRemoved = $daily->delete();

        $this->info(
            'Poda do analytics concluida: '.$rawRemoved.' visualizacao(oes) bruta(s) e '.
            $dailyRemoved.' linha(s) diaria(s) removidas.',
        );

        return self::SUCCESS;
    }

    /**
     * @param  string  $option  nome da opcao de linha de comando
     * @param  string  $configKey  chave em `badgeforge.analytics`
     */
    private function days(string $option, string $configKey, int $default): int
    {
        $value = $this->option($option);

        if (is_numeric($value)) {
            return max(1, (int) $value);
        }

        $configured = config('badgeforge.analytics.'.$configKey);

        return is_numeric($configured) ? max(1, (int) $configured) : $default;
    }
}
