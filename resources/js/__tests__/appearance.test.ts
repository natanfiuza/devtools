import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { defineComponent, h } from 'vue';
import {
    initializeTheme,
    isAppearance,
    updateTheme,
    useAppearance,
} from '@/composables/useAppearance';

/**
 * O tema e resolvido no servidor (o <html> ja chega com a classe) e o cliente
 * so corrige quando existe preferencia local. O que se testa aqui e
 * exatamente essa ordem: localStorage -> servidor -> sistema.
 */

const { pageProps } = vi.hoisted(() => ({
    pageProps: { appearance: 'system' } as { appearance: string },
}));

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({ props: pageProps }),
}));

/** jsdom nao implementa matchMedia. */
function stubMatchMedia(dark: boolean): void {
    window.matchMedia = ((query: string) => ({
        matches: query.includes('prefers-color-scheme: dark') ? dark : !dark,
        media: query,
        addEventListener: () => {},
        removeEventListener: () => {},
        addListener: () => {},
        removeListener: () => {},
        onchange: null,
        dispatchEvent: () => false,
    })) as unknown as typeof window.matchMedia;
}

function isDark(): boolean {
    return document.documentElement.classList.contains('dark');
}

beforeEach(() => {
    localStorage.clear();
    document.documentElement.classList.remove('dark');
    delete document.documentElement.dataset.appearance;
    pageProps.appearance = 'system';
    stubMatchMedia(false);
});

describe('isAppearance', () => {
    it('aceita apenas os tres valores', () => {
        expect(['light', 'dark', 'system'].every(isAppearance)).toBe(true);
        expect(isAppearance('neon')).toBe(false);
        expect(isAppearance(undefined)).toBe(false);
    });
});

describe('initializeTheme', () => {
    it('respeita o que o servidor ja renderizou', () => {
        document.documentElement.dataset.appearance = 'dark';

        initializeTheme();

        expect(isDark()).toBe(true);
    });

    it('deixa a preferencia local vencer o servidor', () => {
        document.documentElement.dataset.appearance = 'light';
        localStorage.setItem('appearance', 'dark');

        initializeTheme();

        expect(isDark()).toBe(true);
    });

    it('segue o sistema quando nao ha preferencia', () => {
        stubMatchMedia(true);

        initializeTheme();

        expect(isDark()).toBe(true);
    });

    it('nao aplica escuro com o sistema em claro', () => {
        document.documentElement.dataset.appearance = 'system';

        initializeTheme();

        expect(isDark()).toBe(false);
    });

    it('ignora um valor invalido guardado no navegador', () => {
        localStorage.setItem('appearance', 'neon');
        document.documentElement.dataset.appearance = 'light';

        initializeTheme();

        expect(isDark()).toBe(false);
    });
});

describe('updateTheme', () => {
    it('alterna a classe do <html>', () => {
        updateTheme('dark');
        expect(isDark()).toBe(true);

        updateTheme('light');
        expect(isDark()).toBe(false);
    });

    it('com system, segue o sistema', () => {
        stubMatchMedia(true);
        updateTheme('system');

        expect(isDark()).toBe(true);
    });
});

describe('useAppearance', () => {
    function mountToggle() {
        const Host = defineComponent({
            setup: () => useAppearance(),
            render: () => h('div'),
        });

        return mount(Host);
    }

    it('usa a preferencia da conta quando nao ha nada local', () => {
        pageProps.appearance = 'dark';

        const wrapper = mountToggle();

        expect(wrapper.vm.appearance).toBe('dark');
    });

    it('prefere o localStorage a conta', () => {
        pageProps.appearance = 'dark';
        localStorage.setItem('appearance', 'light');

        const wrapper = mountToggle();

        expect(wrapper.vm.appearance).toBe('light');
    });

    it('grava localStorage, cookie e a classe do <html>', () => {
        const wrapper = mountToggle();

        wrapper.vm.updateAppearance('dark');

        expect(wrapper.vm.appearance).toBe('dark');
        expect(localStorage.getItem('appearance')).toBe('dark');
        expect(document.cookie).toContain('appearance=dark');
        expect(isDark()).toBe(true);
    });
});
