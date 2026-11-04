<?php

namespace App\Http\Controllers;

use App\Support\BadgeForge\Providers\ProviderCatalog;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Documentacao publica do BadgeForge (`/{locale}/docs/badgeforge`, ADR-082).
 *
 * Entrega o catalogo de providers com os exemplos de cada metrica, os tres
 * endpoints e o TTL de cache - o suficiente para montar, copiar e entender um
 * badge sem sair da pagina. Nenhuma credencial, nenhum asset de conta.
 */
final class BadgeForgeDocsController extends Controller
{
    public function __invoke(ProviderCatalog $catalog): Response
    {
        return Inertia::render('BadgeForgeDocs', [
            'providers' => $catalog->forDocs(),
            'staticEndpoint' => (string) config('badgeforge.endpoint', '/api/badges/static'),
            'providerEndpoint' => (string) config('badgeforge.provider_endpoint', '/api/badges'),
            'personalEndpoint' => (string) config('badgeforge.personal_endpoint', '/b'),
            'cacheTtl' => (int) config('badgeforge.providers.cache_ttl', 300),
            'origin' => rtrim(url('/'), '/'),
        ]);
    }
}
