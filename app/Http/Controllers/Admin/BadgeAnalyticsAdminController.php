<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\BadgeForge\Analytics\AdminAnalytics;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Volume global do BadgeForge (F7, ADR-086).
 *
 * Somente leitura: o numero vem do agregado diario, entao nao ha acao aqui.
 * A janela e validada contra `badgeforge.analytics.admin_windows` (7/30/90/400)
 * e valor estranho cai no padrao, nunca em 422.
 */
final class BadgeAnalyticsAdminController extends Controller
{
    public function index(Request $request, AdminAnalytics $analytics): Response
    {
        return Inertia::render('admin/BadgeAnalytics', [
            'analytics' => $analytics->for($request->query('window')),
        ]);
    }
}
