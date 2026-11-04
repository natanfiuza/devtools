<?php

namespace App\Support\BadgeForge\Providers\Clients;

use App\Models\BadgeProviderDefinition;
use App\Support\BadgeForge\Providers\ProviderFetchException;
use App\Support\BadgeForge\Providers\ProviderFetchRequest;

/**
 * Laravel: versao e downloads totais do proprio framework (F6, ADR-085).
 *
 * `v` e `dt` compartilham a mesma URL (`stats.json`) e, portanto, a mesma
 * entrada de cache: o cliente projeta **as duas** metricas em toda resposta,
 * senao a segunda definicao receberia um HIT sem a metrica pedida.
 *
 * `versions[]` comeca pelos dev releases (`13.x-dev`); a versao e a primeira
 * entrada que nao termina em `-dev`. Sem versao estavel, so `version` fica
 * indisponivel e `downloads` continua valendo.
 */
final class LaravelProviderClient extends AbstractProviderClient
{
    public function key(): string
    {
        return 'laravel';
    }

    public function supportsPrivate(BadgeProviderDefinition $definition): bool
    {
        return false;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, int|string|null>
     */
    protected function project(array $data, ProviderFetchRequest $request): array
    {
        return [
            'version' => $this->latestStableVersion($data),
            'downloads' => $this->downloads($data),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function latestStableVersion(array $data): ?string
    {
        $versions = $data['versions'] ?? null;

        if (! is_array($versions)) {
            return null;
        }

        foreach ($versions as $version) {
            if (! is_string($version) || trim($version) === '') {
                continue;
            }

            if (str_ends_with($version, '-dev')) {
                continue;
            }

            return $version;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function downloads(array $data): int
    {
        $downloads = $data['downloads'] ?? null;

        if (! is_array($downloads)) {
            throw new ProviderFetchException(ProviderFetchException::INVALID_RESPONSE);
        }

        return $this->integer($downloads, 'total');
    }
}
