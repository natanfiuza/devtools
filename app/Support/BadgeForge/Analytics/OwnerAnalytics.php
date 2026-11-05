<?php

namespace App\Support\BadgeForge\Analytics;

use App\Enums\BadgeViewSubject;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Analytics da conta (`/dashboard/badgeforge`, F7, ADR-086).
 *
 * Le sempre o bruto (`badge_views`), que e a fonte do dono ate a poda de 90
 * dias; o agregado diario alimenta o admin e o historico longo. Free ve os
 * ultimos `free_window_days`: totais e contagens por imagem, sem quebras.
 * Pago escolhe 7/30/90 e ganha serie diaria, referrers, paises, formatos,
 * taxa de cache e CSV. Janela invalida nunca vira 422: cai no padrao.
 *
 * "Visitante unico" e `COUNT(DISTINCT ip_hash)` dentro do periodo. Na serie
 * diaria o distinto e por dia, nunca somado entre dias.
 */
final class OwnerAnalytics
{
    private const int DEFAULT_WINDOW = 30;

    public function __construct(private readonly AnalyticsSubjectResolver $subjects) {}

    /**
     * Janela efetiva em dias. Free ignora o pedido; pago aceita so as janelas
     * de `analytics.paid_windows` e qualquer outro valor cai no padrao.
     */
    public function windowDays(User $user, mixed $requested = null): int
    {
        $freeWindow = self::intOr((array) config('badgeforge.analytics', []), 'free_window_days', self::DEFAULT_WINDOW);

        if ($user->effectivePlan()->isFree()) {
            return max(1, $freeWindow);
        }

        $windows = self::windows();

        $value = is_numeric($requested) ? (int) $requested : null;

        if ($value !== null && in_array($value, $windows, true)) {
            return $value;
        }

        return in_array(self::DEFAULT_WINDOW, $windows, true)
            ? self::DEFAULT_WINDOW
            : ($windows[0] ?? self::DEFAULT_WINDOW);
    }

    /**
     * @return list<int>
     */
    public function paidWindows(): array
    {
        return self::windows();
    }

    /**
     * @return array<string, mixed>
     */
    public function for(User $user, mixed $requested = null): array
    {
        $isPaid = ! $user->effectivePlan()->isFree();
        $days = $this->windowDays($user, $requested);
        $start = now()->startOfDay()->subDays($days - 1);
        $today = now()->startOfDay();

        $query = DB::table('badge_views')
            ->where('user_id', $user->getKey())
            ->where('viewed_at', '>=', $start);

        $totals = self::row((clone $query)->selectRaw(
            'COUNT(*) as views, COUNT(DISTINCT ip_hash) as uniques',
        )->first());

        $subjects = $this->subjects($user, clone $query);

        return [
            'isPaid' => $isPaid,
            'window' => $days,
            'windows' => $isPaid ? $this->paidWindows() : [],
            'freeWindowDays' => self::intOr((array) config('badgeforge.analytics', []), 'free_window_days', self::DEFAULT_WINDOW),
            'start' => $start->toDateString(),
            'end' => $today->toDateString(),
            'totals' => [
                'views' => (int) ($totals['views'] ?? 0),
                'uniques' => (int) ($totals['uniques'] ?? 0),
            ],
            'subjects' => $subjects,
            'days' => $isPaid ? $this->series(clone $query, $start, $today) : [],
            'referers' => $isPaid ? $this->breakdown(clone $query, 'referer_domain', 'domain', $this->topLimit()) : [],
            'countries' => $isPaid ? $this->breakdown(clone $query, 'country', 'country', $this->topLimit()) : [],
            'formats' => $isPaid ? $this->breakdown(clone $query, 'format', 'format', null) : [],
            'cache' => $isPaid ? $this->cache(clone $query) : null,
            'exportUrl' => $isPaid
                ? route('badgeforge.analytics.export', ['window' => $days])
                : null,
        ];
    }

    /**
     * @return list<array{type: string, id: string, name: string, views: int, uniques: int}>
     */
    private function subjects(User $user, Builder $query): array
    {
        $rows = $query
            ->selectRaw('subject_type, subject_id, COUNT(*) as views, COUNT(DISTINCT ip_hash) as uniques')
            ->groupBy('subject_type', 'subject_id')
            ->orderByDesc('views')
            ->orderBy('subject_id')
            ->get();

        $subjects = [];
        $keys = [];

        foreach ($rows as $row) {
            $type = BadgeViewSubject::tryFrom((string) $row->subject_type);

            if ($type === null) {
                continue;
            }

            $subjects[] = [
                'type' => $type->value,
                'id' => (string) $row->subject_id,
                'views' => (int) $row->views,
                'uniques' => (int) $row->uniques,
            ];
            $keys[] = ['type' => $type, 'id' => (string) $row->subject_id];
        }

        $names = $this->subjects->names($user, $keys);

        // Free ve a contagem por imagem; o nome continua disponivel para o
        // dono nao perder a referencia, mas a resposta nao ganha quebras.
        return array_map(static function (array $subject) use ($names): array {
            return [
                'type' => $subject['type'],
                'id' => $subject['id'],
                'name' => $names[$subject['type'].':'.$subject['id']] ?? '',
                'views' => $subject['views'],
                'uniques' => $subject['uniques'],
            ];
        }, $subjects);
    }

    /**
     * Serie diaria zero-filled: um ponto por dia da janela, mesmo sem acesso.
     *
     * @return list<array{date: string, views: int, uniques: int}>
     */
    private function series(Builder $query, CarbonInterface $start, CarbonInterface $today): array
    {
        $rows = $query
            ->selectRaw('DATE(viewed_at) as day, COUNT(*) as views, COUNT(DISTINCT ip_hash) as uniques')
            ->groupByRaw('DATE(viewed_at)')
            ->orderBy('day')
            ->get();

        $byDay = [];

        foreach ($rows as $row) {
            $byDay[(string) $row->day] = [
                'views' => (int) $row->views,
                'uniques' => (int) $row->uniques,
            ];
        }

        $series = [];

        for ($date = $start->copy(); $date->lte($today); $date = $date->addDay()) {
            $key = $date->toDateString();

            $series[] = [
                'date' => $key,
                'views' => $byDay[$key]['views'] ?? 0,
                'uniques' => $byDay[$key]['uniques'] ?? 0,
            ];
        }

        return $series;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function breakdown(Builder $query, string $column, string $alias, ?int $limit): array
    {
        $query = $query
            ->whereNotNull($column)
            ->where($column, '<>', '')
            ->select($column)
            ->selectRaw('COUNT(*) as views')
            ->groupBy($column)
            ->orderByDesc('views')
            ->orderBy($column);

        if ($limit !== null) {
            $query->limit($limit);
        }

        return array_values(array_map(static fn (object $row): array => [
            $alias => (string) $row->{$column},
            'views' => (int) $row->views,
        ], $query->get()->all()));
    }

    /**
     * Taxa de cache so entre acessos que informam o campo: o acesso a badge
     * pessoal nasce com `cache_hit = null` e nao entra na conta.
     *
     * @return array{hits: int, total: int, rate: float|null}
     */
    private function cache(Builder $query): array
    {
        $row = self::row($query
            ->whereNotNull('cache_hit')
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN cache_hit = 1 THEN 1 ELSE 0 END) as hits')
            ->first());

        $total = (int) ($row['total'] ?? 0);
        $hits = (int) ($row['hits'] ?? 0);

        return [
            'hits' => $hits,
            'total' => $total,
            'rate' => $total > 0 ? round($hits / $total, 4) : null,
        ];
    }

    private function topLimit(): int
    {
        return max(1, self::intOr((array) config('badgeforge.analytics', []), 'top_limit', 10));
    }

    /**
     * @return list<int>
     */
    private static function windows(): array
    {
        $windows = (array) config('badgeforge.analytics.paid_windows', [7, 30, 90]);

        $normalized = array_values(array_filter(
            array_map(static fn (mixed $value): int => (int) $value, $windows),
            static fn (int $value): bool => $value > 0,
        ));

        return $normalized === [] ? [self::DEFAULT_WINDOW] : $normalized;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function intOr(array $config, string $key, int $default): int
    {
        return isset($config[$key]) && is_numeric($config[$key])
            ? (int) $config[$key]
            : $default;
    }

    /**
     * @return array<string, mixed>
     */
    private static function row(mixed $row): array
    {
        return is_object($row) ? (array) $row : [];
    }
}
