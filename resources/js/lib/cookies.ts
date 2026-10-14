/**
 * Cookies de preferencia (tema e idioma). O servidor le os dois: o tema move
 * a classe do <html> e o idioma escolhe o lang/. Sem cookie, o servidor cai
 * na preferencia da conta ou do navegador.
 */

const DEFAULT_DAYS = 365;

export function setCookie(
    name: string,
    value: string,
    days = DEFAULT_DAYS,
): void {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;

    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
}

export function getCookie(name: string): string | null {
    if (typeof document === 'undefined') {
        return null;
    }

    const prefix = `${name}=`;

    for (const part of document.cookie.split(';')) {
        const entry = part.trim();

        if (entry.startsWith(prefix)) {
            return decodeURIComponent(entry.slice(prefix.length));
        }
    }

    return null;
}
