<?php

namespace App\Http\Controllers\Api\V1\BadgeForge;

use App\Http\Controllers\Api\V1\Concerns\ResolvesApiIdentity;
use App\Http\Controllers\Controller;
use App\Support\ApiError;
use App\Support\BadgeForge\Analytics\OwnerAnalytics;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Analytics da conta na API de gestao (F9, ADR-090).
 *
 * Espelha o painel: Free fica nos 30 dias com totais e contagem por imagem;
 * pago escolhe 7/30/90 e ganha serie, referrers, paises, formatos e cache. A
 * janela invalida cai no padrao em vez de 422. O CSV continua so na web
 * (session-based), por isso `exportUrl` sai da resposta.
 */
final class AnalyticsController extends Controller
{
    use ResolvesApiIdentity;

    /**
     * Tabela ausente ou banco fora respondem 503 `badge_unavailable`. O
     * renderer global so cobre `api/badges/*` e `b/*`; sem o `try/catch` aqui
     * uma `QueryException` viraria 500.
     */
    public function index(Request $request, OwnerAnalytics $analytics): JsonResponse
    {
        try {
            $payload = $analytics->for($this->apiUser($request), $request->query('window'));
        } catch (QueryException) {
            return ApiError::badgeUnavailable();
        }

        unset($payload['exportUrl']);

        return response()->json(['data' => $payload]);
    }
}
