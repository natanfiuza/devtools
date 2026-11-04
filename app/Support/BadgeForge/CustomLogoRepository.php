<?php

namespace App\Support\BadgeForge;

use App\Models\BadgeCustomLogo;
use Illuminate\Database\QueryException;

/**
 * Resolve `u_<ULID>` -> logo personalizado na renderizacao publica (ADR-078).
 *
 * Nao exige autenticacao: a URL carrega apenas o ULID opaco. Qualquer falha
 * (slug invalido, registro apagado, JSON corrompido, banco indisponivel) vira
 * `null` e o badge segue sem logo, preservando o contrato de que parametros
 * cosmeticos invalidos nunca derrubam o endpoint.
 *
 * O resultado e memoizado por request, inclusive o "nao encontrado".
 */
final class CustomLogoRepository
{
    private const SLUG_PATTERN = '/^u_([0-9A-HJKMNP-TV-Z]{26})$/i';

    /** @var array<string, Logo|null> */
    private array $cache = [];

    private bool $unavailable = false;

    public function find(mixed $slug): ?Logo
    {
        if (! is_string($slug)) {
            return null;
        }

        $slug = strtolower(trim($slug));

        if (preg_match(self::SLUG_PATTERN, $slug, $matches) !== 1) {
            return null;
        }

        if (array_key_exists($slug, $this->cache)) {
            return $this->cache[$slug];
        }

        if ($this->unavailable) {
            return $this->cache[$slug] = null;
        }

        $id = strtoupper($matches[1]);

        try {
            $model = BadgeCustomLogo::query()->find($id);
        } catch (QueryException) {
            // Migracao ausente ou banco fora: o endpoint publico continua 200.
            $this->unavailable = true;

            return $this->cache[$slug] = null;
        }

        if (! $model instanceof BadgeCustomLogo) {
            return $this->cache[$slug] = null;
        }

        return $this->cache[$slug] = $this->hydrate($model);
    }

    /**
     * Revalida o JSON persistido na leitura: o banco so recebe dados do parser,
     * mas uma linha corrompida jamais deve chegar ao renderer. Usado tambem
     * pelo endpoint autenticado que entrega os paths sob demanda.
     */
    public function hydrate(BadgeCustomLogo $model): ?Logo
    {
        $viewBox = CustomLogoSvgParser::normalizeViewBox($model->view_box);

        if ($viewBox === null) {
            return null;
        }

        // Atributo cru: o PHPDoc do model descreve o formato esperado, mas e o
        // banco que responde — a revalidacao abaixo trata o valor como mixed.
        $rawPaths = $model->getAttribute('paths');

        if (! is_array($rawPaths) || $rawPaths === []) {
            return null;
        }

        $maxPaths = (int) config('badgeforge.custom_logos.max_paths', 200);
        $maxPathLength = (int) config('badgeforge.custom_logos.max_path_length', 4000);
        $maxTotalLength = (int) config('badgeforge.custom_logos.max_total_path_length', 100000);

        if (count($rawPaths) > $maxPaths) {
            return null;
        }

        $paths = [];
        $totalLength = 0;

        foreach ($rawPaths as $rawPath) {
            if (! is_array($rawPath)) {
                return null;
            }

            $data = $rawPath['d'] ?? null;

            if (! is_string($data) || ! CustomLogoSvgParser::isSafePathData($data)) {
                return null;
            }

            if (strlen($data) > $maxPathLength) {
                return null;
            }

            $totalLength += strlen($data);

            if ($totalLength > $maxTotalLength) {
                return null;
            }

            $fillRule = $rawPath['fillRule'] ?? null;
            $clipRule = $rawPath['clipRule'] ?? null;

            if (! self::isRule($fillRule) || ! self::isRule($clipRule)) {
                return null;
            }

            $paths[] = new LogoPath(
                $data,
                is_string($fillRule) ? $fillRule : null,
                is_string($clipRule) ? $clipRule : null,
            );
        }

        return new Logo(
            slug: 'u_'.$model->getKey(),
            name: (string) $model->name,
            viewBox: $viewBox,
            paths: $paths,
            custom: true,
        );
    }

    private static function isRule(mixed $value): bool
    {
        return $value === null || (is_string($value) && in_array($value, ['nonzero', 'evenodd'], true));
    }
}
