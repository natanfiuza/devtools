<?php

namespace App\Actions\BadgeForge;

use App\Models\BadgeAsset;
use App\Models\User;
use App\Support\BadgeForge\Personal\PersonalBadgeGuard;
use App\Support\BadgeForge\Personal\PersonalBadgeRenderer;
use App\Support\BadgeForge\PresetSpecNormalizer;
use App\Support\BadgeForge\Providers\ProviderCatalog;
use App\Support\BadgeForge\Providers\ProviderCredential;
use App\Support\BadgeForge\Providers\ProviderFetchException;
use App\Support\BadgeForge\Providers\ProviderPair;
use App\Support\BadgeForge\Providers\ProviderParam;
use App\Support\BadgeForge\Providers\ProviderPathParser;
use App\Support\BadgeForge\Providers\ProviderUrlBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Cria uma badge pessoal (F4, ADR-081).
 *
 * `static` grava o snapshot normalizado e renderiza o SVG inicial.
 * `provider` grava provider/params/credencial **sem fetch**: o primeiro GET
 * publico gera a imagem, para que um provider fora do ar nao bloqueie a
 * criacao. O PAT nunca e devolvido; ele so existe cifrado na linha.
 */
final class CreateBadgeAsset
{
    public function __construct(
        private readonly PersonalBadgeGuard $guard,
        private readonly PresetSpecNormalizer $normalizer,
        private readonly ProviderCatalog $catalog,
        private readonly ProviderPathParser $parser,
        private readonly ProviderUrlBuilder $urlBuilder,
        private readonly PersonalBadgeRenderer $renderer,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function __invoke(User $user, array $data): BadgeAsset
    {
        $this->guard->ensurePaid($user);
        $this->guard->ensureUsername($user);

        $type = ($data['type'] ?? 'static') === 'provider' ? 'provider' : 'static';

        return DB::transaction(function () use ($user, $data, $type): BadgeAsset {
            $form = $this->form($user, is_array($data['form'] ?? null) ? $data['form'] : []);
            $name = (string) ($data['name'] ?? '');

            if ($type === 'static') {
                $asset = $user->badgeAssets()->create([
                    'name' => $name,
                    'type' => 'static',
                    'private' => false,
                    'form' => $form,
                ]);

                $this->renderer->render($asset);

                return $asset->refresh();
            }

            $pair = $this->pair($data);
            $params = $this->params($pair, $data['params'] ?? null);

            // Valida a URL sem sair para a rede; ela e refeita a cada fetch.
            try {
                $this->urlBuilder->build($pair, $params);
            } catch (ProviderFetchException) {
                $this->guard->fail('params', 'validation.badge_asset.params_invalid');
            }

            $private = (bool) ($data['private'] ?? false);
            $credential = trim((string) ($data['credential'] ?? ''));
            $credentialUsername = trim((string) ($data['credential_username'] ?? ''));
            $credentialUsername = $credentialUsername === '' ? null : $credentialUsername;

            $this->validateCredential($pair, $private, $credential, $credentialUsername);

            $credentialValue = $private
                ? new ProviderCredential($credential, $credentialUsername)
                : null;

            return $user->badgeAssets()->create([
                'name' => $name,
                'type' => 'provider',
                'private' => $private,
                'provider_slug' => $pair->provider->slug,
                'definition_slug' => $pair->definition->slug,
                'params' => $params,
                'form' => $form,
                'credential' => $credentialValue?->token,
                'credential_username' => $credentialValue?->username,
                'credential_fingerprint' => $credentialValue?->fingerprint((string) $pair->provider->slug),
                'credential_set_at' => $credentialValue !== null ? now() : null,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $form
     * @return array<string, mixed>
     */
    private function form(User $user, array $form): array
    {
        return $this->normalizer->normalize($form, $user);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function pair(array $data): ProviderPair
    {
        $provider = $data['provider_slug'] ?? null;
        $definition = $data['definition_slug'] ?? null;

        $pair = is_string($provider) && is_string($definition)
            ? $this->catalog->pair($provider, $definition)
            : null;

        if ($pair === null) {
            $this->guard->fail('provider_slug', 'validation.badge_asset.provider_unknown');
        }

        return $pair;
    }

    /**
     * @return array<string, string>
     */
    private function params(ProviderPair $pair, mixed $raw): array
    {
        if (! is_array($raw)) {
            $this->guard->fail('params', 'validation.badge_asset.params_invalid');
        }

        $definitions = ProviderParam::listFrom($pair->definition->required_params);

        if ($definitions === null) {
            $this->guard->fail('params', 'validation.badge_asset.params_invalid');
        }

        $values = [];
        $parts = [];

        foreach ($definitions as $param) {
            $value = $raw[$param->name] ?? null;

            if (! is_string($value) || $value === '') {
                $this->guard->fail('params', 'validation.badge_asset.params_invalid');
            }

            $values[$param->name] = $value;
            $parts[] = $value;
        }

        if (array_diff(array_keys($raw), array_keys($values)) !== []) {
            $this->guard->fail('params', 'validation.badge_asset.params_invalid');
        }

        // Definicao sem coordenadas usa o proprio `path_pattern` como literal
        // (ex.: `latest`); `implode('/', [])` seria string vazia e o parser
        // recusaria (F6, ADR-085).
        $parsed = $this->parser->parse(
            $pair->definition,
            $definitions === [] ? (string) $pair->definition->path_pattern : implode('/', $parts),
        );

        if ($parsed === null) {
            $this->guard->fail('params', 'validation.badge_asset.params_invalid');
        }

        return $parsed;
    }

    private function validateCredential(
        ProviderPair $pair,
        bool $private,
        string $credential,
        ?string $credentialUsername,
    ): void {
        if (! $private) {
            if ($credential !== '') {
                $this->guard->fail('credential', 'validation.badge_asset.credential_not_allowed');
            }

            return;
        }

        if (! $pair->definition->supports_private) {
            $this->guard->fail('private', 'validation.badge_asset.private_unsupported');
        }

        if ($credential === '') {
            $this->guard->fail('credential', 'validation.badge_asset.credential_required');
        }

        if ($pair->provider->auth_type === 'basic' && $credentialUsername === null) {
            $this->guard->fail(
                'credential_username',
                'validation.badge_asset.credential_username_required',
            );
        }
    }
}
