<?php

namespace App\Support\BadgeForge;

/**
 * Monta o BadgeSpec a partir de label/message ja decodificados e da query
 * cosmetica (F1; extraido do BadgeRequest na F4).
 *
 * A rota estatica usa o caminho padrao, com `?cacheSeconds=`; a rota de
 * provider passa um teto explicito e ignora o parametro do cliente. Logos
 * personalizados continuam limitados por `custom_logos.max_cache_seconds`.
 */
final class BadgeSpecBuilder
{
    public function __construct(
        private readonly StyleCatalog $styles,
        private readonly LogoCatalog $logos,
        private readonly CustomLogoRepository $customLogos,
        private readonly BadgeFontCatalog $fonts,
    ) {}

    /**
     * @param  array<string, mixed>  $query
     */
    public function build(string $label, string $message, array $query, ?int $cacheSecondsOverride = null): BadgeSpec
    {
        $style = $this->styles->get(self::queryString($query, 'style'));

        if ($style->uppercase) {
            $label = mb_strtoupper($label, 'UTF-8');
            $message = mb_strtoupper($message, 'UTF-8');
        }

        $maxLength = (int) config('badgeforge.limits.max_text_length', 100);
        $label = BadgeRequest::truncate($label, $maxLength);
        $message = BadgeRequest::truncate($message, $maxLength);

        $labelColor = $style->labelColor
            ?? Color::parseOr(self::queryString($query, 'labelColor'), (string) config('badgeforge.colors.label'));

        $messageColor = $style->messageColor
            ?? Color::parseOr(self::queryString($query, 'color'), (string) config('badgeforge.colors.default'));

        $rawLogo = self::queryString($query, 'logo');
        $logo = $this->resolveLogo($rawLogo);

        $logoSize = BadgeInput::integer(
            self::queryString($query, 'logoSize'),
            $style->logoSize,
            (int) config('badgeforge.limits.logo_size.min', 8),
            (int) config('badgeforge.limits.logo_size.max', 20),
        );

        $logoPosition = self::queryString($query, 'logoPosition') === 'right' ? 'right' : 'left';

        $cacheSeconds = $cacheSecondsOverride ?? BadgeInput::integer(
            self::queryString($query, 'cacheSeconds'),
            (int) config('badgeforge.limits.cache_seconds.default', 86400),
            (int) config('badgeforge.limits.cache_seconds.min', 300),
            (int) config('badgeforge.limits.cache_seconds.max', 86400),
        );

        if (self::isCustomLogoSlug($rawLogo)) {
            // Logos personalizados podem ser excluidos: o teto garante que a
            // remocao chegue ao CDN em no maximo `max_cache_seconds` (ADR-078).
            $cacheSeconds = min(
                $cacheSeconds,
                (int) config('badgeforge.custom_logos.max_cache_seconds', 3600),
            );
        }

        return new BadgeSpec(
            label: $label,
            message: $message,
            style: $style,
            font: $this->fonts->find(self::queryString($query, 'font')),
            labelColor: $labelColor,
            messageColor: $messageColor,
            logo: $logo,
            logoColor: Color::parseOr(self::queryString($query, 'logoColor'), (string) config('badgeforge.colors.logo')),
            logoSize: $logoSize,
            logoPosition: $logoPosition,
            link: BadgeInput::link(self::queryString($query, 'link')),
            cacheSeconds: max(1, $cacheSeconds),
        );
    }

    /**
     * `u_` e namespace reservado para logos personalizados; um slug nesse
     * formato nunca cai no catalogo embutido.
     */
    private function resolveLogo(?string $raw): ?Logo
    {
        if (self::isCustomLogoSlug($raw)) {
            return $this->customLogos->find($raw);
        }

        return $this->logos->find($raw);
    }

    private static function isCustomLogoSlug(?string $raw): bool
    {
        return $raw !== null && preg_match('/^u_/i', trim($raw)) === 1;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private static function queryString(array $query, string $key): ?string
    {
        $value = $query[$key] ?? null;

        return is_string($value) ? $value : null;
    }
}
