<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;

/**
 * Especificacao OpenAPI 3.1 da API publica v1 (`/openapi.yaml`, ADR-057).
 *
 * Servida a partir de `docs/openapi.yaml` para importacao direta em clientes
 * HTTP (Postman, Insomnia, Bruno, etc.).
 */
final class OpenApiController extends Controller
{
    public function __invoke(): Response
    {
        $path = base_path('docs/openapi.yaml');

        abort_unless(File::exists($path), 404);

        return response(File::get($path), 200, [
            'Content-Type' => 'application/yaml; charset=UTF-8',
        ]);
    }
}
