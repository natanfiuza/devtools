<?php

namespace App\Support\BadgeForge\Providers\Clients;

use App\Models\BadgeProviderDefinition;
use App\Support\BadgeForge\Providers\ProviderFetchException;
use App\Support\BadgeForge\Providers\ProviderFetchRequest;

/**
 * Coveralls: cobertura do ultimo build publicado (F6, ADR-085).
 *
 * O `.json` nao tem seletor de branch na URL usada; o valor projetado e
 * `covered_percent`, e o formatter arredonda e formata.
 */
final class CoverallsProviderClient extends AbstractProviderClient
{
    public function key(): string
    {
        return 'coveralls';
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

        return ['coverage' => $this->number($data, 'covered_percent')];
    }
}
