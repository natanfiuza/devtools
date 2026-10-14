import { computed, type ComputedRef } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { setCookie } from '@/lib/cookies';
import type { Locale } from '@/types';

export type UseLocaleReturn = {
    locales: ComputedRef<Locale[]>;
    current: ComputedRef<Locale | undefined>;
    switchTo: (locale: Locale) => void;
};

/**
 * Troca de idioma.
 *
 * Nas paginas publicas e nas de acesso o idioma esta na URL (ADR-022), entao
 * trocar e navegar para o mesmo caminho no outro idioma
 * (`/pt-br/styleguide` -> `/fr/styleguide`, `/pt-br/login` -> `/fr/login`).
 * No painel e nas configuracoes o caminho nao tem idioma: grava o cookie e
 * recarrega, e o servidor resolve de novo.
 *
 * Trocar por aqui muda o idioma de navegacao (cookie), nao o idioma da conta:
 * esse so muda no cadastro e no perfil (ADR-026).
 */
export function swapLocaleInPath(
    path: string,
    slugs: string[],
    slug: string,
): string | null {
    const [first = '', ...rest] = path.replace(/^\/+/, '').split('/');

    if (!slugs.includes(first)) {
        return null;
    }

    return '/'.concat([slug, ...rest].join('/'));
}

export function useLocale(): UseLocaleReturn {
    const page = usePage();

    const locales = computed<Locale[]>(() => page.props.locales ?? []);

    const current = computed<Locale | undefined>(() =>
        locales.value.find((locale) => locale.code === page.props.locale),
    );

    function switchTo(locale: Locale): void {
        if (locale.code === page.props.locale) {
            return;
        }

        // O cookie vai na hora: se a navegacao seguinte nao tiver o idioma na
        // URL, ele e quem manda.
        setCookie('locale', locale.slug);

        const path =
            typeof window === 'undefined'
                ? null
                : swapLocaleInPath(
                      window.location.pathname,
                      locales.value.map((item) => item.slug),
                      locale.slug,
                  );

        if (path === null) {
            router.reload();

            return;
        }

        router.visit(path + window.location.search + window.location.hash, {
            preserveScroll: true,
        });
    }

    return { locales, current, switchTo };
}
