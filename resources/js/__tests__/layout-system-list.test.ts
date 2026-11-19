import { mount } from '@vue/test-utils';
import { defineComponent, h, nextTick } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { Select } from '@/components/ui/select';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import LayoutSystemsIndex from '@/pages/dashboard/colors/LayoutSystemsIndex.vue';
import type {
    LayoutSystemFilters,
    LayoutSystemRow,
    LayoutSystemsIndexProps,
} from '@/types/layoutSystem';

/**
 * Meus Sistemas de Layout (F4, ADR-170).
 *
 * A lista espelha Minhas Paletas: filtros do servidor com debounce na busca,
 * cota conjunta, vazio, e o ciclo de vida (duplicar, arquivar, excluir) sai da
 * propria pagina para o servidor.
 */

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
        url: '/dashboard/colors/layout-systems',
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

const TRADUCOES = {
    colors: {
        list: {
            badge_public: 'PUBLICO',
            badge_private: 'PRIVADO',
            badge_archived: 'ARQUIVADO',
            search_label: 'BUSCAR',
            search_placeholder: 'NOME_OU_TAG',
            status_label: 'SITUACAO',
            status_active: 'ATIVOS',
            status_archived: 'ARQUIVADOS',
            tag_label: 'TAG',
            tag_all: 'TODAS_AS_TAGS',
            sort_label: 'ORDENAR',
            sort_updated_at: 'ATUALIZACAO',
            sort_created_at: 'CRIACAO',
            sort_name: 'NOME',
            sort_duplications_count: 'USO',
            direction_desc: 'DECRESCENTE',
            direction_asc: 'CRESCENTE',
            clear: 'LIMPAR',
            empty_search: 'VAZIO_BUSCA',
            usage_zero: 'SEM_USO',
            usage: 'USO {count}',
            updated_at: 'ATUALIZADO {date}',
            results: '{from}-{to} DE {total}',
        },
        systems: {
            list: {
                title: 'MEUS_SISTEMAS',
                description: 'USO {used} DE {limit}',
                description_unlimited: 'USO {used} SEM_LIMITE',
                new_system: 'NOVO_SISTEMA',
                tokens_label: 'TOKENS',
                tokens_count: '{count} TOKENS',
                preview_alt: 'PREVIA {name}',
                empty_title: 'VAZIO_TITULO',
                empty_description: 'VAZIO_DESCRICAO',
                empty_cta: 'VAZIO_CTA',
            },
            actions: {
                share_unavailable: 'SEM_LINK',
            },
            delete: {
                title: 'EXCLUIR_SISTEMA',
                description: 'SEM_VOLTA',
                label: 'DIGITE_O_NOME',
                confirm: 'CONFIRMAR_EXCLUSAO',
                deleting: 'EXCLUINDO',
                cancel: 'CANCELAR',
            },
        },
        actions: {
            edit: 'EDITAR',
            duplicate: 'DUPLICAR',
            share: 'COMPARTILHAR',
            shared: 'COPIADO',
            archive: 'ARQUIVAR',
            restore: 'RESTAURAR',
            delete: 'EXCLUIR',
        },
        pagination: {
            previous: 'ANTERIOR',
            next: 'PROXIMA',
            page: 'PAGINA {page} DE {last}',
        },
    },
};

function i18n() {
    const instance = createI18nInstance();

    applyTranslations(instance, {
        locale: 'pt-BR',
        translations: TRADUCOES,
    });

    return instance;
}

const FILTERS: LayoutSystemFilters = {
    q: '',
    status: 'active',
    tag: null,
    sort: 'updated_at',
    direction: 'desc',
};

const SYSTEM_ID = '01KSYSTEM00000000000000001';

function row(overrides: Partial<LayoutSystemRow> = {}): LayoutSystemRow {
    return {
        id: SYSTEM_ID,
        name: 'Atlas',
        description: 'Sistema claro e escuro',
        visibility: 'private',
        tokens_count: 66,
        duplications_count: 0,
        archived: false,
        slug: null,
        preview: ['#3b82f6', '#6366f1', '#ffffff'],
        tags: [{ id: 'tag-1', name: 'web' }],
        updated_at: '2026-11-10T12:00:00+00:00',
        ...overrides,
    };
}

function props(
    overrides: Partial<LayoutSystemsIndexProps> = {},
): LayoutSystemsIndexProps {
    return {
        systems: {
            data: [row()],
            current_page: 1,
            last_page: 1,
            per_page: 20,
            total: 1,
            from: 1,
            to: 1,
        },
        filters: FILTERS,
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
        ...overrides,
    };
}

function mountPage(overrides: Partial<LayoutSystemsIndexProps> = {}) {
    return mount(LayoutSystemsIndex, {
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

async function typeSearch(value: string): Promise<void> {
    const input = query<HTMLInputElement>(
        '[data-test="layout-systems-search"]',
    );

    input.value = value;
    input.dispatchEvent(new Event('input'));
    await nextTick();
}

describe('Meus Sistemas de Layout', () => {
    beforeEach(() => {
        routerMock.get.mockReset();
        routerMock.post.mockReset();
        routerMock.patch.mockReset();
        routerMock.delete.mockReset();
        document.body.innerHTML = '';
    });

    it('mostra a cota conjunta, os cartões e a paginação', () => {
        mountPage({
            systems: {
                data: [row()],
                current_page: 1,
                last_page: 2,
                per_page: 20,
                total: 21,
                from: 1,
                to: 20,
            },
        });

        const text = query('[data-test="layout-systems-index"]').textContent;

        expect(text).toContain('USO 3 DE 25');
        expect(text).toContain('Atlas');
        expect(text).toContain('66 TOKENS');
        expect(
            query('[data-test="layout-system-card-usage"]').textContent,
        ).toContain('SEM_USO');
        expect(query('[data-test="palette-page-info"]').textContent).toContain(
            '1-20 DE 21',
        );
    });

    it('avisa quando a cota é ilimitada', () => {
        mountPage({ limits: { limit: null, used: 7, remaining: null } });

        expect(
            query('[data-test="layout-systems-index"]').textContent,
        ).toContain('USO 7 SEM_LIMITE');
    });

    it('espera 300 ms antes de buscar', async () => {
        vi.useFakeTimers();

        try {
            mountPage();

            await typeSearch('atlas');

            await vi.advanceTimersByTimeAsync(299);
            expect(routerMock.get).not.toHaveBeenCalled();

            await vi.advanceTimersByTimeAsync(1);
            expect(routerMock.get).toHaveBeenCalledTimes(1);
            expect(routerMock.get).toHaveBeenCalledWith(
                '/dashboard/colors/layout-systems',
                {
                    q: 'atlas',
                    status: 'active',
                    sort: 'updated_at',
                    direction: 'desc',
                    page: 1,
                },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        } finally {
            vi.useRealTimers();
        }
    });

    it('filtra por situação na hora, sem esperar o debounce', async () => {
        const wrapper = mountPage();

        wrapper
            .findAllComponents(Select)[0]
            ?.vm.$emit('update:modelValue', 'archived');
        await nextTick();

        expect(routerMock.get).toHaveBeenCalledWith(
            '/dashboard/colors/layout-systems',
            {
                status: 'archived',
                sort: 'updated_at',
                direction: 'desc',
                page: 1,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    });

    it('limpa os filtros e volta ao padrão', async () => {
        mountPage({
            filters: {
                ...FILTERS,
                q: 'atlas',
                status: 'archived',
                tag: 'tag-1',
            },
        });

        query<HTMLButtonElement>('[data-test="layout-systems-clear"]').click();
        await nextTick();

        expect(routerMock.get).toHaveBeenCalledTimes(1);
        expect(routerMock.get).toHaveBeenCalledWith(
            '/dashboard/colors/layout-systems',
            {
                status: 'active',
                sort: 'updated_at',
                direction: 'desc',
                page: 1,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    });

    it('mostra o estado vazio com o convite ao editor', () => {
        mountPage({
            systems: {
                data: [],
                current_page: 1,
                last_page: 1,
                per_page: 20,
                total: 0,
                from: null,
                to: null,
            },
        });

        const empty = query('[data-test="layout-systems-empty"]');

        expect(empty.textContent).toContain('VAZIO_TITULO');
        expect(empty.textContent).toContain('VAZIO_DESCRICAO');
        expect(empty.textContent).toContain('VAZIO_CTA');
    });

    it('distingue busca sem resultado de lista vazia', () => {
        mountPage({
            filters: { ...FILTERS, q: 'nada' },
            systems: {
                data: [],
                current_page: 1,
                last_page: 1,
                per_page: 20,
                total: 0,
                from: null,
                to: null,
            },
        });

        const empty = query('[data-test="layout-systems-empty"]');

        expect(empty.textContent).toContain('VAZIO_BUSCA');
        expect(empty.textContent).not.toContain('VAZIO_CTA');
    });

    it('aponta o cartão para o editor', () => {
        mountPage();

        expect(
            query<HTMLAnchorElement>(
                '[data-test="layout-system-card-name"]',
            ).getAttribute('href'),
        ).toBe(`/dashboard/colors/layout-systems/${SYSTEM_ID}/edit`);
    });

    it('duplica, arquiva e restaura pelo servidor', async () => {
        mountPage();

        query<HTMLButtonElement>(
            '[data-test="layout-system-action-duplicate"]',
        ).click();
        await nextTick();

        expect(routerMock.post).toHaveBeenCalledWith(
            `/dashboard/colors/layout-systems/${SYSTEM_ID}/duplicate`,
            {},
            { preserveScroll: true },
        );

        query<HTMLButtonElement>(
            '[data-test="layout-system-action-archive"]',
        ).click();
        await nextTick();

        expect(routerMock.patch).toHaveBeenCalledWith(
            `/dashboard/colors/layout-systems/${SYSTEM_ID}/archive`,
            {},
            { preserveScroll: true },
        );
    });

    it('exclui só com o nome exato e manda a confirmação', async () => {
        mountPage();

        query<HTMLButtonElement>(
            '[data-test="layout-system-action-delete"]',
        ).click();
        await nextTick();

        const input = query<HTMLInputElement>(
            '[data-test="layout-system-delete-name"]',
        );
        const confirm = query<HTMLButtonElement>(
            '[data-test="layout-system-delete-confirm"]',
        );

        expect(confirm.disabled).toBe(true);

        input.value = 'Atlas';
        input.dispatchEvent(new Event('input'));
        await nextTick();

        expect(confirm.disabled).toBe(false);

        confirm.click();
        await nextTick();

        expect(routerMock.delete).toHaveBeenCalledTimes(1);
        expect(routerMock.delete).toHaveBeenCalledWith(
            `/dashboard/colors/layout-systems/${SYSTEM_ID}`,
            expect.objectContaining({
                data: { confirm_name: 'Atlas' },
                preserveScroll: true,
            }),
        );
    });

    it('desabilita o compartilhamento sem slug público', () => {
        mountPage();

        expect(
            query<HTMLButtonElement>('[data-test="layout-system-action-share"]')
                .disabled,
        ).toBe(true);
        expect(exists('[data-test="layout-systems-new"]')).toBe(true);
    });
});
