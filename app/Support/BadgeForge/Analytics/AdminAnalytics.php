<?php

namespace App\Support\BadgeForge\Analytics;

use Illuminate\Support\Facades\DB;

/**
 * Volume global do analytics no painel administrativo (F7, ADR-086).
 *
 * O admin le **so o agregado diario** (`badge_view_dailies`), que guarda ate
 * 400 dias. A serie e zero-filled e as janelas vem de
 * `badgeforge.analytics.admin_windows`; janela invalida cai no padrao.
 *
 * Visitantes unicos nao aparecem aqui: o agregado e particionado por pais,
 * referenciador, formato e definicao, e somar `unique_visitors` entre linhas
 * contaria o mesmo IP varias vezes. O dono continua vendo o unico exato a
 * partir do bruto. A taxa de cache e a fatia das visualizacoes servidas do
 * cache (`cache_hits / views`).
 */
final class AdminAnalytics
{
    private const int DEFAULT_WINDOW = 30;

    /**
     * @return array<string, mixed>
     */
    public function for(mixed $requested = null): array
    {
        $days = $this->windowDays($requested);
        $start = now()->startOfDay()->subDays($days - 1);
        $today = now()->startOfDay();

        // `DATE()` para funcionar igual no MySQL (coluna DATE) e no SQLite dos
        // testes, onde o cast `date` do model grava `Y-m-d H:i:s`.
        $base = DB::table('badge_view_dailies')
            ->whereDate('day', '>=', $start->toDateString())
            ->whereDate('day', '<=', $today->toDateString());

        $totals = self::row((clone $base)
            ->selectRaw('COALESCE(SUM(views), 0) as views, COALESCE(SUM(cache_hits), 0) as cache_hits')
            ->first());

        $rows = (clone $base)
            ->selectRaw('DATE(day) as day, SUM(views) as views, SUM(cache_hits) as cache_hits')
            ->groupByRaw('DATE(day)')
            ->orderByRaw('DATE(day)')
            ->get();

        $byDay = [];

        foreach ($rows as $row) {
            $byDay[(string) $row->day] = [
                'views' => (int) $row->views,
                'cacheHits' => (int) $row->cache_hits,
            ];
        }

        $series = [];

        for ($date = $start->copy(); $date->lte($today); $date = $date->addDay()) {
            $key = $date->toDateString();

            $series[] = [
                'date' => $key,
                'views' => $byDay[$key]['views'] ?? 0,
                'cacheHits' => $byDay[$key]['cacheHits'] ?? 0,
            ];
        }

        $views = (int) ($totals['views'] ?? 0);
        $hits = (int) ($totals['cache_hits'] ?? 0);

        return [
            'window' => $days,
            'windows' => $this->windows(),
            'start' => $start->toDateString(),
            'end' => $today->toDateString(),
            'totals' => [
                'views' => $views,
                'cacheHits' => $hits,
                'cacheRate' => $views > 0 ? round($hits / $views, 4) : null,
            ],
            'days' => $series,
        ];
    }

    private function windowDays(mixed $requested): int
    {
        $windows = $this->windows();

        $value = is_numeric($requested) ? (int) $requested : null;

        if ($value !== null && in_array($value, $windows, true)) {
            return $value;
        }

        return in_array(self::DEFAULT_WINDOW, $windows, true)
            ? self::DEFAULT_WINDOW
            : $windows[0];
    }

    /**
     * @return list<int>
     */
    private function windows(): array
    {
        $windows = array_values(array_filter(
            array_map(
                static fn (mixed $value): int => (int) $value,
                (array) config('badgeforge.analytics.admin_windows', [7, 30, 90, 400]),
            ),
            static fn (int $value): bool => $value > 0,
        ));

        return $windows === [] ? [self::DEFAULT_WINDOW] : $windows;
    }

    /**
     * @return array<string, mixed>
     */
    private static function row(mixed $row): array
    {
        return is_object($row) ? (array) $row : [];
    }
}
