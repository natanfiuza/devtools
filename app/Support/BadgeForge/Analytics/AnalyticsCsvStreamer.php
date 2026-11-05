<?php

namespace App\Support\BadgeForge\Analytics;

use App\Enums\BadgeViewSubject;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exportacao CSV das visualizacoes do dono (F7, ADR-086).
 *
 * Le o bruto em blocos e nunca inclui `ip_hash`, `user_agent_hash` nem o UA
 * cru: o arquivo e do dono, mas PII nao sai do servidor. O nome do sujeito e
 * resolvido em lote antes do streaming; imagem excluida sai com nome vazio.
 */
final class AnalyticsCsvStreamer
{
    private const int CHUNK = 500;

    private const array HEADER = [
        'viewed_at',
        'subject_name',
        'subject_type',
        'definition',
        'format',
        'style',
        'cache',
        'country',
        'referer_domain',
    ];

    public function __construct(private readonly AnalyticsSubjectResolver $subjects) {}

    public function stream(User $user, int $days): StreamedResponse
    {
        $start = now()->startOfDay()->subDays(max(1, $days) - 1);

        $query = DB::table('badge_views')
            ->where('user_id', $user->getKey())
            ->where('viewed_at', '>=', $start)
            ->orderBy('id');

        $names = $this->names($user, $start);

        return response()->streamDownload(
            static function () use ($query, $names): void {
                $out = fopen('php://output', 'w');

                if ($out === false) {
                    return;
                }

                fputcsv($out, self::HEADER);

                $query->chunkById(self::CHUNK, static function ($rows) use ($out, $names): void {
                    foreach ($rows as $row) {
                        $type = (string) $row->subject_type;

                        fputcsv($out, [
                            Carbon::parse((string) $row->viewed_at)->toIso8601String(),
                            $names[$type.':'.(string) $row->subject_id] ?? '',
                            $type,
                            (string) ($row->definition ?? ''),
                            (string) $row->format,
                            (string) ($row->style ?? ''),
                            self::cache($row->cache_hit),
                            (string) ($row->country ?? ''),
                            (string) ($row->referer_domain ?? ''),
                        ]);
                    }
                });

                fclose($out);
            },
            'badgeforge-analytics-'.now()->toDateString().'.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    /**
     * @return array<string, string>
     */
    private function names(User $user, CarbonInterface $start): array
    {
        $rows = DB::table('badge_views')
            ->where('user_id', $user->getKey())
            ->where('viewed_at', '>=', $start)
            ->select('subject_type', 'subject_id')
            ->distinct()
            ->get();

        $keys = [];

        foreach ($rows as $row) {
            $type = BadgeViewSubject::tryFrom((string) $row->subject_type);

            if ($type !== null) {
                $keys[] = ['type' => $type, 'id' => (string) $row->subject_id];
            }
        }

        return $this->subjects->names($user, $keys);
    }

    private static function cache(mixed $cacheHit): string
    {
        if ($cacheHit === null) {
            return '';
        }

        return (int) $cacheHit === 1 ? 'hit' : 'miss';
    }
}
