/**
 * Detalhes de plataforma que mudam a interface.
 *
 * O atalho da busca nao passa pelo i18n: ele muda com o teclado, nao com o
 * idioma. No SSR nao existe `navigator`, entao o rotulo cai no generico -
 * mesma escolha que o resto do app faz para o que so o navegador sabe.
 */

export function isApplePlatform(): boolean {
    if (typeof navigator === 'undefined') {
        return false;
    }

    return /Mac|iPhone|iPad/.test(navigator.userAgent);
}

/** Rotulo do atalho da palette, para o `<kbd>` do gatilho. */
export function searchShortcutLabel(): string {
    return isApplePlatform() ? '⌘K' : 'Ctrl K';
}
