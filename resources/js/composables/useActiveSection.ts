import type { ComputedRef } from 'vue';
import { computed } from 'vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavItem } from '@/types';

export type UseActiveSectionReturn = {
    activeHref: ComputedRef<string | null>;
    activeTitle: ComputedRef<string>;
};

/**
 * A secao aberta de uma navegacao lateral.
 *
 * A secao aberta e a rota mais especifica que casa com a URL: `/dashboard` e
 * `/admin` sao prefixo de toda pagina da area, entao a primeira que casasse
 * seria sempre a Visao geral - em `/admin/users/5` quem esta aberta e Contas, e
 * o href mais longo e quem decide. E o mesmo href que marca o item na navegacao
 * (`aria-current`) e escreve o titulo da barra; as duas coisas saem daqui para
 * nao divergirem.
 *
 * `fallback` cobre a tela que nao pertence a nenhuma secao (uma pagina nova,
 * ainda sem item de menu).
 */
export function useActiveSection(
    items: ComputedRef<NavItem[]>,
    fallback: () => string,
): UseActiveSectionReturn {
    const { isCurrentOrParentUrl } = useCurrentUrl();

    const activeHref = computed<string | null>(() => {
        const matches = items.value
            .map((item) => item.href)
            // As secoes sao sempre caminho simples; o par caminho+metodo do
            // Inertia fica so no tipo compartilhado.
            .filter((href): href is string => typeof href === 'string')
            .filter((href) => isCurrentOrParentUrl(href));

        return matches.toSorted((a, b) => b.length - a.length)[0] ?? null;
    });

    const activeTitle = computed<string>(
        () =>
            items.value.find((item) => item.href === activeHref.value)?.title ??
            fallback(),
    );

    return { activeHref, activeTitle };
}
