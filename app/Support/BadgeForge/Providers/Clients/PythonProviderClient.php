<?php

namespace App\Support\BadgeForge\Providers\Clients;

use App\Models\BadgeProviderDefinition;
use App\Support\BadgeForge\Providers\ProviderFetchException;
use App\Support\BadgeForge\Providers\ProviderFetchRequest;

/**
 * Python: versao estavel mais recente via endoflife.date (F6, ADR-085).
 *
 * A lista vem ordenada por ciclo; o primeiro item carrega `latest`. O catalogo
 * do python.org nao expoe um campo `latest` limpo, por isso a fonte externa.
 */
final class PythonProviderClient extends AbstractProviderClient
{
    public function key(): string
    {
        return 'python';
    }

    public function supportsPrivate(BadgeProviderDefinition $definition): bool
    {
        return false;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    protected function project(array $data, ProviderFetchRequest $request): array
    {
        if ($request->pair->definition->extractor_key !== 'version') {
            throw new ProviderFetchException(ProviderFetchException::INVALID_RESPONSE);
        }

        $item = reset($data);

        if (! is_array($item)) {
            throw new ProviderFetchException(ProviderFetchException::INVALID_RESPONSE);
        }

        return ['version' => $this->text($item, 'latest')];
    }
}
