<?php

namespace App\Support\Colors;

use Illuminate\Validation\Validator;

/**
 * Validacao do estado persistido do Layout Harmonizer (F4, ADR-168).
 *
 * O cliente nao envia colunas de versao: o servidor deriva tudo do snapshot
 * validado. O shape e o da F3 (`lib/snapshot.ts`) mais as entradas cruas e a
 * secundaria manual. Um estado valido e exatamente o retrato que a tela
 * mostrava ao salvar; por isso a validacao e estrutural, nao um recalculo.
 */
final class LayoutSystemState
{
    /**
     * Acrescenta um unico erro `state` quando o payload nao e aceito.
     *
     * @param  mixed  $state
     */
    public function validate(Validator $validator, $state): void
    {
        if (! $this->valid($state)) {
            $validator->errors()->add('state', __('colors.validation.layout_state'));

            return;
        }

        /** @var array<string, mixed> $state */
        $encoded = json_encode($state);

        if ($encoded === false || strlen($encoded) > max(1, (int) config('colors.layout_systems.state_max_bytes', 262144))) {
            $validator->errors()->add('state', __('colors.validation.layout_state_too_large'));
        }
    }

    /**
     * Versoes e contagem derivadas do snapshot ja validado.
     *
     * @param  array<string, mixed>  $state
     * @return array{schema_version: int, algorithm_version: int, suggestions_version: int, snapshot_version: int, tokens_count: int}
     */
    public function derive(array $state): array
    {
        /** @var array<string, mixed> $snapshot */
        $snapshot = $state['snapshot'];

        /** @var list<array<string, mixed>> $themes */
        $themes = $snapshot['themes'];

        return [
            'schema_version' => (int) $snapshot['schemaVersion'],
            'algorithm_version' => (int) $snapshot['algorithmVersion'],
            'suggestions_version' => (int) $snapshot['suggestionsVersion'],
            'snapshot_version' => (int) $snapshot['snapshotVersion'],
            'tokens_count' => (int) array_sum(array_map(
                static fn (array $theme): int => is_array($theme['tokens'] ?? null) ? count($theme['tokens']) : 0,
                $themes,
            )),
        ];
    }

    /**
     * @param  mixed  $state
     */
    private function valid($state): bool
    {
        if (! is_array($state)) {
            return false;
        }

        $versions = (array) config('colors.layout_systems.versions', []);

        if (($state['stateVersion'] ?? null) !== (int) ($versions['state'] ?? 1)) {
            return false;
        }

        $inputs = $state['inputs'] ?? null;

        if (! is_array($inputs) || ! $this->validInputs($inputs)) {
            return false;
        }

        $snapshot = $state['snapshot'] ?? null;

        if (! is_array($snapshot) || ! $this->validSnapshot($snapshot, $inputs)) {
            return false;
        }

        $secondary = $state['secondary'] ?? null;
        $background = $state['secondaryBackground'] ?? null;
        $manual = ($snapshot['secondarySource'] ?? null) === 'manual';

        if ($manual ? ! $this->isStoredSecondary($secondary) : $secondary !== null) {
            return false;
        }

        // Secundaria com alpha so existe com o fundo de composicao explicito e
        // opaco (emenda da F6); o fundo extra com secundaria opaca e ignorado.
        if (! $this->isNullableOpaqueRgb($background)) {
            return false;
        }

        return ! ($manual && $this->alphaOf($secondary) < 1 && $background === null);
    }

    /**
     * @param  array<string, mixed>  $inputs
     */
    private function validInputs(array $inputs): bool
    {
        $max = max(1, (int) config('colors.layout_systems.input_max_length', 120));

        foreach (['seedText', 'backgroundText', 'secondaryText'] as $key) {
            $value = $inputs[$key] ?? null;

            if (! is_string($value) || mb_strlen($value) > $max) {
                return false;
            }
        }

        // Campo aditivo da emenda da F6: ausente nos estados gravados antes.
        $secondaryBackground = $inputs['secondaryBackgroundText'] ?? null;

        if ($secondaryBackground !== null
            && (! is_string($secondaryBackground) || mb_strlen($secondaryBackground) > $max)) {
            return false;
        }

        return is_string($inputs['strategy'] ?? null)
            && in_array($inputs['strategy'], LayoutSystemCatalog::STRATEGIES, true);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $inputs
     */
    private function validSnapshot(array $snapshot, array $inputs): bool
    {
        $versions = (array) config('colors.layout_systems.versions', []);

        if (($snapshot['snapshotVersion'] ?? null) !== (int) ($versions['snapshot'] ?? 1)
            || ($snapshot['schemaVersion'] ?? null) !== (int) ($versions['schema'] ?? 1)) {
            return false;
        }

        foreach (['algorithmVersion', 'suggestionsVersion'] as $version) {
            $value = $snapshot[$version] ?? null;

            if (! is_int($value) || $value < 1 || $value > 999) {
                return false;
            }
        }

        if (! in_array($snapshot['target'] ?? null, LayoutSystemCatalog::TARGETS, true)
            || ! in_array($snapshot['strategy'] ?? null, LayoutSystemCatalog::STRATEGIES, true)
            || $snapshot['strategy'] !== $inputs['strategy']
            || ! in_array($snapshot['secondarySource'] ?? null, LayoutSystemCatalog::SECONDARY_SOURCES, true)) {
            return false;
        }

        $seed = $snapshot['seed'] ?? null;

        if (! is_array($seed)
            || ! $this->isRgba($seed['original'] ?? null)
            || ! $this->isRgba($seed['effective'] ?? null, true)
            || ! is_bool($seed['achromatic'] ?? null)
            || ! $this->isNullableOpaqueRgb($seed['background'] ?? null)) {
            return false;
        }

        /** @var array<string, mixed> $original */
        $original = $seed['original'];

        if (($original['a'] ?? 1) < 1 && $seed['background'] === null) {
            return false;
        }

        // Informacao da secundaria (aditiva, emenda da F6): opcional para nao
        // invalidar linhas gravadas antes; quando presente exige fundo com alpha.
        $secondary = $snapshot['secondary'] ?? null;

        if ($secondary !== null) {
            if (! is_array($secondary)
                || ! $this->isRgba($secondary['original'] ?? null)
                || ! $this->isRgba($secondary['effective'] ?? null, true)
                || ! $this->isNullableOpaqueRgb($secondary['background'] ?? null)) {
                return false;
            }

            /** @var array<string, mixed> $secondaryOriginal */
            $secondaryOriginal = $secondary['original'];

            if (($secondaryOriginal['a'] ?? 1) < 1 && $secondary['background'] === null) {
                return false;
            }
        }

        if (! $this->validThemes($snapshot['themes'] ?? null)) {
            return false;
        }

        return $this->validOverrides($snapshot['overrides'] ?? null)
            && $this->validLocks($snapshot['locks'] ?? null)
            && $this->validAccepted($snapshot['acceptedAdjustments'] ?? null)
            && $this->validDiagnostics($snapshot['diagnostics'] ?? null);
    }

    /**
     * @param  mixed  $themes
     */
    private function validThemes($themes): bool
    {
        if (! is_array($themes) || count($themes) !== count(LayoutSystemCatalog::THEMES)) {
            return false;
        }

        $seenThemes = [];

        foreach ($themes as $theme) {
            if (! is_array($theme)
                || ! in_array($theme['theme'] ?? null, LayoutSystemCatalog::THEMES, true)
                || isset($seenThemes[$theme['theme']])) {
                return false;
            }

            $seenThemes[$theme['theme']] = true;

            if (! $this->validTokens($theme['tokens'] ?? null, (string) $theme['theme'])
                || ! $this->validChecks($theme['checks'] ?? null)) {
                return false;
            }
        }

        return count($seenThemes) === count(LayoutSystemCatalog::THEMES);
    }

    /**
     * @param  mixed  $tokens
     */
    private function validTokens($tokens, string $theme): bool
    {
        if (! is_array($tokens) || count($tokens) !== count(LayoutSystemCatalog::TOKENS)) {
            return false;
        }

        $families = LayoutSystemCatalog::families();
        $seen = [];

        foreach ($tokens as $token) {
            if (! is_array($token)
                || ! is_string($token['id'] ?? null)
                || ! isset($families[$token['id']])
                || isset($seen[$token['id']])
                || ($token['family'] ?? null) !== $families[$token['id']]
                || ($token['theme'] ?? null) !== $theme
                || ! $this->isRgba($token['rgba'] ?? null, true)
                || ! $this->isOklch($token['oklch'] ?? null)
                || ! $this->isHex($token['hex'] ?? null)
                || ! is_bool($token['clipped'] ?? null)
                || ! in_array($token['source'] ?? null, LayoutSystemCatalog::TOKEN_SOURCES, true)) {
                return false;
            }

            $seen[$token['id']] = true;
        }

        return count($seen) === count(LayoutSystemCatalog::TOKENS);
    }

    /**
     * @param  mixed  $checks
     */
    private function validChecks($checks): bool
    {
        if (! is_array($checks) || count($checks) !== count(LayoutSystemCatalog::PAIRS)) {
            return false;
        }

        $pairs = LayoutSystemCatalog::pairKeys();
        $seen = [];

        foreach ($checks as $check) {
            if (! is_array($check)
                || ! is_string($check['foreground'] ?? null)
                || ! is_string($check['background'] ?? null)
                || ! isset($pairs[$check['foreground'].'|'.$check['background']])
                || isset($seen[$check['foreground'].'|'.$check['background']])
                || ! $this->isVerdict($check['verdict'] ?? null)) {
                return false;
            }

            $seen[$check['foreground'].'|'.$check['background']] = true;
        }

        return count($seen) === count(LayoutSystemCatalog::PAIRS);
    }

    /**
     * @param  mixed  $overrides
     */
    private function validOverrides($overrides): bool
    {
        if (! is_array($overrides) || count($overrides) > 66) {
            return false;
        }

        $tokens = LayoutSystemCatalog::tokenIds();

        foreach ($overrides as $override) {
            if (! is_array($override)
                || ! in_array($override['theme'] ?? null, LayoutSystemCatalog::THEMES, true)
                || ! in_array($override['token'] ?? null, $tokens, true)
                || ! in_array($override['origin'] ?? null, LayoutSystemCatalog::OVERRIDE_ORIGINS, true)
                || ! $this->isRgba($override['rgba'] ?? null, true)
                || ! $this->isHex($override['hex'] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  mixed  $locks
     */
    private function validLocks($locks): bool
    {
        if (! is_array($locks) || count($locks) > 66) {
            return false;
        }

        $tokens = LayoutSystemCatalog::tokenIds();

        foreach ($locks as $lock) {
            if (! is_array($lock)
                || ! in_array($lock['theme'] ?? null, LayoutSystemCatalog::THEMES, true)
                || ! in_array($lock['token'] ?? null, $tokens, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  mixed  $accepted
     */
    private function validAccepted($accepted): bool
    {
        if (! is_array($accepted) || count($accepted) > 66) {
            return false;
        }

        $tokens = LayoutSystemCatalog::tokenIds();

        foreach ($accepted as $adjustment) {
            if (! is_array($adjustment)
                || ! in_array($adjustment['theme'] ?? null, LayoutSystemCatalog::THEMES, true)
                || ! in_array($adjustment['token'] ?? null, $tokens, true)
                || ! in_array($adjustment['target'] ?? null, LayoutSystemCatalog::TARGETS, true)
                || ! $this->isRgb($adjustment['from'] ?? null)
                || ! $this->isRgb($adjustment['to'] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  mixed  $diagnostics
     */
    private function validDiagnostics($diagnostics): bool
    {
        if (! is_array($diagnostics) || count($diagnostics) > 100) {
            return false;
        }

        $pairs = LayoutSystemCatalog::pairKeys();

        foreach ($diagnostics as $diagnostic) {
            if (! is_array($diagnostic)
                || ! in_array($diagnostic['theme'] ?? null, LayoutSystemCatalog::THEMES, true)
                || ! is_string($diagnostic['foreground'] ?? null)
                || ! is_string($diagnostic['background'] ?? null)
                || ! isset($pairs[$diagnostic['foreground'].'|'.$diagnostic['background']])
                || ! is_numeric($diagnostic['ratio'] ?? null)
                || ! is_bool($diagnostic['aa'] ?? null)
                || ! is_bool($diagnostic['aaa'] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  mixed  $verdict
     */
    private function isVerdict($verdict): bool
    {
        return is_array($verdict)
            && is_numeric($verdict['ratio'] ?? null)
            && (float) $verdict['ratio'] >= 0
            && is_bool($verdict['aaNormal'] ?? null)
            && is_bool($verdict['aaaNormal'] ?? null)
            && is_bool($verdict['aaLarge'] ?? null)
            && is_bool($verdict['aaaLarge'] ?? null);
    }

    /**
     * Secundaria manual gravada: RGB opaco (linhas antigas) ou RGBA valido.
     *
     * @param  mixed  $value
     */
    private function isStoredSecondary($value): bool
    {
        return $this->isRgb($value)
            && (! array_key_exists('a', $value) || $this->isRgba($value));
    }

    /**
     * @param  mixed  $value
     */
    private function alphaOf($value): float
    {
        return is_array($value) && array_key_exists('a', $value) && is_numeric($value['a'])
            ? (float) $value['a']
            : 1.0;
    }

    /**
     * RGB sem canal alpha (ou com `a` exatamente 1): fundo de composicao.
     *
     * @param  mixed  $value
     */
    private function isNullableOpaqueRgb($value): bool
    {
        if ($value === null) {
            return true;
        }

        if (! $this->isRgb($value)) {
            return false;
        }

        return ! array_key_exists('a', $value) || $value['a'] === 1;
    }

    /**
     * @param  mixed  $value
     */
    private function isRgb($value): bool
    {
        return is_array($value) && $this->isChannel($value['r'] ?? null)
            && $this->isChannel($value['g'] ?? null)
            && $this->isChannel($value['b'] ?? null);
    }

    /**
     * @param  mixed  $value
     */
    private function isRgba($value, bool $opaque = false): bool
    {
        if (! $this->isRgb($value)) {
            return false;
        }

        $alpha = $value['a'] ?? null;

        if (! is_int($alpha) && ! is_float($alpha)) {
            return false;
        }

        if ($alpha < 0 || $alpha > 1) {
            return false;
        }

        return ! $opaque || $alpha === 1;
    }

    /**
     * @param  mixed  $value
     */
    private function isChannel($value): bool
    {
        return is_int($value) && $value >= 0 && $value <= 255;
    }

    /**
     * @param  mixed  $value
     */
    private function isOklch($value): bool
    {
        return is_array($value)
            && is_numeric($value['l'] ?? null)
            && is_numeric($value['c'] ?? null)
            && is_numeric($value['h'] ?? null);
    }

    /**
     * @param  mixed  $value
     */
    private function isHex($value): bool
    {
        return is_string($value) && preg_match('/^#[0-9a-f]{6}$/', $value) === 1;
    }
}
