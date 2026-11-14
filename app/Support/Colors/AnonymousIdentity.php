<?php

namespace App\Support\Colors;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Cookie as CookieValue;

/**
 * Identificador anonimo das curtidas (F6, ADR-135).
 *
 * O visitante recebe um cookie first-party, cifrado pelo EncryptCookies,
 * HttpOnly e SameSite=Lax. O valor e aleatorio e nao tem relacao com IP,
 * sessao, e-mail ou conta. O IP so e usado, via HMAC, como chave do limitador
 * em cache; nunca e gravado nem devolvido.
 *
 * Sem cookie, ou com valor fora do formato, o visitante e tratado como um
 * novo anonimo.
 */
final class AnonymousIdentity
{
    public const COOKIE = 'colors_anon';

    private const PATTERN = '/^[a-f0-9]{64}$/';

    public static function read(Request $request): ?string
    {
        $value = $request->cookie(self::COOKIE);

        return is_string($value) && preg_match(self::PATTERN, $value) === 1
            ? $value
            : null;
    }

    public static function issue(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Chave do limitador para o visitante; nao e o IP em texto.
     */
    public static function ipKey(Request $request): string
    {
        return hash_hmac(
            'sha256',
            'colors-ip|'.$request->ip(),
            (string) config('app.key'),
        );
    }

    public static function cookie(string $identifier): CookieValue
    {
        $minutes = max(1, (int) config('colors.social.cookie_days', 365)) * 24 * 60;

        return Cookie::make(
            name: self::COOKIE,
            value: $identifier,
            minutes: $minutes,
            path: '/',
            domain: null,
            secure: (bool) config('session.secure'),
            httpOnly: true,
            raw: false,
            sameSite: 'lax',
        );
    }

    public static function forget(): CookieValue
    {
        return Cookie::forget(self::COOKIE);
    }
}
