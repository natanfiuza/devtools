<?php

namespace App\Support\BadgeForge\Providers;

use App\Models\BadgeProviderDefinition;
use App\Support\BadgeForge\Providers\Clients\DockerHubProviderClient;
use App\Support\BadgeForge\Providers\Clients\GithubProviderClient;
use App\Support\BadgeForge\Providers\Clients\NpmProviderClient;
use App\Support\BadgeForge\Providers\Clients\PackagistProviderClient;
use App\Support\BadgeForge\Providers\Clients\ProviderClient;
use Illuminate\Support\Facades\Log;

/**
 * Traduz `client_key` em um cliente conhecido (F4, ADR-080).
 *
 * O banco nunca nomeia classe ou metodo: `parser_class` fica inerte e uma
 * chave desconhecida resulta em 404/fallback, com log apenas de slugs.
 */
final class ProviderClientRegistry
{
    /**
     * @var array<string, ProviderClient>
     */
    private array $clients = [];

    public function __construct(
        GithubProviderClient $github,
        NpmProviderClient $npm,
        PackagistProviderClient $packagist,
        DockerHubProviderClient $docker,
    ) {
        foreach ([$github, $npm, $packagist, $docker] as $client) {
            $this->clients[$client->key()] = $client;
        }
    }

    public function for(BadgeProviderDefinition $definition): ?ProviderClient
    {
        $key = strtolower(trim((string) $definition->client_key));

        if ($key === '') {
            return null;
        }

        $client = $this->clients[$key] ?? null;

        if ($client instanceof ProviderClient) {
            return $client;
        }

        Log::warning('badgeforge.provider.unknown_client', [
            'provider_id' => $definition->provider_id,
            'definition' => $definition->slug,
            'client_key' => $key,
        ]);

        return null;
    }
}
