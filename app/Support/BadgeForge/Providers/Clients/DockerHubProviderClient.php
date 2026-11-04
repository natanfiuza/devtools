<?php

namespace App\Support\BadgeForge\Providers\Clients;

use App\Models\BadgeProviderDefinition;
use App\Support\BadgeForge\Providers\ProviderCredential;
use App\Support\BadgeForge\Providers\ProviderFetchException;
use App\Support\BadgeForge\Providers\ProviderFetchRequest;
use Illuminate\Http\Client\PendingRequest;

/**
 * Docker Hub: pulls. Repositorio privado usa Basic `username:token`; o
 * username e nao-secreto e fica em `credential_username` (F4, ADR-081).
 */
final class DockerHubProviderClient extends AbstractProviderClient
{
    public function key(): string
    {
        return 'docker';
    }

    public function supportsPrivate(BadgeProviderDefinition $definition): bool
    {
        return (bool) $definition->supports_private;
    }

    protected function authenticate(PendingRequest $pending, ProviderCredential $credential): PendingRequest
    {
        $username = $credential->username;

        if (! is_string($username) || $username === '') {
            throw new ProviderFetchException(ProviderFetchException::UNAUTHORIZED);
        }

        return $pending->withBasicAuth($username, $credential->token);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, int>
     */
    protected function project(array $data, ProviderFetchRequest $request): array
    {
        return ['pulls' => $this->integer($data, 'pull_count')];
    }
}
