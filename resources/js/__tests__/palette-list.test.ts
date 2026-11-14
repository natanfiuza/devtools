import { mount } from '@vue/test-utils';
import { defineComponent, h, nextTick } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { Select } from '@/components/ui/select';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import PalettesIndex from '@/pages/dashboard/colors/PalettesIndex.vue';
import type {
    FavoritePaletteRow,
    PaletteFilters,
    PaletteRow,
    PalettesIndexProps,
} from '@/types/colors';

/**
 * Minhas Paletas (F4, ADR-104/ADR-109).
 *
 * Busca, filtro e ordem sao do servidor: a pagina so monta a query e chama
 * `router.get` com `preserveState`/`replace`. O teste confere o debounce da
 * busca, o filtro imediato, a limpeza dos filtros, o estado vazio e as duas
 * acoes destrutivas (excluir com o nome exato e gerenciar tags).
 */

const { routerMock, pageState } = vi.hoisted(() => ({
    routerMock: {
        get: vi.fn(),
        post: vi.fn(),
        patch: vi.fn(),
        delete: vi.fn(),
    },
    pageState: {
        props: {
            localeSlug: 'pt-br',
            auth: { user: { id: 1 } },
        },
        url: '/dashboard/colors/palettes',
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
            title: 'MINHAS_PALETAS',
            description: 'USO {used} DE {limit}',
            description_unlimited: 'USO {used} SEM_LIMITE',
            search_label: 'BUSCAR',
            search_placeholder: 'NOME_OU_TAG',
            status_label: 'SITUACAO',
            status_active: 'ATIVAS',
            status_archived: 'ARQUIVADAS',
            tag_label: 'TAG',
            tag_all: 'TODAS_AS_TAGS',
            sort_label: 'ORDENAR',
            sort_updated_at: 'ATUALIZACAO',
            sort_created_at: 'CRIACAO',
            sort_name: 'NOME',
            sort_colors_count: 'CORES',
            sort_duplications_count: 'USO',
            direction_label: 'ORDEM',
            direction_desc: 'DECRESCENTE',
            direction_asc: 'CRESCENTE',
            clear: 'LIMPAR',
            manage_tags: 'GERENCIAR_TAGS',
            new_palette: 'NOVA_PALETA',
            empty_title: 'VAZIO_TITULO',
            empty_description: 'VAZIO_DESCRICAO',
            empty_cta: 'VAZIO_CTA',
            empty_search: 'VAZIO_BUSCA',
            kind_scale: 'ESCALA',
            kind_extracted: 'DA_IMAGEM',
            badge_public: 'PUBLICA',
            badge_private: 'PRIVADA',
            badge_archived: 'ARQUIVADA',
            colors_count: '{count} CORES',
            usage_zero: 'SEM_USO',
            usage: 'USO {count}',
            updated_at: 'ATUALIZADA {date}',
            preview_alt: 'PREVIA {name}',
            results: '{from}-{to} DE {total}',
        },
        favorites: {
            tab_mine: 'ABA_MINHAS',
            tab_favorites: 'ABA_FAVORITAS',
            empty_title: 'SEM_FAVORITAS',
            empty_description: 'DICA_FAVORITAS',
            empty_cta: 'VER_MINHAS',
            favorited_at: 'FAVORITA_EM {date}',
            remove: 'REMOVER_FAVORITO',
            removing: 'REMOVENDO_FAVORITO',
            remove_error: 'ERRO_REMOVER_FAVORITO',
        },
        actions: {
            edit: 'EDITAR',
            prototype: 'PROTOTIPO',
            duplicate: 'DUPLICAR',
            share: 'COMPARTILHAR',
            share_unavailable: 'SEM_LINK',
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
        delete: {
            title: 'EXCLUIR_PALETA',
            description: 'SEM_VOLTA',
            label: 'DIGITE_O_NOME',
            confirm: 'CONFIRMAR_EXCLUSAO',
            deleting: 'EXCLUINDO',
            cancel: 'CANCELAR',
            mismatch: 'NAO_CONFERE',
        },
        tags_manager: {
            title: 'GERENCIAR_TAGS_TITULO',
            description: 'DESC_TAGS',
            empty: 'SEM_TAGS',
            new_placeholder: 'NOVA_TAG',
            create: 'CRIAR',
            rename: 'RENOMEAR',
            save: 'SALVAR',
            cancel: 'CANCELAR',
            delete: 'EXCLUIR_TAG',
            confirm_delete: 'EXCLUIR {name}?',
            confirm_delete_hint: 'SAI_DE_TODAS',
            confirm_delete_action: 'EXCLUIR_TAG_AGORA',
            up: 'SUBIR {name}',
            down: 'DESCER {name}',
            close: 'FECHAR',
            errors: {
                name_required: 'NOME_OBRIGATORIO',
                name_too_long: 'NOME_LONGO {max}',
            },
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

const FILTERS: PaletteFilters = {
    q: '',
    status: 'active',
    tag: null,
    sort: 'updated_at',
    direction: 'desc',
};

function row(overrides: Partial<PaletteRow> = {}): PaletteRow {
    return {
        id: '01KPALETTE00000000000000001',
        name: 'Oceano',
        description: 'Azuis profundos',
        kind: 'scale',
        visibility: 'private',
        colors_count: 11,
        duplications_count: 0,
        archived: false,
        slug: null,
        preview: ['#eef2ff', '#6366f1', '#1e1b4b'],
        tags: [{ id: 'tag-1', name: 'web' }],
        updated_at: '2026-11-10T12:00:00+00:00',
        ...overrides,
    };
}

function favoriteRow(
    overrides: Partial<FavoritePaletteRow> = {},
): FavoritePaletteRow {
    return {
        ...row(),
        slug: 'oceano-publica',
        visibility: 'public',
        favorited_at: '2026-11-12T10:00:00+00:00',
        ...overrides,
    };
}

function props(
    overrides: Partial<PalettesIndexProps> = {},
): PalettesIndexProps {
    return {
        tab: 'mine',
        palettes: {
            data: [row()],
            current_page: 1,
            last_page: 1,
            per_page: 20,
            total: 1,
            from: 1,
            to: 1,
        },
        favorites: null,
        filters: FILTERS,
        tags: [
            { id: 'tag-1', name: 'web' },
            { id: 'tag-2', name: 'marca' },
        ],
        limits: { limit: 25, used: 3, remaining: 22 },
        form: {
            nameMaxLength: 80,
            descriptionMaxLength: 500,
            tagsPerPalette: 10,
            tagNameMaxLength: 30,
            sourceLabelMaxLength: 80,
        },
        ...overrides,
    };
}

function mountPage(overrides: Partial<PalettesIndexProps> = {}) {
    return mount(PalettesIndex, {
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
    const input = query<HTMLInputElement>('[data-test="palettes-search"]');

    input.value = value;
    input.dispatchEvent(new Event('input'));
    await nextTick();
}

describe('Minhas Paletas', () => {
    beforeEach(() => {
        routerMock.get.mockReset();
        routerMock.post.mockReset();
        routerMock.patch.mockReset();
        routerMock.delete.mockReset();
        document.body.innerHTML = '';
    });

    it('mostra a cota, os cartões e a paginação', async () => {
        mountPage({
            palettes: {
                data: [row()],
                current_page: 1,
                last_page: 2,
                per_page: 20,
                total: 21,
                from: 1,
                to: 20,
            },
        });

        const text = query('[data-test="palettes-index"]').textContent;

        expect(text).toContain('USO 3 DE 25');
        expect(text).toContain('Oceano');
        expect(text).toContain('ATUALIZADA');
        expect(query('[data-test="palette-card-usage"]').textContent).toContain(
            'SEM_USO',
        );
        expect(query('[data-test="palette-page-info"]').textContent).toContain(
            '1-20 DE 21',
        );
    });

    it('avisa quando a cota é ilimitada', async () => {
        mountPage({
            limits: { limit: null, used: 7, remaining: null },
        });

        expect(
            query('[data-test="palettes-index"]').textContent,
        ).toContain('USO 7 SEM_LIMITE');
    });

    it('espera 300 ms antes de buscar', async () => {
        vi.useFakeTimers();

        try {
            mountPage();

            await typeSearch('azul');

            await vi.advanceTimersByTimeAsync(299);
            expect(routerMock.get).not.toHaveBeenCalled();

            await vi.advanceTimersByTimeAsync(1);
            expect(routerMock.get).toHaveBeenCalledTimes(1);
            expect(routerMock.get).toHaveBeenCalledWith(
                '/dashboard/colors/palettes',
                {
                    q: 'azul',
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

        const selects = wrapper.findAllComponents(Select);

        expect(selects).toHaveLength(4);

        selects[0]?.vm.$emit('update:modelValue', 'archived');
        await nextTick();

        expect(routerMock.get).toHaveBeenCalledWith(
            '/dashboard/colors/palettes',
            {
                status: 'archived',
                sort: 'updated_at',
                direction: 'desc',
                page: 1,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    });

    it('filtra pela tag da conta', async () => {
        const wrapper = mountPage();

        wrapper.findAllComponents(Select)[1]?.vm.$emit(
            'update:modelValue',
            'tag-2',
        );
        await nextTick();

        expect(routerMock.get).toHaveBeenCalledWith(
            '/dashboard/colors/palettes',
            {
                status: 'active',
                tag: 'tag-2',
                sort: 'updated_at',
                direction: 'desc',
                page: 1,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    });

    it('limpa os filtros e volta ao padrão', async () => {
        mountPage({
            filters: { ...FILTERS, q: 'azul', status: 'archived', tag: 'tag-1' },
        });

        query<HTMLButtonElement>('[data-test="palettes-clear"]').click();
        await nextTick();

        expect(routerMock.get).toHaveBeenCalledTimes(1);
        expect(routerMock.get).toHaveBeenCalledWith(
            '/dashboard/colors/palettes',
            { status: 'active', sort: 'updated_at', direction: 'desc', page: 1 },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    });

    it('mostra o estado vazio com o convite ao gerador', async () => {
        mountPage({
            palettes: {
                data: [],
                current_page: 1,
                last_page: 1,
                per_page: 20,
                total: 0,
                from: null,
                to: null,
            },
        });

        const empty = query('[data-test="palettes-empty"]');

        expect(empty.textContent).toContain('VAZIO_TITULO');
        expect(empty.textContent).toContain('VAZIO_DESCRICAO');
        expect(empty.textContent).toContain('VAZIO_CTA');
    });

    it('distingue busca sem resultado de lista vazia', async () => {
        mountPage({
            filters: { ...FILTERS, q: 'nada' },
            palettes: {
                data: [],
                current_page: 1,
                last_page: 1,
                per_page: 20,
                total: 0,
                from: null,
                to: null,
            },
        });

        const empty = query('[data-test="palettes-empty"]');

        expect(empty.textContent).toContain('VAZIO_BUSCA');
        expect(empty.textContent).not.toContain('VAZIO_CTA');
    });

    it('exclui só com o nome exato e manda a confirmação', async () => {
        mountPage();

        query<HTMLButtonElement>(
            '[data-test="palette-action-delete"]',
        ).click();
        await nextTick();

        const input = query<HTMLInputElement>(
            '[data-test="palette-delete-name"]',
        );
        const confirm = query<HTMLButtonElement>(
            '[data-test="palette-delete-confirm"]',
        );

        expect(confirm.disabled).toBe(true);

        input.value = 'Oceano';
        input.dispatchEvent(new Event('input'));
        await nextTick();

        expect(confirm.disabled).toBe(false);

        confirm.click();
        await nextTick();

        expect(routerMock.delete).toHaveBeenCalledTimes(1);
        expect(routerMock.delete).toHaveBeenCalledWith(
            '/dashboard/colors/palettes/01KPALETTE00000000000000001',
            expect.objectContaining({
                data: { confirm_name: 'Oceano' },
                preserveScroll: true,
            }),
        );
    });

    it('abre o gerenciador com as tags da conta', async () => {
        mountPage();

        query<HTMLButtonElement>(
            '[data-test="palettes-manage-tags"]',
        ).click();
        await nextTick();

        const rows = document.querySelectorAll('[data-test="tag-manager-row"]');

        expect(rows).toHaveLength(2);
        expect(rows[0]?.textContent).toContain('web');
    });

    it('mostra as abas e alterna para Favoritas pelo servidor', async () => {
        mountPage();

        expect(
            query('[data-test="palettes-tabs"]').textContent,
        ).toContain('ABA_MINHAS');

        query<HTMLButtonElement>(
            '[data-test="palettes-tab-favorites"]',
        ).click();
        await nextTick();

        expect(routerMock.get).toHaveBeenCalledWith(
            '/dashboard/colors/palettes',
            { tab: 'favorites', page: 1 },
            expect.objectContaining({
                preserveState: true,
                preserveScroll: true,
                replace: true,
            }),
        );
    });

    it('na aba Favoritas esconde os filtros e lista as paletas salvas', () => {
        mountPage({
            tab: 'favorites',
            favorites: {
                data: [favoriteRow()],
                current_page: 1,
                last_page: 1,
                per_page: 20,
                total: 1,
                from: 1,
                to: 1,
            },
        });

        expect(exists('[data-test="palettes-filters"]')).toBe(false);
        expect(exists('[data-test="palettes-tabs"]')).toBe(true);

        const list = query('[data-test="favorites-list"]');

        expect(list.textContent).toContain('Oceano');
        expect(list.textContent).toContain('FAVORITA_EM');
        expect(exists('[data-test="favorite-palette-remove"]')).toBe(true);
        expect(exists('[data-test="palettes-list"]')).toBe(false);
    });

    it('mostra o estado vazio das favoritas', () => {
        mountPage({
            tab: 'favorites',
            favorites: {
                data: [],
                current_page: 1,
                last_page: 1,
                per_page: 20,
                total: 0,
                from: null,
                to: null,
            },
        });

        expect(
            query('[data-test="favorites-empty"]').textContent,
        ).toContain('SEM_FAVORITAS');
        expect(exists('[data-test="palettes-empty"]')).toBe(false);
    });
});
