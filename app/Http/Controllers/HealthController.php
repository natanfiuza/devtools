<?php

namespace App\Http\Controllers;

use App\Support\Health\HealthProbe;
use Illuminate\Http\JsonResponse;

/**
 * `/up` com dependencias reais (F8, ADR-089).
 *
 * O health check padrao do Laravel so prova que o PHP respondeu. Este aqui
 * consulta o banco, faz ida e volta no cache e grava um probe no disco do
 * PNG: saudavel = 200 `up`, qualquer falha = 503 `down` com o mapa das
 * sondas. A rota e anonima e stateless e fica fora da sessao.
 */
final class HealthController extends Controller
{
    public function __invoke(HealthProbe $probe): JsonResponse
    {
        $checks = $probe->run();
        $healthy = ! in_array(false, $checks, true);

        return response()->json(
            [
                'status' => $healthy ? 'up' : 'down',
                'checks' => $checks,
            ],
            $healthy ? 200 : 503,
            ['Cache-Control' => 'no-store'],
        );
    }
}
