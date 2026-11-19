import { mount } from '@vue/test-utils';
import { defineComponent, h, nextTick } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { parseColor } from '@/lib/color';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import LayoutSystemEditor from '@/pages/dashboard/colors/LayoutSystemEditor.vue';
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
    LAYOUT_STATE_VERSION,
    buildLayoutState,
    type LayoutSystemStateV1,
} from '@/tools/layout-harmonizer/lib/state';
import { LAYOUT_SUGGESTIONS_VERSION } from '@/tools/layout-harmonizer/lib/suggestions';
import type {
    LayoutSystemEditor as LayoutSystemEditorData,
    LayoutSystemEditorProps,
} from '@/types/layoutSystem';
import { exportMessages } from './support/langMessages';

/**
 * Editor de um sistema de layout (F4, ADR-168/ADR-169).
 *
 * O quadro abre hidratado do estado gravado - sem regenerar - e o salvamento
 * sai do modal com os metadados do formulario. Arquivado e versao desconhecida
 * ficam somente leitura, com o ciclo de vida ainda disponivel.
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
        },
        url: '/dashboard/colors/layout-systems/x/edit',
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

function stateFixture(
    algorithmVersion: number = LAYOUT_ALGORITHM_VERSION,
): LayoutSystemStateV1 {
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
    snapshot.algorithmVersion = algorithmVersion;
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

const SYSTEM_ID = '01KSYSTEM00000000000000001';

function system(
    overrides: Partial<LayoutSystemEditorData> = {},
): LayoutSystemEditorData {
    return {
        id: SYSTEM_ID,
        name: 'Atlas',
        description: 'Sistema claro e escuro',
        visibility: 'private',
        tokens_count: 66,
        duplications_count: 0,
        archived: false,
        slug: null,
        preview: ['#3b82f6'],
        tags: [{ id: 'tag-1', name: 'web' }],
        updated_at: '2026-11-10T12:00:00+00:00',
        state: stateFixture(),
        schema_version: LAYOUT_SCHEMA_VERSION,
        algorithm_version: LAYOUT_ALGORITHM_VERSION,
        suggestions_version: LAYOUT_SUGGESTIONS_VERSION,
        snapshot_version: LAYOUT_SNAPSHOT_VERSION,
        archived_at: null,
        credit: { source: null, origin: null },
        ...overrides,
    };
}

function props(
    overrides: Partial<LayoutSystemEditorProps> = {},
): LayoutSystemEditorProps {
    return {
        system: system(),
        tags: [
            { id: 'tag-1', name: 'web' },
            { id: 'tag-2', name: 'marca' },
        ],
        limits: { limit: 25, used: 3, remaining: 22 },
        form: {
            nameMaxLength: 80,
            descriptionMaxLength: 500,
            tagsPerSystem: 10,
            tagNameMaxLength: 30,
        },
        currentVersions: {
            state: LAYOUT_STATE_VERSION,
            schema: LAYOUT_SCHEMA_VERSION,
            snapshot: LAYOUT_SNAPSHOT_VERSION,
            algorithm: LAYOUT_ALGORITHM_VERSION,
            suggestions: LAYOUT_SUGGESTIONS_VERSION,
        },
        ...overrides,
    };
}

function mountPage(overrides: Partial<LayoutSystemEditorProps> = {}) {
    return mount(LayoutSystemEditor, {
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

describe('Editor de sistema de layout', () => {
    beforeEach(() => {
        routerMock.get.mockReset();
        routerMock.post.mockReset();
        routerMock.patch.mockReset();
        routerMock.delete.mockReset();
        routerMock.on.mockClear();
        document.body.innerHTML = '';
        window.localStorage.clear();
    });

    it('hidrata o cabeçalho e o quadro sem regenerar', () => {
        mountPage();

        expect(
            query('[data-test="layout-system-editor-name"]').textContent,
        ).toContain('Atlas');
        expect(
            query('[data-test="layout-system-editor-tokens"]').textContent,
        ).toContain('66 tokens');
        expect(
            query<HTMLInputElement>(
                '[data-test="layout-system-meta-description"]',
            ).value,
        ).toBe('Sistema claro e escuro');

        const workspace = query('[data-test="layout-workspace"]');

        expect(workspace.dataset.mode).toBe('live');
        expect(
            document.querySelectorAll('[data-test="token-cell"]'),
        ).toHaveLength(66);
        expect(exists('[data-test="layout-system-editor-save"]')).toBe(true);
    });

    it('abre o modal com os metadados do formulário e envia o PATCH', async () => {
        mountPage();

        const name = query<HTMLInputElement>(
            '[data-test="layout-system-meta-name"]',
        );

        name.value = 'Atlas 2';
        name.dispatchEvent(new Event('input'));
        await nextTick();

        query<HTMLButtonElement>(
            '[data-test="layout-system-editor-save"]',
        ).click();
        await nextTick();

        const dialogName = query<HTMLInputElement>(
            '[data-test="layout-save-name"]',
        );

        expect(dialogName.value).toBe('Atlas 2');
        expect(exists('[data-test="layout-save-as-new"]')).toBe(true);

        query<HTMLButtonElement>('[data-test="layout-save-submit"]').click();
        await nextTick();

        expect(routerMock.patch).toHaveBeenCalledTimes(1);
        expect(routerMock.patch).toHaveBeenCalledWith(
            `/dashboard/colors/layout-systems/${SYSTEM_ID}`,
            expect.objectContaining({
                name: 'Atlas 2',
                description: 'Sistema claro e escuro',
                visibility: 'private',
                tags: ['web'],
                state: expect.objectContaining({ stateVersion: 1 }),
            }),
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('arquivado fica somente leitura, com quadro congelado e restaurar', () => {
        mountPage({
            system: system({
                archived: true,
                archived_at: '2026-11-18T10:00:00+00:00',
            }),
        });

        expect(exists('[data-test="layout-system-editor-archived"]')).toBe(
            true,
        );
        expect(
            document.querySelectorAll('[data-test="token-cell"]'),
        ).toHaveLength(66);
        expect(exists('[data-test="layout-harmonizer-panel"]')).toBe(false);
        expect(exists('[data-test="layout-system-editor-save"]')).toBe(false);
        expect(exists('[data-test="layout-system-action-restore"]')).toBe(true);
        expect(exists('[data-test="layout-system-meta-form"]')).toBe(false);
    });

    it('versão mais nova que o editor fica somente leitura com aviso', () => {
        const state = stateFixture(LAYOUT_ALGORITHM_VERSION + 1);

        mountPage({
            system: system({
                state,
                algorithm_version: LAYOUT_ALGORITHM_VERSION + 1,
            }),
        });

        expect(exists('[data-test="layout-system-editor-readonly"]')).toBe(
            true,
        );
        expect(
            query<HTMLButtonElement>('[data-test="layout-system-editor-save"]')
                .disabled,
        ).toBe(true);
        expect(query('[data-test="layout-workspace"]').dataset.mode).toBe(
            'readonly',
        );
    });

    it('exclui pelo diálogo com o nome exato', async () => {
        mountPage();

        query<HTMLButtonElement>(
            '[data-test="layout-system-action-delete"]',
        ).click();
        await nextTick();

        const input = query<HTMLInputElement>(
            '[data-test="layout-system-delete-name"]',
        );

        input.value = 'Atlas';
        input.dispatchEvent(new Event('input'));
        await nextTick();

        query<HTMLButtonElement>(
            '[data-test="layout-system-delete-confirm"]',
        ).click();
        await nextTick();

        expect(routerMock.delete).toHaveBeenCalledWith(
            `/dashboard/colors/layout-systems/${SYSTEM_ID}`,
            expect.objectContaining({
                data: { confirm_name: 'Atlas' },
            }),
        );
    });
});
