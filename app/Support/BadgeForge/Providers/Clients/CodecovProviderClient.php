<?php

namespace App\Support\BadgeForge\Providers\Clients;

use App\Models\BadgeProviderDefinition;
use App\Support\BadgeForge\Providers\ProviderFetchException;
use App\Support\BadgeForge\Providers\ProviderFetchRequest;

/**
 * Codecov: cobertura da branch padrao do repositorio (F6, ADR-085).
 *
 * O path publico e `{owner}/{repo}` e a API v2 sem `?branch=` ja responde pela
 * branch padrao. O valor projetado e o percentual bruto de `totals.coverage`;
 * o formatter arredonda e formata.
 */
final class CodecovProviderClient extends AbstractProviderClient
{
    public function key(): string
    {
        return 'codecov';
    }

    public function supportsPrivate(BadgeProviderDefinition $definition): bool
    {
        return false;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, float>
     */
    protected function project(array $data, ProviderFetchRequest $request): array
    {
        if ($request->pair->definition->extractor_key !== 'coverage') {
            throw new ProviderFetchException(ProviderFetchException::INVALID_RESPONSE);
        }

        $totals = $data['totals'] ?? null;

        if (! is_array($totals)) {
            throw new ProviderFetchException(ProviderFetchException::INVALID_RESPONSE);
        }

        return ['coverage' => $this->number($totals, 'coverage')];
    }
}
