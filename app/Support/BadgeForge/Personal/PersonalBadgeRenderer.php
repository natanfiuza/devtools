<?php

namespace App\Support\BadgeForge\Personal;

use App\Models\BadgeAsset;
use App\Support\BadgeForge\BadgeSpec;
use App\Support\BadgeForge\BadgeSpecBuilder;
use App\Support\BadgeForge\PngRenderer;
use App\Support\BadgeForge\PresetSpecNormalizer;
use App\Support\BadgeForge\Providers\ProviderCatalog;
use App\Support\BadgeForge\Providers\ProviderCredential;
use App\Support\BadgeForge\Providers\ProviderFetchRequest;
use App\Support\BadgeForge\Providers\ProviderMetricFormatter;
use App\Support\BadgeForge\Providers\ProviderMetrics;
use App\Support\BadgeForge\Providers\ProviderPair;
use App\Support\BadgeForge\Providers\ProviderUrlBuilder;
use App\Support\BadgeForge\StaticBadgeRenderer;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Renderiza e persiste a imagem de uma badge pessoal (F4, ADR-081).
 *
 * `static` e um snapshot do formulario: o dono so renomeia, entao o SVG pode
 * ser servido do disco indefinidamente. `provider` consulta o cache de
 * provider so quando o arquivo vence; se o provider falhar, serve o ultimo
 * arquivo bom (stale) ou um fallback 200. Um lock por badge evita que N
 * requisicoes simultaneas disparem N fetches.
 */
final class PersonalBadgeRenderer
{
    public function __construct(
        private readonly BadgeAssetStorage $storage,
        private readonly PresetSpecNormalizer $normalizer,
        private readonly BadgeSpecBuilder $builder,
        private readonly StaticBadgeRenderer $renderer,
        private readonly PngRenderer $png,
        private readonly ProviderCatalog $catalog,
        private readonly ProviderUrlBuilder $urlBuilder,
        private readonly ProviderMetrics $metrics,
        private readonly ProviderMetricFormatter $formatter,
    ) {}

    public function render(BadgeAsset $asset, bool $force = false): PersonalBadge
    {
        $asset->loadMissing('user');

        $cachedSvg = $this->storage->read($asset->svg_path);
        $cachedPng = $this->storage->read($asset->png_path);
        $fresh = ! $force
            && $cachedSvg !== null
            && $asset->expires_at !== null
            && $asset->expires_at->isFuture();

        if ($fresh) {
            return new PersonalBadge($this->cosmeticSpec($asset), $cachedSvg, $cachedPng, false);
        }

        $lock = $this->acquire($asset);

        if (! $lock) {
            if ($cachedSvg !== null) {
                return new PersonalBadge($this->cosmeticSpec($asset), $cachedSvg, $cachedPng, true);
            }

            $spec = $this->fallbackSpec($asset);

            return new PersonalBadge($spec, $this->renderer->render($spec), null, true);
        }

        try {
            $resolved = $this->resolveSpec($asset);

            if ($resolved['failed'] && $cachedSvg !== null) {
                $this->expireSoon($asset);

                return new PersonalBadge($this->cosmeticSpec($asset), $cachedSvg, $cachedPng, true);
            }

            $spec = $resolved['spec'];
            $svg = $this->renderer->render($spec);
            $png = $this->renderPng($svg);

            $this->persist($asset, $spec, $svg, $png);

            return new PersonalBadge($spec, $svg, $png, false);
        } finally {
            if ($lock instanceof Lock) {
                try {
                    $lock->release();
                } catch (Throwable) {
                    // Lock expirado: nada a fazer.
                }
            }
        }
    }

    /**
     * Spec sem consultar o provider; usada quando o arquivo em disco ainda
     * esta fresco. So os headers (TTL e nome do download) dependem dela.
     */
    private function cosmeticSpec(BadgeAsset $asset): BadgeSpec
    {
        if (! $asset->isProvider()) {
            return $this->staticSpec($asset);
        }

        $pair = $this->pair($asset);
        $ttl = $this->cap($pair?->provider->effectiveCacheTtl()
            ?? (int) config('badgeforge.providers.cache_ttl', 300));

        return $this->providerSpec(
            $asset,
            $pair?->definition->label ?? (string) $asset->definition_slug,
            '…',
            $ttl,
        );
    }

    /**
     * @return array{spec: BadgeSpec, failed: bool}
     */
    private function resolveSpec(BadgeAsset $asset): array
    {
        if (! $asset->isProvider()) {
            return ['spec' => $this->staticSpec($asset), 'failed' => false];
        }

        $pair = $this->pair($asset);

        if ($pair === null) {
            return ['spec' => $this->fallbackSpec($asset), 'failed' => true];
        }

        $request = null;

        try {
            $url = $this->urlBuilder->build($pair, $this->params($asset));

            $request = new ProviderFetchRequest(
                pair: $pair,
                params: $this->params($asset),
                url: $url,
                credential: $this->credential($asset),
            );
        } catch (Throwable) {
            return ['spec' => $this->fallbackSpec($asset), 'failed' => true];
        }

        $result = $this->metrics->get($request);

        $message = $this->formatter->format($result->value, $pair->definition->extractor_key)
            ?? (string) config('badgeforge.providers.unavailable_message', 'unavailable');

        return [
            'spec' => $this->providerSpec(
                $asset,
                $pair->definition->label,
                $message,
                $this->cap($result->ttl),
            ),
            'failed' => $result->value === null,
        ];
    }

    private function staticSpec(BadgeAsset $asset): BadgeSpec
    {
        $form = $this->form($asset);
        $ttl = $this->cap(isset($form['cacheSeconds']) ? (int) $form['cacheSeconds'] : 0);

        return $this->builder->build(
            (string) ($form['label'] ?? ''),
            (string) ($form['message'] ?? ''),
            $form,
            $ttl,
        );
    }

    private function providerSpec(
        BadgeAsset $asset,
        string $label,
        string $message,
        int $ttl,
    ): BadgeSpec {
        return $this->builder->build($label, $message, $this->form($asset), $ttl);
    }

    private function fallbackSpec(BadgeAsset $asset): BadgeSpec
    {
        $pair = $this->pair($asset);
        $label = $pair?->definition->label ?? (string) $asset->definition_slug;

        return $this->providerSpec(
            $asset,
            $label,
            (string) config('badgeforge.providers.unavailable_message', 'unavailable'),
            $this->cap((int) config('badgeforge.providers.fallback_ttl', 60)),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function form(BadgeAsset $asset): array
    {
        /** @var array<string, mixed> $raw */
        $raw = is_array($asset->form) ? $asset->form : [];

        try {
            return $this->normalizer->normalize($raw, $asset->user);
        } catch (Throwable) {
            // Snapshot antigo/corrompido (logo excluido, usuario ilegivel):
            // o builder e tolerante, entao o formulario cru e suficiente.
            return $raw;
        }
    }

    /**
     * @return array<string, string>
     */
    private function params(BadgeAsset $asset): array
    {
        if (! is_array($asset->params)) {
            return [];
        }

        $params = [];

        foreach ($asset->params as $key => $value) {
            $params[$key] = $value;
        }

        return $params;
    }

    private function pair(BadgeAsset $asset): ?ProviderPair
    {
        if (! is_string($asset->provider_slug) || ! is_string($asset->definition_slug)) {
            return null;
        }

        return $this->catalog->pair($asset->provider_slug, $asset->definition_slug);
    }

    /**
     * Uma credencial ilegivel (APP_KEY trocada) degrada para anonima: a badge
     * privada cai no stale/fallback, nunca em 500.
     */
    private function credential(BadgeAsset $asset): ?ProviderCredential
    {
        if (! $asset->private) {
            return null;
        }

        $ciphertext = $asset->getRawOriginal('credential');

        if ($ciphertext === '') {
            return null;
        }

        try {
            $token = Crypt::decryptString($ciphertext);
        } catch (DecryptException) {
            Log::warning('badgeforge: credencial de badge pessoal ilegivel.');

            return null;
        }

        if ($token === '') {
            return null;
        }

        return new ProviderCredential($token, $asset->credential_username);
    }

    private function persist(BadgeAsset $asset, BadgeSpec $spec, string $svg, ?string $png): void
    {
        $svgPath = $asset->svg_path ?? $this->storage->pathFor($asset, 'svg');
        $pngPath = $asset->png_path ?? $this->storage->pathFor($asset, 'png');

        if ($svgPath !== null) {
            $this->storage->write($svgPath, $svg);
        }

        if ($pngPath !== null && $png !== null) {
            $this->storage->write($pngPath, $png);
        }

        $asset->forceFill([
            'svg_path' => $svgPath,
            'png_path' => $png !== null ? $pngPath : null,
            'etag' => hash('sha256', $svg),
            'bytes' => strlen($svg),
            'last_generated_at' => now(),
            'expires_at' => now()->addSeconds(max(1, $spec->cacheSeconds)),
        ])->save();
    }

    private function expireSoon(BadgeAsset $asset): void
    {
        $asset->forceFill([
            'expires_at' => now()->addSeconds(
                max(1, (int) config('badgeforge.providers.fallback_ttl', 60)),
            ),
        ])->save();
    }

    private function renderPng(string $svg): ?string
    {
        if (! $this->png->isSupported()) {
            return null;
        }

        try {
            return $this->png->render($svg);
        } catch (Throwable) {
            Log::warning('badgeforge: falha ao rasterizar PNG da badge pessoal.');

            return null;
        }
    }

    private function cap(int $ttl): int
    {
        return max(1, min(
            $ttl,
            (int) config('badgeforge.personal_badges.max_cache_seconds', 3600),
        ));
    }

    /**
     * `Lock` adquirido, `false` quando outra requisicao esta regenerando e
     * `true` quando o store de lock esta fora do ar (segue sem exclusao).
     */
    private function acquire(BadgeAsset $asset): Lock|bool
    {
        try {
            $lock = Cache::lock('badgeforge:personal:'.$asset->getKey().':lock', 10);

            return $lock->get() ? $lock : false;
        } catch (Throwable) {
            return true;
        }
    }
}
