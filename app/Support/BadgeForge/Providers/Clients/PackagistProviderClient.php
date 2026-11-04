<?php

namespace App\Support\BadgeForge\Providers\Clients;

use App\Models\BadgeProviderDefinition;
use App\Support\BadgeForge\Providers\ProviderFetchException;
use App\Support\BadgeForge\Providers\ProviderFetchRequest;

/**
 * Packagist: downloads totais e versao mais recente do metadado p2.
 * Sempre publico — o registry e a API nao exposem pacote privado (F4, ADR-080).
 */
final class PackagistProviderClient extends AbstractProviderClient
{
    public function key(): string
    {
        return 'packagist';
    }

    public function supportsPrivate(BadgeProviderDefinition $definition): bool
    {
        return false;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, int|string>
     */
    protected function project(array $data, ProviderFetchRequest $request): array
    {
        return match ($request->pair->definition->extractor_key) {
            'downloads' => ['downloads' => $this->downloads($data)],
            'version' => ['version' => $this->latestVersion($data, $request)],
            default => throw new ProviderFetchException(ProviderFetchException::INVALID_RESPONSE),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function downloads(array $data): int
    {
        // `stats.json` devolve `downloads` no topo. O fallback para
        // `package.downloads` mantem o parser tolerante a resposta completa,
        // mas a definicao usa o endpoint enxuto - o JSON completo passa de
        // `providers.max_body_bytes` em pacotes grandes.
        $downloads = $data['downloads'] ?? null;

        if (! is_array($downloads)) {
            $package = $data['package'] ?? null;
            $downloads = is_array($package) ? ($package['downloads'] ?? null) : null;
        }

        if (! is_array($downloads)) {
            throw new ProviderFetchException(ProviderFetchException::INVALID_RESPONSE);
        }

        return $this->integer($downloads, 'total');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function latestVersion(array $data, ProviderFetchRequest $request): string
    {
        $packages = $data['packages'] ?? null;

        if (! is_array($packages) || $packages === []) {
            throw new ProviderFetchException(ProviderFetchException::INVALID_RESPONSE);
        }

        $name = ($request->params['vendor'] ?? '').'/'.($request->params['package'] ?? '');
        $versions = $packages[$name] ?? reset($packages);

        if (! is_array($versions) || $versions === []) {
            throw new ProviderFetchException(ProviderFetchException::INVALID_RESPONSE);
        }

        $latest = $versions[0] ?? null;

        if (! is_array($latest)) {
            throw new ProviderFetchException(ProviderFetchException::INVALID_RESPONSE);
        }

        return $this->text($latest, 'version');
    }
}
