<?php

namespace App\Support\BadgeForge\Providers;

use App\Models\BadgeProvider;
use App\Models\BadgeProviderDefinition;
use Illuminate\Database\QueryException;

/**
 * Catalogo de providers habilitados, memoizado por request (F4, ADR-080).
 *
 * Durante o deploy o codigo novo roda antes da migracao: qualquer
 * `QueryException` vira catalogo vazio para que a tool e a docs publiquem 200
 * em vez de quebrar. `static` e slug reservado da rota estatica e nunca vira
 * provider.
 */
final class ProviderCatalog
{
    private const array RESERVED_SLUGS = ['static', 'endpoint', 'custom'];

    /**
     * @var array<string, BadgeProvider>|null
     */
    private ?array $providers = null;

    /**
     * @var array<string, array<int, BadgeProviderDefinition>>
     */
    private array $definitions = [];

    /**
     * @return list<BadgeProvider>
     */
    public function all(): array
    {
        return array_values($this->providers());
    }

    public function pair(string $providerSlug, string $definitionSlug): ?ProviderPair
    {
        $provider = $this->providers()[$providerSlug] ?? null;

        if (! $provider instanceof BadgeProvider) {
            return null;
        }

        foreach ($this->definitionsOf($provider) as $definition) {
            if ($definition->slug === $definitionSlug) {
                return new ProviderPair($provider, $definition);
            }
        }

        return null;
    }

    /**
     * Catalogo publico da tool e da pagina de docs, sem credenciais.
     *
     * @return list<array<string, mixed>>
     */
    public function forDocs(): array
    {
        $docs = [];

        foreach ($this->all() as $provider) {
            $definitions = [];

            foreach ($this->definitionsOf($provider) as $definition) {
                $params = ProviderParam::listFrom($definition->required_params);

                if ($params === null) {
                    continue;
                }

                $definitions[] = [
                    'slug' => $definition->slug,
                    'name' => $definition->name,
                    'label' => $definition->label,
                    'supportsPrivate' => $definition->supports_private,
                    'examplePath' => $definition->example_path,
                    'pathPattern' => $definition->path_pattern,
                    'params' => array_map(
                        static fn (ProviderParam $param): array => [
                            'name' => $param->name,
                            'required' => true,
                            'pattern' => $param->pattern,
                            'allowSlash' => $param->allowSlash,
                            'placeholderKey' => $param->placeholderKey,
                        ],
                        $params,
                    ),
                ];
            }

            $docs[] = [
                'slug' => $provider->slug,
                'name' => $provider->name,
                'category' => $provider->category,
                'requiresAuth' => $provider->requires_auth,
                // Docker privado usa Basic: o username da conta (non-secret)
                // acompanha o PAT (ADR-081).
                'requiresUsername' => $provider->auth_type === 'basic',
                'documentationUrl' => $provider->documentation_url,
                'definitions' => $definitions,
            ];
        }

        return $docs;
    }

    /**
     * @return array<string, BadgeProvider>
     */
    private function providers(): array
    {
        if ($this->providers !== null) {
            return $this->providers;
        }

        try {
            $models = BadgeProvider::query()
                ->enabled()
                ->orderBy('id')
                ->get();
        } catch (QueryException) {
            return $this->providers = [];
        }

        $providers = [];

        foreach ($models as $provider) {
            $slug = (string) $provider->slug;

            if (preg_match('/\A[a-z0-9][a-z0-9-]*\z/', $slug) !== 1) {
                continue;
            }

            if (in_array($slug, self::RESERVED_SLUGS, true)) {
                continue;
            }

            $providers[$slug] = $provider;
        }

        return $this->providers = $providers;
    }

    /**
     * @return array<int, BadgeProviderDefinition>
     */
    private function definitionsOf(BadgeProvider $provider): array
    {
        $slug = (string) $provider->slug;

        if (array_key_exists($slug, $this->definitions)) {
            return $this->definitions[$slug];
        }

        try {
            $definitions = $provider->definitions()
                ->enabled()
                ->orderBy('sort')
                ->orderBy('id')
                ->get()
                ->all();
        } catch (QueryException) {
            $definitions = [];
        }

        return $this->definitions[$slug] = $definitions;
    }
}
