<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;

/**
 * Especificacoes OpenAPI 3.1 servidas como YAML (ADR-057, ADR-071).
 *
 * `/openapi.yaml` expoe a API publica v1 a partir de `docs/openapi.yaml`; o
 * BadgeForge tem a sua propria spec em `docs/badgeforge/openapi.yaml`,
 * servida em `/openapi/badges.yaml` e importavel em clientes HTTP (Postman,
 * Insomnia, Bruno, etc.).
 */
final class OpenApiController extends Controller
{
    public function __invoke(): Response
    {
        return self::yaml(base_path('docs/openapi.yaml'));
    }

    public function badges(): Response
    {
        $path = base_path('docs/badgeforge/openapi.yaml');

        abort_unless(File::exists($path), 404);

        // A spec e autoral e versionada no repositorio; so o servidor varia
        // por ambiente. O marcador evita duplicar o dominio no arquivo.
        $yaml = str_replace(
            '%APP_URL%',
            rtrim((string) config('app.url'), '/'),
            File::get($path),
        );

        return response($yaml, 200, [
            'Content-Type' => 'application/yaml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    private static function yaml(string $path): Response
    {
        abort_unless(File::exists($path), 404);

        return response(File::get($path), 200, [
            'Content-Type' => 'application/yaml; charset=UTF-8',
        ]);
    }
}
