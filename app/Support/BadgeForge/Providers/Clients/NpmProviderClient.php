<?php

namespace App\Support\BadgeForge\Providers\Clients;

use App\Models\BadgeProviderDefinition;
use App\Support\BadgeForge\Providers\ProviderCredential;
use App\Support\BadgeForge\Providers\ProviderFetchException;
use App\Support\BadgeForge\Providers\ProviderFetchRequest;
use Illuminate\Http\Client\PendingRequest;

/**
 * npm: `v` vem do registry (aceita pacote privado com Bearer) e `dm` da API
 * de downloads, que so enxerga pacotes publicos (F4, ADR-080).
 */
final class NpmProviderClient extends AbstractProviderClient
{
    public function key(): string
    {
        return 'npm';
    }

    public function supportsPrivate(BadgeProviderDefinition $definition): bool
    {
        return (bool) $definition->supports_private && $definition->extractor_key === 'version';
    }

    protected function authenticate(PendingRequest $pending, ProviderCredential $credential): PendingRequest
    {
        return $pending->withToken($credential->token);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, int|string>
     */
    protected function project(array $data, ProviderFetchRequest $request): array
    {
        return match ($request->pair->definition->extractor_key) {
            'version' => ['version' => $this->text($data, 'version')],
            'downloads' => ['downloads' => $this->integer($data, 'downloads')],
            default => throw new ProviderFetchException(ProviderFetchException::INVALID_RESPONSE),
        };
    }
}
