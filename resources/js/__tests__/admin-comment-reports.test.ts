import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h } from 'vue';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import CommentReports from '@/pages/admin/CommentReports.vue';
import type { AdminCommentReportRow, Paginator } from '@/types';

/**
 * Denuncias de comentario no painel (F7, ADR-141).
 *
 * A tela mostra o comentario denunciado em qualquer estado e decide entre
 * resolver e dispensar a denuncia. Encerrar a denuncia nao altera o
 * comentario. As rotas sao as de verdade (`@/routes/admin`), entao o teste
 * vale como contrato do que a tela chama.
 */

const { routerGet, routerPost } = vi.hoisted(() => ({
    routerGet: vi.fn(),
    routerPost: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    router: { get: routerGet, post: routerPost },
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
        },
        comment_reports: {
            title: 'DENUNCIAS',
            description: 'DESCRICAO_DENUNCIAS',
            empty: 'VAZIO_DENUNCIAS',
            filter: 'STATUS',
            comment: 'COMENTARIO',
            reason: 'MOTIVO',
            reporter: 'DENUNCIANTE',
            status: 'SITUACAO',
            reported_at: 'DENUNCIADO_EM',
            deleted: 'EXCLUIDO',
            resolve: 'RESOLVER',
            dismiss: 'DISPENSAR',
            statuses: {
                pending: 'PENDENTE',
                resolved: 'RESOLVIDA',
                dismissed: 'DISPENSADA',
                all: 'TODAS',
            },
            comment_statuses: {
                pending: 'EM_ANALISE',
                approved: 'APROVADO',
                rejected: 'REJEITADO',
                flagged: 'SINALIZADO',
            },
        },
    },
    colors: {
        reports: {
            reasons: {
                spam: 'SPAM',
                harassment: 'ASSEDIO',
                hate: 'ODIO',
                sexual: 'SEXUAL',
                other: 'OUTRO',
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
    overrides: Partial<AdminCommentReportRow> = {},
): AdminCommentReportRow {
    return {
        id: '01ARZ3NDEKTSV4RRFFQ69G5FAV',
        reason: 'harassment',
        status: 'pending',
        reportedAt: '15/11/2026 10:00',
        resolvedAt: null,
        resolverName: null,
        reporterName: 'Bruno',
        reporterEmail: 'bruno@exemplo.com',
        comment: {
            id: '01ARZ3NDEKTSV4RRFFQ69G5FAW',
            body: 'Texto denunciado',
            status: 'approved',
            deleted: false,
            paletteName: 'Oceano',
            paletteSlug: 'oceano',
            authorName: 'Ana',
        },
        ...overrides,
    };
}

function pagina(rows: AdminCommentReportRow[]) {
    return {
        data: rows,
        current_page: 1,
        last_page: 1,
        total: rows.length,
    } satisfies Paginator<AdminCommentReportRow>;
}

function montar(rows: AdminCommentReportRow[]) {
    return mount(CommentReports, {
        props: {
            reports: pagina(rows),
            filters: { status: 'pending' },
        },
        attachTo: document.body,
        global: { plugins: [i18nDoTeste()] },
    });
}

beforeEach(() => {
    routerGet.mockReset();
    routerPost.mockReset();
    document.body.innerHTML = '';
});

describe('denuncias de comentario', () => {
    it('mostra o comentario denunciado, o motivo, o denunciante, o status e a data', () => {
        const wrapper = montar([
            linha(),
            linha({
                id: '01ARZ3NDEKTSV4RRFFQ69G5FAX',
                reason: 'spam',
                status: 'resolved',
                resolvedAt: '15/11/2026 11:00',
                resolverName: 'Admin',
                reporterName: null,
                reporterEmail: null,
                comment: {
                    id: '01ARZ3NDEKTSV4RRFFQ69G5FAY',
                    body: 'Outro comentario',
                    status: 'rejected',
                    deleted: true,
                    paletteName: 'Floresta',
                    paletteSlug: 'floresta',
                    authorName: null,
                },
            }),
        ]);

        const texto = wrapper.text();

        expect(texto).toContain('Texto denunciado');
        expect(texto).toContain('Oceano');
        expect(texto).toContain('Ana');
        expect(texto).toContain('ASSEDIO');
        expect(texto).toContain('PENDENTE');
        expect(texto).toContain('APROVADO');
        expect(texto).toContain('15/11/2026 10:00');
        expect(texto).toContain('Bruno');
        expect(texto).toContain('bruno@exemplo.com');

        expect(texto).toContain('Outro comentario');
        expect(texto).toContain('SPAM');
        expect(texto).toContain('RESOLVIDA');
        expect(texto).toContain('REJEITADO');
        expect(texto).toContain('EXCLUIDO');
        expect(texto).toContain('15/11/2026 11:00');
        expect(texto).toContain('Admin');
        // Denunciante excluido ou anonimo aparece como travessao.
        expect(wrapper.findAll('tbody tr')[1]?.text()).toContain('—');

        wrapper.unmount();
    });

    it('resolve e dispensa pela rota da denuncia', async () => {
        const wrapper = montar([linha()]);

        await wrapper
            .get('[data-test="comment-report-resolve"]')
            .trigger('click');
        await wrapper
            .get('[data-test="comment-report-dismiss"]')
            .trigger('click');

        expect(routerPost).toHaveBeenNthCalledWith(
            1,
            '/admin/comment-reports/01ARZ3NDEKTSV4RRFFQ69G5FAV/resolve',
            {},
            expect.objectContaining({ preserveScroll: true }),
        );
        expect(routerPost).toHaveBeenNthCalledWith(
            2,
            '/admin/comment-reports/01ARZ3NDEKTSV4RRFFQ69G5FAV/dismiss',
            {},
            expect.objectContaining({ preserveScroll: true }),
        );

        wrapper.unmount();
    });

    it('so mostra os botoes em denuncias pendentes', () => {
        const wrapper = montar([
            linha(),
            linha({
                id: '01ARZ3NDEKTSV4RRFFQ69G5FAZ',
                status: 'resolved',
                resolvedAt: '15/11/2026 11:00',
            }),
            linha({
                id: '01ARZ3NDEKTSV4RRFFQ69G5FB0',
                status: 'dismissed',
                resolvedAt: '15/11/2026 12:00',
            }),
        ]);

        const linhas = wrapper.findAll('tbody tr');

        expect(linhas).toHaveLength(3);
        expect(
            linhas[0]?.findAll('[data-test="comment-report-resolve"]'),
        ).toHaveLength(1);
        expect(
            linhas[0]?.findAll('[data-test="comment-report-dismiss"]'),
        ).toHaveLength(1);
        expect(
            linhas[1]?.findAll('[data-test="comment-report-resolve"]'),
        ).toHaveLength(0);
        expect(
            linhas[2]?.findAll('[data-test="comment-report-dismiss"]'),
        ).toHaveLength(0);

        wrapper.unmount();
    });
});
