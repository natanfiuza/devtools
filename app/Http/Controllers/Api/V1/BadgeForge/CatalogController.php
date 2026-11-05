<?php

namespace App\Http\Controllers\Api\V1\BadgeForge;

use App\Http\Controllers\Controller;
use App\Support\BadgeForge\Providers\ProviderCatalog;
use App\Support\BadgeForge\StyleCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Catalogo publico do BadgeForge (F8, ADR-088).
 *
 * Providers e estilos sao anonimos: o builder do cliente precisa deles antes
 * de qualquer token. A resposta e cacheavel por `Cache-Control` publico e
 * carrega ETag do proprio corpo, para o cliente revalidar com 304.
 */
final class CatalogController extends Controller
{
    public function providers(Request $request, ProviderCatalog $catalog): JsonResponse
    {
        return self::cacheable($request, ['data' => $catalog->forDocs()]);
    }

    public function styles(Request $request, StyleCatalog $styles): JsonResponse
    {
        return self::cacheable($request, ['data' => $styles->ids()]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function cacheable(Request $request, array $payload): JsonResponse
    {
        $response = response()->json($payload);

        $response->headers->set(
            'Cache-Control',
            'public, max-age='.max(0, (int) config('badgeforge.management.catalog_cache_seconds', 300)),
        );

        $response->setEtag(hash('sha256', (string) $response->getContent()));
        $response->isNotModified($request);

        return $response;
    }
}
