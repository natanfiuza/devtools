<?php

namespace App\Http\Controllers\BadgeForge;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\BadgeForge\Analytics\AnalyticsCsvStreamer;
use App\Support\BadgeForge\Analytics\OwnerAnalytics;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exportacao CSV das visualizacoes (F7, ADR-086).
 *
 * So plano pago (`badgeforge.paid`) e so do dono. A janela segue a mesma regra
 * da dashboard: valor fora de `analytics.paid_windows` cai no padrao em vez
 * de 422. O arquivo nao tem IP, hash de IP nem User-Agent.
 */
final class AnalyticsExportController extends Controller
{
    public function __invoke(
        Request $request,
        OwnerAnalytics $analytics,
        AnalyticsCsvStreamer $streamer,
    ): StreamedResponse {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        return $streamer->stream($user, $analytics->windowDays($user, $request->query('window')));
    }
}
