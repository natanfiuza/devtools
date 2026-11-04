<?php

namespace App\Support\BadgeForge\Providers;

/**
 * Credencial de provider em memoria (F4, ADR-081).
 *
 * O token nunca sai daqui por serializacao, debug, log ou excecao. O
 * fingerprint e um HMAC-SHA256 com `APP_KEY`, prefixado pelo slug do
 * provider e pelo username (quando existe, como no Docker), e e a unica
 * forma da credencial influenciar a chave de cache.
 */
final readonly class ProviderCredential
{
    public function __construct(
        public string $token,
        public ?string $username = null,
    ) {}

    public function fingerprint(string $providerSlug): string
    {
        return hash_hmac(
            'sha256',
            $providerSlug."\0".($this->username ?? '')."\0".$this->token,
            (string) config('app.key'),
        );
    }

    /**
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return ['username' => $this->username];
    }

    /**
     * @return array<string, string|null>
     */
    public function __debugInfo(): array
    {
        return ['token' => '[redacted]', 'username' => $this->username];
    }
}
