<?php

namespace App\Http\Controllers;

use App\Support\BadgeForge\Providers\ProviderCatalog;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Documentacao publica do BadgeForge (`/{locale}/docs/badgeforge`, ADR-082).
 *
 * Entrega o catalogo de providers com os exemplos de cada metrica, as quatro
 * rotas de imagem, o TTL de cache e os limites do endpoint JSON - o suficiente
 * para montar, copiar e entender um badge sem sair da pagina. Nenhuma
 * credencial, nenhum asset de conta e nenhuma URL de origem.
 *
 * A secao da API JSON (F8, ADR-088) aponta para a base da gestao, para a tela
 * de tokens e para a especificacao OpenAPI servida em `/openapi/badges.yaml`.
 */
final class BadgeForgeDocsController extends Controller
{
    public function __invoke(ProviderCatalog $catalog): Response
    {
        return Inertia::render('BadgeForgeDocs', [
            'providers' => $catalog->forDocs(),
            'staticEndpoint' => (string) config('badgeforge.static_endpoint', '/api/badges/static'),
            'providerEndpoint' => (string) config('badgeforge.provider_endpoint', '/api/badges'),
            'personalEndpoint' => (string) config('badgeforge.personal_endpoint', '/b'),
            'jsonEndpoint' => (string) config('badgeforge.endpoint.path', '/api/badges/endpoint'),
            'customEndpoint' => (string) config('badgeforge.custom_providers.path', '/api/badges/custom'),
            'cacheTtl' => (int) config('badgeforge.providers.cache_ttl', 300),
            'endpointCacheTtl' => (int) config('badgeforge.endpoint.cache_ttl', 300),
            'endpointRateLimits' => [
                'anonymous' => (int) config('badgeforge.endpoint.rate_limit.anonymous', 60),
                'free' => (int) config('badgeforge.endpoint.rate_limit.free', 300),
                'paid' => (int) config('badgeforge.endpoint.rate_limit.paid', 3000),
            ],
            'origin' => rtrim(url('/'), '/'),
            'jsonApiBaseUrl' => '/api/v1/badgeforge',
            'openApiUrl' => route('openapi.badges'),
            'tokensSettingsUrl' => route('api-tokens.index'),
        ]);
    }
}
