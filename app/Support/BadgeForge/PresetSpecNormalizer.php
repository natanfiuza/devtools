<?php

namespace App\Support\BadgeForge;

use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Normaliza o snapshot de formulario de um preset (ADR-079).
 *
 * Reusa as mesmas regras tolerantes de `BadgeRequest` (cores, tamanhos, link,
 * cache) para que aplicar um preset produza exatamente o badge que a URL
 * renderiza. A unica rejeicao e um logo custom de outra conta - referenciar o
 * logo de terceiros nunca e valido, mesmo que a renderizacao publica fosse
 * cair em "sem logo".
 */
final class PresetSpecNormalizer
{
    private const CUSTOM_LOGO_PATTERN = '/^u_([0-9A-HJKMNP-TV-Z]{26})$/i';

    public function __construct(
        private readonly StyleCatalog $styles,
        private readonly LogoCatalog $logos,
        private readonly BadgeFontCatalog $fonts,
    ) {}

    /**
     * @param  array<string, mixed>  $form
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function normalize(array $form, User $user): array
    {
        $styleId = is_string($form['style'] ?? null) ? $form['style'] : null;
        $style = $this->styles->get($styleId);

        $logo = $this->logo($form['logo'] ?? null, $user);

        $logoSize = $this->integer(
            $form['logoSize'] ?? null,
            $style->logoSize,
            (int) config('badgeforge.limits.logo_size.min', 8),
            (int) config('badgeforge.limits.logo_size.max', 20),
        );

        $cacheSeconds = $this->integer(
            $form['cacheSeconds'] ?? null,
            (int) config('badgeforge.limits.cache_seconds.default', 86400),
            (int) config('badgeforge.limits.cache_seconds.min', 300),
            (int) config('badgeforge.limits.cache_seconds.max', 86400),
        );

        if (str_starts_with($logo, 'u_')) {
            $cacheSeconds = min(
                $cacheSeconds,
                (int) config('badgeforge.custom_logos.max_cache_seconds', 3600),
            );
        }

        $font = is_string($form['font'] ?? null)
            ? $this->fonts->find($form['font'])
            : null;

        return [
            'label' => $this->text($form['label'] ?? null),
            'message' => $this->text($form['message'] ?? null),
            'style' => $style->id,
            'font' => $font?->slug,
            'color' => Color::parseOr($form['color'] ?? null, (string) config('badgeforge.colors.default'))->serialized(),
            'labelColor' => Color::parseOr($form['labelColor'] ?? null, (string) config('badgeforge.colors.label'))->serialized(),
            'logo' => $logo,
            'logoColor' => Color::parseOr($form['logoColor'] ?? null, (string) config('badgeforge.colors.logo'))->serialized(),
            'logoSize' => $logoSize,
            'logoPosition' => ($form['logoPosition'] ?? null) === 'right' ? 'right' : 'left',
            'link' => is_string($form['link'] ?? null) ? BadgeInput::link($form['link']) : null,
            'cacheSeconds' => $cacheSeconds,
        ];
    }

    /**
     * @throws ValidationException
     */
    private function logo(mixed $raw, User $user): string
    {
        if (! is_string($raw)) {
            return 'none';
        }

        $value = trim($raw);

        if ($value === '' || $value === 'none') {
            return 'none';
        }

        if (preg_match(self::CUSTOM_LOGO_PATTERN, $value, $matches) === 1) {
            $id = strtoupper($matches[1]);

            $owned = $user->badgeCustomLogos()->whereKey($id)->exists();

            if (! $owned) {
                throw ValidationException::withMessages([
                    'form.logo' => __('validation.custom_logo.invalid_logo'),
                ]);
            }

            return 'u_'.$id;
        }

        $slug = strtolower($value);

        return in_array($slug, $this->logos->slugs(), true) ? $slug : 'none';
    }

    private function text(mixed $raw): string
    {
        if (! is_string($raw)) {
            return '';
        }

        $cleaned = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $raw);

        return BadgeRequest::truncate(
            is_string($cleaned) ? $cleaned : '',
            (int) config('badgeforge.limits.max_text_length', 100),
        );
    }

    private function integer(mixed $raw, int $fallback, int $min, int $max): int
    {
        $string = is_int($raw) ? (string) $raw : (is_string($raw) ? $raw : null);

        return BadgeInput::integer($string, $fallback, $min, $max);
    }
}
