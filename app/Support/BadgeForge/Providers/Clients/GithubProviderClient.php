<?php

namespace App\Support\BadgeForge\Providers\Clients;

use App\Models\BadgeProviderDefinition;
use App\Support\BadgeForge\Providers\ProviderCredential;
use App\Support\BadgeForge\Providers\ProviderFetchRequest;
use Illuminate\Http\Client\PendingRequest;

/**
 * GitHub REST: uma chamada a `/repos/{owner}/{repo}` projeta stars, forks,
 * issues e license; as quatro definicoes compartilham a mesma entrada de
 * cache porque a URL resolvida e a mesma (F4, ADR-080).
 *
 * `open_issues_count` inclui pull requests abertos — comportamento da API,
 * documentado na pagina publica.
 */
final class GithubProviderClient extends AbstractProviderClient
{
    public function key(): string
    {
        return 'github';
    }

    public function supportsPrivate(BadgeProviderDefinition $definition): bool
    {
        return (bool) $definition->supports_private;
    }

    /**
     * @return array<string, string>
     */
    protected function headers(ProviderFetchRequest $request): array
    {
        return [
            'Accept' => 'application/vnd.github+json',
            'X-GitHub-Api-Version' => '2022-11-28',
        ];
    }

    protected function authenticate(PendingRequest $pending, ProviderCredential $credential): PendingRequest
    {
        return $pending->withToken($credential->token);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, int|string|null>
     */
    protected function project(array $data, ProviderFetchRequest $request): array
    {
        $license = $data['license'] ?? null;

        return [
            'stars' => $this->integer($data, 'stargazers_count'),
            'forks' => $this->integer($data, 'forks_count'),
            'issues' => $this->integer($data, 'open_issues_count'),
            'license' => is_array($license) && is_string($license['spdx_id'] ?? null)
                ? $license['spdx_id']
                : null,
        ];
    }
}
