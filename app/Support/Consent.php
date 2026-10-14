<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Decisao do visitante sobre cookies de medicao (LGPD/GDPR, ADR-013).
 *
 * Sao tres estados, e o terceiro e o que importa: sem cookie a decisao e nula,
 * e e com ela nula que o banner aparece. Qualquer valor fora dos dois aceitos -
 * cookie antigo, editado a mao ou truncado - tambem volta a nula, porque "sem
 * decisao" e o estado seguro: nada de medicao.
 *
 * Quem decide e o servidor, que manda a decisao nas props compartilhadas: assim
 * o banner nao pisca para quem ja respondeu, mesmo padrao do tema (ADR-021).
 */
final class Consent
{
    public const GRANTED = 'granted';

    public const DENIED = 'denied';

    /**
     * Decisoes aceitas.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return [self::GRANTED, self::DENIED];
    }

    public static function isSupported(mixed $value): bool
    {
        return is_string($value) && in_array($value, self::values(), strict: true);
    }

    /** Nome do cookie, de config/analytics.php (fonte unica dos dois lados). */
    public static function cookie(): string
    {
        return (string) config('analytics.cookie');
    }

    /** Validade do cookie, em dias. */
    public static function days(): int
    {
        return (int) config('analytics.cookie_days');
    }

    /** Decisao registrada, ou nulo quando o visitante ainda nao respondeu. */
    public static function fromRequest(Request $request): ?string
    {
        // O cookie vem do cliente: pode nao existir, vir repetido (array) ou
        // com qualquer valor. So um dos dois aceitos passa daqui.
        $cookie = $request->cookie(self::cookie());

        return is_string($cookie) && self::isSupported($cookie) ? $cookie : null;
    }

    /**
     * O cookie veio, mas com um valor que nao serve.
     *
     * Quem responde a requisicao usa isto para apagar o lixo do navegador
     * (HandleConsent): sem isso ele voltaria a cada visita.
     */
    public static function isCorrupt(Request $request): bool
    {
        $cookie = $request->cookie(self::cookie());

        return $cookie !== null && ! self::isSupported($cookie);
    }
}
