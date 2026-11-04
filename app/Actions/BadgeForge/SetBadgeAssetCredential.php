<?php

namespace App\Actions\BadgeForge;

use App\Models\BadgeAsset;
use App\Models\User;
use App\Support\BadgeForge\Personal\BadgeAssetStorage;
use App\Support\BadgeForge\Personal\PersonalBadgeGuard;
use App\Support\BadgeForge\Providers\ProviderCatalog;
use App\Support\BadgeForge\Providers\ProviderCredential;

/**
 * Define ou troca o PAT de uma badge pessoal privada (F4, ADR-081).
 *
 * O token entra cifrado e o fingerprint HMAC e recalculado; os arquivos sao
 * apagados para que a proxima leitura use a nova credencial. Nao existe
 * "remover credencial": uma badge privada nunca fica sem PAT - revogar e
 * excluir a badge e recriar.
 */
final class SetBadgeAssetCredential
{
    public function __construct(
        private readonly PersonalBadgeGuard $guard,
        private readonly ProviderCatalog $catalog,
        private readonly BadgeAssetStorage $storage,
    ) {}

    public function __invoke(
        User $user,
        BadgeAsset $asset,
        string $credential,
        ?string $credentialUsername,
    ): BadgeAsset {
        $this->guard->ensurePaid($user);

        if (! $asset->isProvider() || ! $asset->private) {
            $this->guard->fail('credential', 'validation.badge_asset.credential_not_allowed');
        }

        $pair = is_string($asset->provider_slug) && is_string($asset->definition_slug)
            ? $this->catalog->pair($asset->provider_slug, $asset->definition_slug)
            : null;

        if ($pair === null || ! $pair->definition->supports_private) {
            $this->guard->fail('private', 'validation.badge_asset.private_unsupported');
        }

        $credential = trim($credential);

        if ($credential === '') {
            $this->guard->fail('credential', 'validation.badge_asset.credential_required');
        }

        $credentialUsername = $credentialUsername === null ? null : trim($credentialUsername);
        $credentialUsername = $credentialUsername === '' ? null : $credentialUsername;

        if ($pair->provider->auth_type === 'basic' && $credentialUsername === null) {
            $this->guard->fail(
                'credential_username',
                'validation.badge_asset.credential_username_required',
            );
        }

        $value = new ProviderCredential($credential, $credentialUsername);

        $this->storage->delete($asset->svg_path);
        $this->storage->delete($asset->png_path);

        $asset->forceFill([
            'credential' => $value->token,
            'credential_username' => $value->username,
            'credential_fingerprint' => $value->fingerprint((string) $pair->provider->slug),
            'credential_set_at' => now(),
            'svg_path' => null,
            'png_path' => null,
            'etag' => null,
            'bytes' => null,
            'last_generated_at' => null,
            'expires_at' => null,
        ])->save();

        return $asset;
    }
}
