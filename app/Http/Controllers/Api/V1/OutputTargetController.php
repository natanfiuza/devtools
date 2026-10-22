<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\OutputTarget;
use Illuminate\Http\JsonResponse;

/**
 * Alvos de saida habilitados (ADR-050).
 *
 * O catalogo e dado: o cliente descobre aqui o `slug` que aceita no POST e os
 * dialetos que aquele alvo cobre. Alvo desligado pelo admin nao aparece, e o
 * POST nele responde 422 como na web.
 */
final class OutputTargetController extends Controller
{
    public function index(): JsonResponse
    {
        $targets = OutputTarget::query()
            ->enabled()
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->map(fn (OutputTarget $target): array => [
                'slug' => $target->slug,
                'label' => $target->label,
                'language' => $target->language,
                'supported_dialects' => $target->supported_dialects,
            ]);

        return response()->json(['data' => $targets]);
    }
}
