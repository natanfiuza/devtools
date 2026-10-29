<?php

namespace App\Support\Ai;

use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Agregacao e formatacao de metricas de consumo de tokens de API.
 *
 * Consolida o historico da tabela `ai_requests` por dia e por token para renderizar
 * o grafico de barras na tela de configuracoes de tokens de API.
 */
final class ApiTokenUsageMetrics
{
    public const PERIOD_CURRENT_MONTH = 'current_month';

    public const PERIOD_PREVIOUS_MONTH = 'previous_month';

    public const PERIOD_CUSTOM = 'custom';

    public const MAX_CUSTOM_RANGE_DAYS = 90;

    /**
     * @return array{
     *     period: array{type: string, startDate: string, endDate: string},
     *     days: array<int, array{
     *         date: string,
     *         dayLabel: string,
     *         formattedDate: string,
     *         totalTokens: int,
     *         totalRequests: int,
     *         breakdown: array<int, array{
     *             tokenId: int|string,
     *             tokenName: string,
     *             tokens: int,
     *             requests: int,
     *             isRevoked: bool
     *         }>
     *     }>,
     *     tokens: array<int, array{
     *         id: int|string,
     *         name: string,
     *         isRevoked: bool
     *     }>,
     *     summary: array{
     *         totalTokens: int,
     *         totalRequests: int
     *     }
     * }
     */
    public function forUser(
        User $user,
        ?string $periodType = self::PERIOD_CURRENT_MONTH,
        ?string $customStart = null,
        ?string $customEnd = null,
        ?CarbonInterface $now = null
    ): array {
        $referenceNow = $now ?? CarbonImmutable::now();
        [$resolvedType, $startDate, $endDate] = $this->resolveDateRange(
            $periodType,
            $customStart,
            $customEnd,
            $referenceNow
        );

        $activeTokens = $user->tokens()
            ->get(['id', 'name'])
            ->keyBy('id')
            ->map(fn ($token): string => (string) $token->name)
            ->all();

        $rows = DB::table('ai_requests')
            ->where('user_id', $user->getKey())
            ->whereNotNull('api_token_id')
            ->where('created_at', '>=', $startDate->startOfDay())
            ->where('created_at', '<=', $endDate->endOfDay())
            ->selectRaw('
                date(created_at) as usage_date,
                api_token_id,
                COUNT(*) as requests_count,
                SUM(CASE WHEN (COALESCE(tokens_in, 0) + COALESCE(tokens_out, 0)) > 0
                    THEN (COALESCE(tokens_in, 0) + COALESCE(tokens_out, 0))
                    ELSE COALESCE(reserved_tokens, 0)
                END) as total_tokens
            ')
            ->groupBy('usage_date', 'api_token_id')
            ->orderBy('usage_date')
            ->get();

        $revokedLabel = __('settings.api_tokens.usage_chart.legend.revoked_tokens');

        // Mapear linhas por data e token
        /** @var array<string, array<string, array{tokens: int, requests: int, tokenName: string, isRevoked: bool}>> $groupedByDay */
        $groupedByDay = [];
        /** @var array<string, array{id: int|string, name: string, isRevoked: bool}> $tokensEncountered */
        $tokensEncountered = [];

        foreach ($rows as $row) {
            $date = (string) $row->usage_date;
            $rawTokenId = (int) $row->api_token_id;
            $tokens = (int) $row->total_tokens;
            $requests = (int) $row->requests_count;

            $isActive = isset($activeTokens[$rawTokenId]);
            $tokenKey = $isActive ? (string) $rawTokenId : 'revoked';
            $tokenName = $isActive ? $activeTokens[$rawTokenId] : $revokedLabel;
            $isRevoked = ! $isActive;

            if (! isset($groupedByDay[$date])) {
                $groupedByDay[$date] = [];
            }

            if (! isset($groupedByDay[$date][$tokenKey])) {
                $groupedByDay[$date][$tokenKey] = [
                    'tokens' => 0,
                    'requests' => 0,
                    'tokenName' => $tokenName,
                    'isRevoked' => $isRevoked,
                ];
            }

            $groupedByDay[$date][$tokenKey]['tokens'] += $tokens;
            $groupedByDay[$date][$tokenKey]['requests'] += $requests;

            if (! isset($tokensEncountered[$tokenKey])) {
                $tokensEncountered[$tokenKey] = [
                    'id' => $isActive ? $rawTokenId : 'revoked',
                    'name' => $tokenName,
                    'isRevoked' => $isRevoked,
                ];
            }
        }

        // Construir dias continuos do periodo
        $days = [];
        $grandTotalTokens = 0;
        $grandTotalRequests = 0;

        $cursor = $startDate->copy()->startOfDay();
        $endCursor = $endDate->copy()->startOfDay();

        while ($cursor->lte($endCursor)) {
            $dateStr = $cursor->format('Y-m-d');
            $dayBreakdown = [];
            $dayTotalTokens = 0;
            $dayTotalRequests = 0;

            if (isset($groupedByDay[$dateStr])) {
                foreach ($groupedByDay[$dateStr] as $tokenKey => $data) {
                    $dayTotalTokens += $data['tokens'];
                    $dayTotalRequests += $data['requests'];

                    $dayBreakdown[] = [
                        'tokenId' => $tokenKey === 'revoked' ? 'revoked' : (int) $tokenKey,
                        'tokenName' => $data['tokenName'],
                        'tokens' => $data['tokens'],
                        'requests' => $data['requests'],
                        'isRevoked' => $data['isRevoked'],
                    ];
                }
            }

            $grandTotalTokens += $dayTotalTokens;
            $grandTotalRequests += $dayTotalRequests;

            $days[] = [
                'date' => $dateStr,
                'dayLabel' => $cursor->format('d'),
                'formattedDate' => $cursor->format('d/m/Y'),
                'totalTokens' => $dayTotalTokens,
                'totalRequests' => $dayTotalRequests,
                'breakdown' => $dayBreakdown,
            ];

            $cursor = $cursor->addDay();
        }

        // Lista de tokens ordenada (tokens ativos primeiro por nome, revogados no final)
        $tokensList = array_values($tokensEncountered);
        usort($tokensList, function (array $a, array $b): int {
            if ($a['isRevoked'] !== $b['isRevoked']) {
                return $a['isRevoked'] ? 1 : -1;
            }

            return strcasecmp($a['name'], $b['name']);
        });

        return [
            'period' => [
                'type' => $resolvedType,
                'startDate' => $startDate->format('Y-m-d'),
                'endDate' => $endDate->format('Y-m-d'),
            ],
            'days' => $days,
            'tokens' => $tokensList,
            'summary' => [
                'totalTokens' => $grandTotalTokens,
                'totalRequests' => $grandTotalRequests,
            ],
        ];
    }

    /**
     * @return array{0: string, 1: CarbonInterface, 2: CarbonInterface}
     */
    private function resolveDateRange(
        ?string $periodType,
        ?string $customStart,
        ?string $customEnd,
        CarbonInterface $now
    ): array {
        if ($periodType === self::PERIOD_PREVIOUS_MONTH) {
            $previous = $now->subMonthNoOverflow();

            return [
                self::PERIOD_PREVIOUS_MONTH,
                $previous->copy()->startOfMonth(),
                $previous->copy()->endOfMonth(),
            ];
        }

        if ($periodType === self::PERIOD_CUSTOM && ! empty($customStart) && ! empty($customEnd)) {
            try {
                $start = CarbonImmutable::parse($customStart)->startOfDay();
                $end = CarbonImmutable::parse($customEnd)->endOfDay();

                if ($start->gt($end)) {
                    [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
                }

                // Limitar intervalo maximo a 90 dias
                if ($start->diffInDays($end) > self::MAX_CUSTOM_RANGE_DAYS) {
                    $start = $end->subDays(self::MAX_CUSTOM_RANGE_DAYS)->startOfDay();
                }

                return [
                    self::PERIOD_CUSTOM,
                    $start,
                    $end,
                ];
            } catch (\Throwable) {
                // Em caso de data invalida, recai suavemente para o mes atual
            }
        }

        return [
            self::PERIOD_CURRENT_MONTH,
            $now->copy()->startOfMonth(),
            $now->copy()->endOfMonth(),
        ];
    }
}
