import { mount } from '@vue/test-utils';
import { defineComponent, h, nextTick } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { parseColor } from '@/lib/color';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import PublicLayoutSystem from '@/pages/colors/PublicLayoutSystem.vue';
import {
    LAYOUT_ALGORITHM_VERSION,
    LAYOUT_SCHEMA_VERSION,
    generateLayoutSystem,
} from '@/tools/layout-harmonizer/engine';
import { emptyEditState } from '@/tools/layout-harmonizer/lib/editState';
import {
    LAYOUT_SNAPSHOT_VERSION,
    buildSnapshot,
} from '@/tools/layout-harmonizer/lib/snapshot';
import {
    buildLayoutState,
    type LayoutSystemStateV1,
} from '@/tools/layout-harmonizer/lib/state';
import { LAYOUT_SUGGESTIONS_VERSION } from '@/tools/layout-harmonizer/lib/suggestions';
import type {
    LayoutSystemPublic,
    PublicLayoutSystemProps,
} from '@/types/layoutSystem';
import { exportMessages } from './support/langMessages';

/**
 * Página pública de um sistema de layout (F4, ADR-174).
 *
 * Vitrine somente leitura do snapshot gravado: os quadros não têm edição nem
 * restauração, mas a cópia de HEX continua. O crédito só aparece quando a
 * origem era pública, e duplicar exige conta - anônimo sai pelo intent e
 * volta com `duplicate=1`, que dispara o POST uma vez só.
 */

// O motor gera 66 tokens por montagem; sob a suite inteira em paralelo o
// seletor de 5 s padrao aperta. O limite maior so cobre a montagem pesada.
vi.setConfig({ testTimeout: 20_000 });

const { routerMock, pageState } = vi.hoisted(() => ({
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
            localeSlug: 'pt-br',
            auth: { user: { id: 1 } },
            seo: { meta: { title: 'Atlas' } },
        },
        url: '/pt-br/colors/s/atlas',
    },
}));

vi.mock('@inertiajs/vue3', () => ({
    router: routerMock,
    usePage: () => pageState,
    Head: defineComponent({
        name: 'InertiaHead',
        props: { title: { type: String, default: '' } },
        render: () => h('div'),
    }),
    Link: defineComponent({
        name: 'InertiaLink',
        props: { href: { type: String, default: '' } },
        setup(props, { slots, attrs }) {
            return () =>
                h(
                    'a',
                    { ...attrs, href: props.href },
                    slots.default ? slots.default() : [],
                );
        },
    }),
}));

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

    snapshot.schemaVersion = LAYOUT_SCHEMA_VERSION;
    snapshot.algorithmVersion = LAYOUT_ALGORITHM_VERSION;
    snapshot.suggestionsVersion = LAYOUT_SUGGESTIONS_VERSION;

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

function system(
    overrides: Partial<LayoutSystemPublic> = {},
): LayoutSystemPublic {
    return {
        id: '01KSYSTEM00000000000000001',
        name: 'Atlas',
        description: 'Sistema claro e escuro',
        schema_version: LAYOUT_SCHEMA_VERSION,
        algorithm_version: LAYOUT_ALGORITHM_VERSION,
        suggestions_version: LAYOUT_SUGGESTIONS_VERSION,
        snapshot_version: LAYOUT_SNAPSHOT_VERSION,
        tokens_count: 66,
        duplications_count: 3,
        slug: 'atlas',
        state: stateFixture(),
        tags: [{ id: 'tag-1', name: 'web' }],
        author: {
            id: 1,
            name: 'Nataniel Fiuza',
            username: 'natanfi',
            avatar: null,
        },
        credit: null,
        updated_at: '2026-11-10T12:00:00+00:00',
        ...overrides,
    };
}

function props(
    overrides: Partial<PublicLayoutSystemProps> = {},
): PublicLayoutSystemProps {
    return {
        system: system(),
        authenticated: true,
        canDuplicate: true,
        ...overrides,
    };
}

function mountPage(overrides: Partial<PublicLayoutSystemProps> = {}) {
    return mount(PublicLayoutSystem, {
        props: props(overrides),
        global: { plugins: [i18n()] },
        attachTo: document.body,
    });
}

function query<T extends HTMLElement>(selector: string): T {
    const element = document.querySelector<T>(selector);

    if (element === null) {
        throw new Error(`elemento ausente: ${selector}`);
    }

    return element;
}

function exists(selector: string): boolean {
    return document.querySelector(selector) !== null;
}

describe('Página pública de sistema de layout', () => {
    beforeEach(() => {
        routerMock.get.mockReset();
        routerMock.post.mockReset();
        routerMock.patch.mockReset();
        routerMock.delete.mockReset();
        routerMock.on.mockClear();
        pageState.url = '/pt-br/colors/s/atlas';
        document.body.innerHTML = '';

        window.history.replaceState(
            window.history.state,
            '',
            '/pt-br/colors/s/atlas',
        );
    });

    it('mostra os quadros somente leitura a partir do snapshot', () => {
        mountPage();

        const page = query('[data-test="public-layout-system"]');

        expect(page.textContent).toContain('Atlas');
        expect(page.textContent).toContain('Sistema claro e escuro');
        expect(
            query('[data-test="public-layout-system-tokens"]').textContent,
        ).toContain('66 tokens');
        expect(
            query('[data-test="public-layout-system-usage"]').textContent,
        ).toContain('Usada 3 vezes');
        expect(
            query('[data-test="public-layout-system-versions"]').textContent,
        ).toContain('Schema v1');
        expect(
            document.querySelectorAll('[data-test="token-cell"]'),
        ).toHaveLength(66);
        const editButtons = Array.from(
            document.querySelectorAll<HTMLButtonElement>(
                '[data-test="token-edit"]',
            ),
        );
        const lockButtons = Array.from(
            document.querySelectorAll<HTMLButtonElement>(
                '[data-test="token-lock"]',
            ),
        );

        // Os botoes aparecem, mas travados: a vitrine nunca aceita edicao.
        expect(editButtons.length).toBeGreaterThan(0);
        expect(editButtons.every((button) => button.disabled)).toBe(true);
        expect(lockButtons.length).toBeGreaterThan(0);
        expect(lockButtons.every((button) => button.disabled)).toBe(true);
        expect(
            document.querySelectorAll('[data-test="copy-hex"]').length,
        ).toBeGreaterThan(0);
        // A pagina publica nao exporta para ninguem (F5, ADR-175).
        expect(exists('[data-test="layout-export-panel"]')).toBe(false);
    });

    it('liga o autor ao perfil e a estratégia ao nome traduzido', () => {
        mountPage();

        expect(
            query<HTMLAnchorElement>(
                '[data-test="public-layout-system-author-link"]',
            ).getAttribute('href'),
        ).toBe('/pt-br/u/natanfi');
        expect(
            query('[data-test="public-layout-system-strategy"]').textContent,
        ).toContain('Análoga');
    });

    it('exibe crédito com link quando a origem é pública', () => {
        mountPage({
            system: system({
                credit: {
                    palette_name: 'Céu de verão',
                    palette_slug: 'ceu-de-verao',
                    palette_url: '/pt-br/colors/p/ceu-de-verao',
                },
            }),
        });

        expect(
            query('[data-test="public-layout-system-credit"]').textContent,
        ).toContain('Criado a partir da paleta');
        expect(
            query<HTMLAnchorElement>(
                '[data-test="public-layout-system-credit-link"]',
            ).getAttribute('href'),
        ).toBe('/pt-br/colors/p/ceu-de-verao');
    });

    it('mantém o crédito textual sem link quando a origem sumiu', () => {
        mountPage({
            system: system({
                credit: {
                    palette_name: 'Céu de verão',
                    palette_slug: null,
                    palette_url: null,
                },
            }),
        });

        const credit = query('[data-test="public-layout-system-credit"]');

        expect(credit.textContent).toContain('Céu de verão');
        expect(exists('[data-test="public-layout-system-credit-link"]')).toBe(
            false,
        );
    });

    it('duplica pelo servidor quando há conta', async () => {
        mountPage();

        query<HTMLButtonElement>(
            '[data-test="public-layout-system-duplicate"]',
        ).click();
        await nextTick();

        expect(routerMock.post).toHaveBeenCalledTimes(1);
        expect(routerMock.post).toHaveBeenCalledWith(
            '/pt-br/colors/s/atlas/duplicate',
            {},
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('manda o anônimo pelo login antes de duplicar', () => {
        mountPage({ authenticated: false });

        expect(exists('[data-test="public-layout-system-duplicate"]')).toBe(
            false,
        );
        expect(
            query<HTMLAnchorElement>(
                '[data-test="public-layout-system-duplicate-login"]',
            ).getAttribute('href'),
        ).toBe('/pt-br/colors/s/atlas/duplicate');
    });

    it('não oferece duplicar quando a cópia não é permitida', () => {
        mountPage({ authenticated: false, canDuplicate: false });

        expect(
            exists('[data-test="public-layout-system-duplicate-login"]'),
        ).toBe(false);
        expect(exists('[data-test="public-layout-system-duplicate"]')).toBe(
            false,
        );
    });

    it('volta do login com ?duplicate=1, limpa a query e duplica uma vez', async () => {
        pageState.url = '/pt-br/colors/s/atlas?duplicate=1';

        mountPage();
        await nextTick();

        expect(routerMock.post).toHaveBeenCalledTimes(1);
        expect(window.location.search).toBe('');
    });

    it('não duplica sozinha sem a marca da volta do login', async () => {
        mountPage();
        await nextTick();

        expect(routerMock.post).not.toHaveBeenCalled();
    });
});
