import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { parseColor } from '@/lib/color';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import LayoutExportPanel from '@/tools/layout-harmonizer/components/LayoutExportPanel.vue';
import { Select } from '@/components/ui/select';
import { generateLayoutSystem } from '@/tools/layout-harmonizer/engine';
import { emptyEditState } from '@/tools/layout-harmonizer/lib/editState';
import { buildSnapshot } from '@/tools/layout-harmonizer/lib/snapshot';
import {
    buildLayoutState,
    type LayoutSystemStateV1,
} from '@/tools/layout-harmonizer/lib/state';
import systems from '@/routes/dashboard/colors/systems';
import { exportMessages, layoutExportMessages } from './support/langMessages';

/**
 * Card de exportacao (F5, ADR-175).
 *
 * Anonimo ve o cadeado e o CTA de login; autenticado ve o seletor de idioma,
 * prefixo/escopo e os tres downloads. Nada sai daqui: os arquivos vem do
 * snapshot exibido e o download e o helper do navegador (mockado).
 */

const { downloadMock, routerMock, pageState } = vi.hoisted(() => ({
    downloadMock: {
        downloadText: vi.fn(),
        downloadBlob: vi.fn(),
    },
    routerMock: {
        get: vi.fn(),
        post: vi.fn(),
        patch: vi.fn(),
        delete: vi.fn(),
        on: vi.fn(() => vi.fn()),
        visit: vi.fn(),
    },
    pageState: {
        props: {
            locale: 'pt_BR',
            localeSlug: 'pt-br',
            locales: [
                {
                    code: 'en',
                    slug: 'en',
                    html: 'en',
                    dir: 'ltr',
                    name: 'English',
                },
                {
                    code: 'pt_BR',
                    slug: 'pt-br',
                    html: 'pt-BR',
                    dir: 'ltr',
                    name: 'Português (Brasil)',
                },
                {
                    code: 'pt_PT',
                    slug: 'pt-pt',
                    html: 'pt-PT',
                    dir: 'ltr',
                    name: 'Português (Portugal)',
                },
                {
                    code: 'fr',
                    slug: 'fr',
                    html: 'fr',
                    dir: 'ltr',
                    name: 'Français',
                },
                {
                    code: 'es',
                    slug: 'es',
                    html: 'es',
                    dir: 'ltr',
                    name: 'Español',
                },
            ],
            auth: { user: { id: 1 } },
        },
        url: '/pt-br/tools/layout-harmonizer',
    },
}));

vi.mock('@inertiajs/vue3', () => ({
    router: routerMock,
    usePage: () => pageState,
}));

vi.mock('@/lib/download', () => downloadMock);

function i18n() {
    const instance = createI18nInstance();

    applyTranslations(instance, {
        locale: 'pt_BR',
        translations: exportMessages().pt_BR,
    });

    return instance;
}

function stateFixture(): LayoutSystemStateV1 {
    const parsed = parseColor('#3b82f6');

    if (!parsed.ok) {
        throw new Error('cor de teste invalida');
    }

    const snapshot = buildSnapshot(
        generateLayoutSystem({
            seed: parsed.color.rgba,
            strategy: 'analogous',
            background: null,
            secondary: null,
            overrides: undefined,
        }),
        emptyEditState(),
        'aa',
    );

    return buildLayoutState(
        {
            seedText: '#3b82f6',
            backgroundText: '',
            secondaryText: '',
            strategy: 'analogous',
        },
        null,
        snapshot,
    );
}

function mountPanel(props: Record<string, unknown> = {}) {
    return mount(LayoutExportPanel, {
        props: {
            state: stateFixture(),
            name: 'Sistema Oceano',
            authenticated: true,
            exportMessages: layoutExportMessages(),
            exportLimits: { prefixMaxLength: 32, scopeMaxLength: 64 },
            ...props,
        },
        global: { plugins: [i18n()] },
        attachTo: document.body,
    });
}

function exists(selector: string): boolean {
    return document.querySelector(selector) !== null;
}

function query<T extends HTMLElement>(selector: string): T {
    const element = document.querySelector<T>(selector);

    if (element === null) {
        throw new Error(`elemento ausente: ${selector}`);
    }

    return element;
}

async function click(selector: string): Promise<void> {
    query<HTMLButtonElement>(selector).click();
    await nextTick();
}

describe('card de exportacao', () => {
    beforeEach(() => {
        downloadMock.downloadText.mockReset();
        downloadMock.downloadBlob.mockReset();
        routerMock.get.mockReset();
        document.body.innerHTML = '';
    });

    it('nao renderiza sem o subtree de traducoes', () => {
        mountPanel({ exportMessages: {} });

        expect(exists('[data-test="layout-export-panel"]')).toBe(false);
    });

    it('anonimo ve o cadeado e o CTA leva ao login preservando a paleta', async () => {
        mountPanel({
            authenticated: false,
            originPaletteId: '01KPALETTE00000000000000001',
        });

        expect(exists('[data-test="layout-export-locked"]')).toBe(true);
        expect(exists('[data-test="layout-export-markdown"]')).toBe(false);

        await click('[data-test="layout-export-login"]');

        expect(routerMock.get).toHaveBeenCalledWith(
            systems.create.url({
                query: {
                    export: '1',
                    palette: '01KPALETTE00000000000000001',
                },
            }),
        );
    });

    it('autenticado baixa Markdown no idioma ativo', async () => {
        mountPanel();

        await click('[data-test="layout-export-markdown"]');

        expect(downloadMock.downloadText).toHaveBeenCalledTimes(1);

        const [filename, contents, mime] =
            downloadMock.downloadText.mock.calls[0] ?? [];

        expect(filename).toBe('design-instructions.pt-BR.md');
        expect(mime).toBe('text/markdown;charset=utf-8');
        expect(contents).toContain('# Sistema Oceano');
    });

    it('trocar o idioma muda o documento', async () => {
        const wrapper = mountPanel();
        const languageSelect = wrapper.findAllComponents(Select)[0];

        expect(languageSelect).toBeDefined();

        languageSelect!.vm.$emit('update:modelValue', 'en');
        await nextTick();

        await click('[data-test="layout-export-markdown"]');

        const [filename] = downloadMock.downloadText.mock.calls[0] ?? [];

        expect(filename).toBe('design-instructions.en.md');
    });

    it('baixa o CSS com os seletores do escopo e o ZIP com os dois arquivos', async () => {
        mountPanel();

        await click('[data-test="layout-export-css"]');

        const [cssName, cssContents] =
            downloadMock.downloadText.mock.calls[0] ?? [];

        expect(cssName).toBe('colors.css');
        expect(cssContents).toContain(':root, :root[data-theme="light"] {');
        expect(cssContents).toContain(':root[data-theme="dark"] {');
        expect(cssContents).toContain('--lh-primary:');

        await click('[data-test="layout-export-zip"]');

        expect(downloadMock.downloadBlob).toHaveBeenCalledTimes(1);

        const [zipName, blob] = downloadMock.downloadBlob.mock.calls[0] ?? [];

        expect(zipName).toBe('sistema-oceano-layout.zip');
        expect(blob).toBeInstanceOf(Blob);
    });

    it('prefixo invalido mostra o erro e desabilita os downloads', async () => {
        mountPanel();

        const prefix = query<HTMLInputElement>(
            '[data-test="layout-export-prefix"]',
        );

        prefix.value = 'Lh';
        prefix.dispatchEvent(new Event('input'));
        await nextTick();

        expect(
            query('[data-test="layout-export-prefix-error"]').textContent,
        ).toContain('minúsculas');

        for (const selector of [
            'layout-export-markdown',
            'layout-export-css',
            'layout-export-zip',
        ]) {
            expect(
                query<HTMLButtonElement>(`[data-test="${selector}"]`).disabled,
            ).toBe(true);
        }
    });

    it('escopo de classe exige um nome valido', async () => {
        const wrapper = mountPanel();
        const scopeSelect = wrapper.findAllComponents(Select)[1];

        scopeSelect!.vm.$emit('update:modelValue', 'class');
        await nextTick();

        expect(
            query<HTMLButtonElement>('[data-test="layout-export-css"]')
                .disabled,
        ).toBe(true);

        const className = query<HTMLInputElement>(
            '[data-test="layout-export-scope-class"]',
        );

        className.value = '.app';
        className.dispatchEvent(new Event('input'));
        await nextTick();

        expect(
            query<HTMLButtonElement>('[data-test="layout-export-css"]')
                .disabled,
        ).toBe(false);

        await click('[data-test="layout-export-css"]');

        const [, contents] = downloadMock.downloadText.mock.calls[0] ?? [];

        expect(contents).toContain('.app, .app[data-theme="light"] {');
        expect(contents).toContain('.app[data-theme="dark"] {');
    });

    it('pendente de regeneracao bloqueia com o aviso do salvamento', () => {
        mountPanel({ pendingRegeneration: true });

        expect(exists('[data-test="layout-export-pending"]')).toBe(true);
        expect(
            query<HTMLButtonElement>('[data-test="layout-export-markdown"]')
                .disabled,
        ).toBe(true);
    });

    it('avisa modo compativel e estado somente leitura', () => {
        mountPanel({ mode: 'compatible' });

        expect(exists('[data-test="layout-export-compatible"]')).toBe(true);

        document.body.innerHTML = '';

        mountPanel({ mode: 'readonly', readOnly: true });

        expect(exists('[data-test="layout-export-readonly"]')).toBe(true);
    });

    it('origem so entra no documento quando e publica', async () => {
        mountPanel({ origin: { name: 'Oceano base', public: false } });

        await click('[data-test="layout-export-markdown"]');

        expect(downloadMock.downloadText.mock.calls[0]?.[1]).not.toContain(
            'Oceano base',
        );

        downloadMock.downloadText.mockReset();
        document.body.innerHTML = '';

        mountPanel({ origin: { name: 'Oceano base', public: true } });

        await click('[data-test="layout-export-markdown"]');

        expect(downloadMock.downloadText.mock.calls[0]?.[1]).toContain(
            'Oceano base',
        );
    });
});
