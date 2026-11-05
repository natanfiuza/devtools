<?php

namespace App\Console\Commands;

use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Agregado diario do analytics do BadgeForge (F7, ADR-086).
 *
 * Reconstroi os ultimos dias a partir do bruto (`badge_views`), porque o
 * dono continua lendo o bruto e o agregado alimenta o admin e o historico
 * longo. Cada dia e apagado e reinserido dentro de uma transacao: rodar
 * duas vezes seguidas produz exatamente as mesmas linhas.
 *
 * Um dia sem nenhuma linha no bruto e ignorado de proposito. Depois da poda
 * de 90 dias o bruto some, mas as dailies ficam ate 400: apagar o dia "vazio"
 * destruiria esse historico.
 *
 * Dimensoes opcionais viram `''`, nunca NULL, para o indice unico do MySQL
 * funcionar. `unique_visitors` e o distinto do dia, nunca a soma entre dias.
 */
final class BadgeForgeAnalyticsAggregate extends Command
{
    private const int CHUNK = 500;

    /**
     * @var string
     */
    protected $signature = 'badgeforge:analytics:aggregate
        {--days= : Quantos dias reconstruir (padrao: badgeforge.analytics.aggregate_days)}';

    /**
     * @var string
     */
    protected $description = 'Reconstroi o agregado diario das visualizacoes do BadgeForge a partir do bruto.';

    public function handle(): int
    {
        $days = $this->days();
        $today = now()->startOfDay();

        $rebuilt = 0;
        $rows = 0;

        for ($offset = $days - 1; $offset >= 0; $offset--) {
            $day = $today->subDays($offset);
            $count = $this->rebuild($day);

            if ($count === null) {
                continue;
            }

            $rebuilt++;
            $rows += $count;
        }

        $this->info(
            'Agregacao concluida: '.$rebuilt.' dia(s) reconstruido(s), '.$rows.' linha(s) diaria(s).',
        );

        return self::SUCCESS;
    }

    /**
     * Devolve quantas linhas foram gravadas no dia, ou null quando o bruto do
     * dia esta vazio e o agregado existente deve ser preservado.
     */
    private function rebuild(CarbonInterface $day): ?int
    {
        $start = $day->copy()->startOfDay();
        $end = $start->copy()->addDay();

        $aggregate = DB::table('badge_views')
            ->where('viewed_at', '>=', $start)
            ->where('viewed_at', '<', $end)
            ->selectRaw('MAX(user_id) as user_id, subject_type, subject_id')
            ->selectRaw("COALESCE(definition, '') as definition, COALESCE(country, '') as country")
            ->selectRaw("COALESCE(referer_domain, '') as referer_domain, COALESCE(format, '') as format")
            ->selectRaw('COUNT(*) as views, COUNT(DISTINCT ip_hash) as unique_visitors')
            ->selectRaw('SUM(CASE WHEN cache_hit = 1 THEN 1 ELSE 0 END) as cache_hits')
            ->groupByRaw(
                "subject_type, subject_id, COALESCE(definition, ''), COALESCE(country, '')".
                ", COALESCE(referer_domain, ''), COALESCE(format, '')",
            )
            ->get();

        if ($aggregate->isEmpty()) {
            return null;
        }

        DB::transaction(function () use ($start, $aggregate): void {
            DB::table('badge_view_dailies')
                ->where('day', $start->toDateString())
                ->delete();

            foreach ($aggregate->chunk(self::CHUNK) as $chunk) {
                DB::table('badge_view_dailies')->insert(
                    array_values(array_map(
                        static fn (object $row): array => [
                            'subject_type' => (string) $row->subject_type,
                            'subject_id' => (string) $row->subject_id,
                            'user_id' => (int) $row->user_id,
                            'day' => $start->toDateString(),
                            'definition' => (string) $row->definition,
                            'country' => (string) $row->country,
                            'referer_domain' => (string) $row->referer_domain,
                            'format' => (string) $row->format,
                            'views' => (int) $row->views,
                            'unique_visitors' => (int) $row->unique_visitors,
                            'cache_hits' => (int) $row->cache_hits,
                        ],
                        $chunk->all(),
                    )),
                );
            }
        });

        return $aggregate->count();
    }

    private function days(): int
    {
        $option = $this->option('days');
        $configured = (array) config('badgeforge.analytics', []);
        $default = isset($configured['aggregate_days']) && is_numeric($configured['aggregate_days'])
            ? (int) $configured['aggregate_days']
            : 7;

        if (! is_numeric($option)) {
            return max(1, $default);
        }

        return max(1, (int) $option);
    }
}
