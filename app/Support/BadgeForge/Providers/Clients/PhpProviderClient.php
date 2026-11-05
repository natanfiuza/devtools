<?php

namespace App\Support\BadgeForge\Providers\Clients;

use App\Models\BadgeProviderDefinition;
use App\Support\BadgeForge\Providers\ProviderFetchException;
use App\Support\BadgeForge\Providers\ProviderFetchRequest;

/**
 * PHP: versao estavel mais recente publicada no php.net (F6, ADR-085).
 *
 * `releases/index.php?json&max=1` devolve um objeto com a chave do major
 * (`"8"`) apontando para o release. `json_decode(..., true)` converte essa
 * chave numerica em int, por isso `reset()` em vez de acessar `"8"`.
 */
final class PhpProviderClient extends AbstractProviderClient
{
    public function key(): string
    {
        return 'php';
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

        $release = reset($data);

        if (! is_array($release)) {
            throw new ProviderFetchException(ProviderFetchException::INVALID_RESPONSE);
        }

        return ['version' => $this->text($release, 'version')];
    }
}
