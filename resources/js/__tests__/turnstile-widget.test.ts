import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import TurnstileWidget from '@/components/TurnstileWidget.vue';

/**
 * Widget do Turnstile no cadastro (ADR-052).
 *
 * O api.js da Cloudflare e externo: aqui o `window.turnstile` e substituido por
 * um dublê para conferir a fiacao - token no campo oculto, reset depois de um
 * erro de validacao e nada renderizado quando a chave publica nao existe.
 */

const { pageProps } = vi.hoisted(() => ({
    pageProps: {
        locale: 'pt_BR',
        turnstileSiteKey: 'site-key' as string | null,
    },
}));

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({ props: pageProps }),
}));

const traducoes = {
    auth: {
        turnstile: {
            failed: 'FALHA_TURNSTILE',
        },
    },
};

function montar() {
    const i18n = createI18nInstance();
    applyTranslations(i18n, { locale: 'pt_BR', translations: traducoes });

    return mount(TurnstileWidget, { global: { plugins: [i18n] } });
}

function campoDeToken(wrapper: ReturnType<typeof montar>): HTMLInputElement {
    return wrapper.get('[data-test="turnstile-token"]')
        .element as HTMLInputElement;
}

let render: ReturnType<typeof vi.fn>;
let reset: ReturnType<typeof vi.fn>;
let remove: ReturnType<typeof vi.fn>;

beforeEach(() => {
    pageProps.turnstileSiteKey = 'site-key';

    render = vi.fn(
        (_container: HTMLElement, options: Record<string, unknown>) => {
            (options.callback as (value: string) => void)('token-123');

            return 7;
        },
    );
    reset = vi.fn();
    remove = vi.fn();

    window.turnstile = {
        render: render as unknown as (
            container: HTMLElement,
            options: Record<string, unknown>,
        ) => number,
        reset: reset as unknown as (widgetId?: string | number) => void,
        remove: remove as unknown as (widgetId: string | number) => void,
    };
});

afterEach(() => {
    delete window.turnstile;
    vi.restoreAllMocks();
});

describe('TurnstileWidget', () => {
    it('renderiza com a chave publica e grava o token no campo oculto', async () => {
        const wrapper = montar();
        await flushPromises();

        expect(render).toHaveBeenCalledWith(
            expect.any(HTMLElement),
            expect.objectContaining({
                sitekey: 'site-key',
                language: 'pt_BR',
            }),
        );
        expect(campoDeToken(wrapper).value).toBe('token-123');
    });

    it('limpa o token quando a Cloudflare expira o desafio', async () => {
        let expirar: (() => void) | undefined;
        render.mockImplementation(
            (_container: HTMLElement, options: Record<string, unknown>) => {
                expirar = options['expired-callback'] as () => void;
                (options.callback as (value: string) => void)('token-123');

                return 7;
            },
        );

        const wrapper = montar();
        await flushPromises();

        expirar?.();
        await flushPromises();

        expect(campoDeToken(wrapper).value).toBe('');
    });

    it('reseta o widget e descarta o token consumido', async () => {
        const wrapper = montar();
        await flushPromises();

        await (wrapper.vm as unknown as { reset: () => Promise<void> }).reset();

        expect(reset).toHaveBeenCalledWith(7);
        expect(campoDeToken(wrapper).value).toBe('');
    });

    it('nao renderiza nada sem chave publica', async () => {
        pageProps.turnstileSiteKey = null;

        const wrapper = montar();
        await flushPromises();

        expect(wrapper.find('[data-test="turnstile"]').exists()).toBe(false);
        expect(render).not.toHaveBeenCalled();
    });
});
