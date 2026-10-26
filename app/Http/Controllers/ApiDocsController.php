<?php

namespace App\Http\Controllers;

use App\Models\OutputTarget;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pagina publica de documentacao da API v1 (`/{locale}/api-docs`, ADR-057).
 *
 * Entrega a URL base da aplicacao, o link para a especificacao OpenAPI, os
 * limites de taxa e paginacao configurados e a lista de alvos de saida
 * habilitados no momento.
 */
final class ApiDocsController extends Controller
{
    public function __invoke(): Response
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
                'supportedDialects' => $target->supported_dialects,
            ])
            ->values()
            ->all();

        return Inertia::render('ApiDocs', [
            'baseUrl' => rtrim((string) config('app.url'), '/').'/api/v1',
            'openApiUrl' => route('openapi'),
            'tokensSettingsUrl' => route('api-tokens.index'),
            'rateLimits' => [
                'postPerMinute' => (int) config('api.rate_limits.post_per_minute', 30),
                'postPerHour' => (int) config('api.rate_limits.post_per_hour', 300),
                'readPerMinute' => (int) config('api.rate_limits.read_per_minute', 120),
            ],
            'paginationPerPage' => (int) config('api.pagination.per_page', 20),
            'targets' => $targets,
        ]);
    }
}
