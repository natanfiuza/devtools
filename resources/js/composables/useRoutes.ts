import { computed, type ComputedRef } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { home, styleguide } from '@/routes';

export type UseRoutesReturn = {
    /** Home no idioma atual (`/pt-br`). */
    home: ComputedRef<string>;
    styleguide: ComputedRef<string>;
};

/**
 * Rotas publicas com o idioma atual ja injetado.
 *
 * As paginas publicas sao prefixadas pelo idioma, entao o parametro `locale`
 * e sempre obrigatorio no wayfinder. Aqui ele vem das props compartilhadas:
 * o componente escreve `home.value` e nao se preocupa com o idioma.
 */
export function useRoutes(): UseRoutesReturn {
    const page = usePage();

    const slug = computed<string>(() => page.props.localeSlug);

    return {
        home: computed<string>(() => home({ locale: slug.value }).url),
        styleguide: computed<string>(() => styleguide({ locale: slug.value }).url),
    };
}
