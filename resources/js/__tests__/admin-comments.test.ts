import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h, nextTick } from 'vue';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import CommentModeration from '@/pages/admin/CommentModeration.vue';
import type { AdminCommentModerationRow, Paginator } from '@/types';

/**
 * Moderacao de comentarios no painel (F7, ADR-139/ADR-140).
 *
 * A fila mostra o texto em qualquer estado, decide por aprovar/rejeitar e so
 * exclui depois da confirmacao. As rotas sao as de verdade (`@/routes/admin`),
 * entao o teste vale como contrato do que a tela chama.
 */

const { routerGet, routerPost, routerDelete } = vi.hoisted(() => ({
    routerGet: vi.fn(),
    routerPost: vi.fn(),
    routerDelete: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    router: { get: routerGet, post: routerPost, delete: routerDelete },
    Head: defineComponent({
        name: 'InertiaHead',
        props: { title: { type: String, default: '' } },
        render: () => h('div'),
    }),
}));

const TRADUCOES = {
    admin: {
        common: {
            empty_title: 'NADA_AQUI',
            actions: 'ACOES',
            total: 'TOTAL {total}',
            page_of: 'PAGINA {page} DE {last}',
            previous: 'ANTERIOR',
            next: 'PROXIMA',
            cancel: 'CANCELAR',
        },
        comments: {
            title: 'MODERACAO',
            description: 'DESCRICAO_MODERACAO',
            empty: 'VAZIO_MODERACAO',
            filter: 'STATUS',
            search: 'BUSCAR',
            search_placeholder: 'DICA_BUSCA',
            comment: 'COMENTARIO',
            author: 'AUTOR',
            palette: 'PALETA',
            reports: 'DENUNCIAS',
            created_at: 'CRIADO',
            deleted: 'EXCLUIDO',
            approve: 'APROVAR',
            reject: 'REJEITAR',
            delete: 'EXCLUIR',
            delete_confirm_title: 'TITULO_EXCLUIR',
            delete_confirm_description: 'DESCRICAO_EXCLUIR',
            statuses: {
                pending: 'EM_ANALISE',
                flagged: 'SINALIZADO',
                rejected: 'REJEITADO',
                approved: 'APROVADO',
                all: 'TODOS',
            },
        },
    },
};

function i18nDoTeste() {
    const i18n = createI18nInstance();
    applyTranslations(i18n, { locale: 'pt_BR', translations: TRADUCOES });
    return i18n;
}

function linha(
    overrides: Partial<AdminCommentModerationRow> = {},
): AdminCommentModerationRow {
    return {
        id: '01ARZ3NDEKTSV4RRFFQ69G5FAV',
        body: 'Texto em analise',
        approvedBody: null,
        status: 'pending',
        deleted: false,
        paletteName: 'Oceano',
        paletteSlug: 'oceano',
        authorName: 'Ana',
        authorUsername: 'ana',
        createdAt: '15/11/2026 10:00',
        pendingReports: 0,
        ...overrides,
    };
}

function pagina(rows: AdminCommentModerationRow[]) {
    return {
        data: rows,
        current_page: 1,
        last_page: 1,
        total: rows.length,
    } satisfies Paginator<AdminCommentModerationRow>;
}

function montar(rows: AdminCommentModerationRow[]) {
    return mount(CommentModeration, {
        props: {
            comments: pagina(rows),
            filters: { status: 'pending', q: '' },
        },
        attachTo: document.body,
        global: { plugins: [i18nDoTeste()] },
    });
}

beforeEach(() => {
    routerGet.mockReset();
    routerPost.mockReset();
    routerDelete.mockReset();
    document.body.innerHTML = '';
});

describe('moderacao de comentarios', () => {
    it('mostra o texto, o estado, a contagem de denuncias e o excluido', () => {
        const wrapper = montar([
            linha({ pendingReports: 2 }),
            linha({
                id: '01ARZ3NDEKTSV4RRFFQ69G5FAW',
                body: 'Texto aprovado',
                approvedBody: 'Texto aprovado',
                status: 'approved',
                deleted: true,
            }),
        ]);

        const texto = wrapper.text();

        expect(texto).toContain('Texto em analise');
        expect(texto).toContain('Ana');
        expect(texto).toContain('Oceano');
        expect(texto).toContain('EM_ANALISE');
        expect(texto).toContain('APROVADO');
        expect(texto).toContain('EXCLUIDO');
        expect(wrapper.findAll('tbody tr')[0]?.text()).toContain('2');

        wrapper.unmount();
    });

    it('aprova e rejeita pela rota do comentario', async () => {
        const wrapper = montar([linha()]);

        await wrapper
            .get('[data-test="comment-moderation-approve"]')
            .trigger('click');
        await wrapper
            .get('[data-test="comment-moderation-reject"]')
            .trigger('click');

        expect(routerPost).toHaveBeenNthCalledWith(
            1,
            '/admin/comments/01ARZ3NDEKTSV4RRFFQ69G5FAV/approve',
            {},
            expect.objectContaining({ preserveScroll: true }),
        );
        expect(routerPost).toHaveBeenNthCalledWith(
            2,
            '/admin/comments/01ARZ3NDEKTSV4RRFFQ69G5FAV/reject',
            {},
            expect.objectContaining({ preserveScroll: true }),
        );

        wrapper.unmount();
    });

    it('aplica o status e a busca pela URL da fila', async () => {
        const wrapper = montar([linha()]);

        await wrapper.get('input[type="search"]').setValue('ana');
        await wrapper.get('form').trigger('submit');

        expect(routerGet).toHaveBeenCalledWith(
            '/admin/comments',
            { status: 'pending', q: 'ana' },
            expect.objectContaining({ replace: true }),
        );

        wrapper.unmount();
    });

    it('so exclui depois da confirmacao', async () => {
        const wrapper = montar([linha()]);

        await wrapper
            .get('[data-test="comment-moderation-delete"]')
            .trigger('click');
        await nextTick();

        expect(document.body.innerHTML).toContain('TITULO_EXCLUIR');
        expect(routerDelete).not.toHaveBeenCalled();

        const confirmar = document.body.querySelector<HTMLElement>(
            '[data-test="comment-moderation-delete-confirm"]',
        );

        expect(confirmar).not.toBeNull();
        confirmar?.click();
        await nextTick();

        expect(routerDelete).toHaveBeenCalledWith(
            '/admin/comments/01ARZ3NDEKTSV4RRFFQ69G5FAV',
            expect.objectContaining({ preserveScroll: true }),
        );

        wrapper.unmount();
    });
});
