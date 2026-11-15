import { flushPromises, mount } from '@vue/test-utils';
import { defineComponent, h, nextTick } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import CommentThread from '@/components/colors/CommentThread.vue';
import type { ColorComment, ColorCommentPaginator } from '@/types/colors';

/**
 * Thread de comentarios da pagina publica (F7, ADR-139).
 *
 * O servidor decide o `body` exibido; aqui so conferimos a fiacao: raiz e
 * respostas, selo "em analise", tombstone ao excluir pai com respostas,
 * formulario apenas para conta logada e o erro traduzido quando a API recusa.
 */

const { routerMock, pageState, fetchMock } = vi.hoisted(() => ({
    routerMock: {
        get: vi.fn(),
        post: vi.fn(),
        patch: vi.fn(),
        delete: vi.fn(),
    },
    pageState: {
        url: '/en/colors/p/oceano',
        props: { localeSlug: 'en' },
    },
    fetchMock: vi.fn(),
}));

vi.stubGlobal('fetch', fetchMock);

vi.mock('@inertiajs/vue3', () => ({
    router: routerMock,
    usePage: () => pageState,
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
        comments: {
            title: 'COMENTARIOS',
            empty: 'SEM_COMENTARIOS',
            placeholder: 'ESCREVA',
            reply_placeholder: 'RESPONDA',
            submit: 'ENVIAR',
            reply_submit: 'ENVIAR_RESPOSTA',
            reply: 'RESPONDER',
            edit: 'EDITAR',
            edit_save: 'SALVAR',
            delete: 'EXCLUIR',
            delete_confirm: 'CONFIRMAR_EXCLUSAO',
            cancel: 'CANCELAR',
            pending_badge: 'EM_ANALISE',
            edited: 'EDITADO',
            deleted_tombstone: 'REMOVIDO',
            removed_author: 'CONTA_REMOVIDA',
            replies_truncated: 'RESPOSTAS_OCULTAS',
            page_prev: 'ANTERIOR',
            page_next: 'PROXIMA',
            page_status: 'PAGINA {page} DE {pages}',
            error_empty: 'VAZIO',
            error_too_long: 'LONGO {max}',
            error_invalid: 'INVALIDO',
            error_rate_limited: 'LIMITE',
            error_generic: 'GENERICO',
        },
        reports: {
            title: 'DENUNCIAR_TITULO',
            open: 'DENUNCIAR',
            reason_label: 'MOTIVO',
            submit: 'ENVIAR_DENUNCIA',
            cancel: 'CANCELAR_DENUNCIA',
            close: 'FECHAR_DENUNCIA',
            sent: 'DENUNCIA_ENVIADA',
            errors: {
                duplicate: 'DUPLICADA',
                own: 'PROPRIA',
                generic: 'ERRO_GENERICO',
            },
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

function i18n() {
    const instance = createI18nInstance();

    applyTranslations(instance, {
        locale: 'pt-BR',
        translations: TRADUCOES,
    });

    return instance;
}

function comment(overrides: Partial<ColorComment> = {}): ColorComment {
    return {
        id: 'cmt-1',
        body: 'Primeiro comentario',
        pending: true,
        deleted: false,
        created_at: null,
        edited_at: null,
        author: { id: 42, name: 'Ana', username: 'ana', avatar: null },
        author_removed: false,
        can_edit: false,
        can_delete: false,
        can_report: true,
        replies: [],
        replies_truncated: false,
        ...overrides,
    };
}

function paginator(data: ColorComment[]): ColorCommentPaginator {
    return {
        data,
        current_page: 1,
        last_page: 1,
        per_page: 20,
        total: data.length,
        from: data.length === 0 ? null : 1,
        to: data.length === 0 ? null : data.length,
    };
}

function mountThread(
    options: {
        data?: ColorComment[];
        canComment?: boolean;
    } = {},
) {
    return mount(CommentThread, {
        props: {
            paletteId: 'pal-1',
            comments: paginator(options.data ?? [comment()]),
            canComment: options.canComment ?? false,
            maxLength: 255,
        },
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

async function click(selector: string): Promise<void> {
    query<HTMLButtonElement>(selector).click();
    await nextTick();
}

async function type(selector: string, value: string): Promise<void> {
    const input = query<HTMLTextAreaElement>(selector);

    input.value = value;
    input.dispatchEvent(new Event('input'));
    await nextTick();
}

/** Formulario dentro de um item (resposta/edicao), fora da caixa da raiz. */
async function typeIn(root: Element, value: string): Promise<void> {
    const input = root.querySelector<HTMLTextAreaElement>(
        '[data-test="comment-form-input"]',
    );

    if (input === null) {
        throw new Error('formulario ausente no item');
    }

    input.value = value;
    input.dispatchEvent(new Event('input'));
    await nextTick();
}

async function choose(selector: string, value: string): Promise<void> {
    const select = query<HTMLSelectElement>(selector);

    select.value = value;
    select.dispatchEvent(new Event('change'));
    await nextTick();
}

async function submitIn(root: Element): Promise<void> {
    const button = root.querySelector<HTMLButtonElement>(
        '[data-test="comment-form-submit"]',
    );

    if (button === null) {
        throw new Error('botao de envio ausente no item');
    }

    button.click();
    await nextTick();
}

describe('Thread de comentarios', () => {
    beforeEach(() => {
        routerMock.get.mockReset();
        fetchMock.mockReset();
        document.body.innerHTML = '';
    });

    it('lista raizes e respostas com o selo de em analise', () => {
        mountThread({
            data: [
                comment({
                    replies: [
                        comment({
                            id: 'cmt-2',
                            body: 'Uma resposta',
                            pending: false,
                        }),
                    ],
                }),
            ],
        });

        const items = document.querySelectorAll('[data-test="comment-item"]');

        expect(document.querySelectorAll('[data-depth="0"]')).toHaveLength(1);
        expect(document.querySelectorAll('[data-depth="1"]')).toHaveLength(1);
        expect(items).toHaveLength(2);
        expect(document.body.textContent).toContain('Primeiro comentario');
        expect(document.body.textContent).toContain('EM_ANALISE');
        expect(
            document.querySelectorAll('[data-test="comment-pending-badge"]'),
        ).toHaveLength(1);
    });

    it('visitante le a thread sem formulario', () => {
        mountThread({ canComment: false });

        expect(exists('[data-test="comment-form"]')).toBe(false);
        expect(exists('[data-test="comment-reply-toggle"]')).toBe(false);
    });

    it('conta logada comenta e o novo item entra no topo', async () => {
        fetchMock.mockResolvedValue({
            ok: true,
            json: async () => ({
                comment: comment({ id: 'cmt-new', body: 'Novo' }),
            }),
        });

        mountThread({ data: [], canComment: true });

        expect(exists('[data-test="comment-thread-empty"]')).toBe(true);

        await type('[data-test="comment-form-input"]', '  Novo  ');
        await click('[data-test="comment-form-submit"]');
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(fetchMock.mock.calls[0]?.[0]).toBe(
            '/api/tools/colors/palettes/pal-1/comments',
        );
        expect(JSON.parse(fetchMock.mock.calls[0]?.[1].body as string)).toEqual(
            {
                body: 'Novo',
            },
        );
        expect(exists('[data-test="comment-thread-empty"]')).toBe(false);
        expect(document.querySelectorAll('[data-depth="0"]')).toHaveLength(1);
        expect(document.body.textContent).toContain('Novo');
    });

    it('responde uma raiz e a resposta nao ganha botao de responder', async () => {
        fetchMock.mockResolvedValue({
            ok: true,
            json: async () => ({
                comment: comment({
                    id: 'cmt-reply',
                    body: 'Resposta',
                    can_edit: true,
                }),
            }),
        });

        mountThread({ canComment: true });

        await click('[data-test="comment-reply-toggle"]');

        const root = query('[data-depth="0"]');

        await typeIn(root, 'Resposta');
        await submitIn(root);
        await flushPromises();

        expect(JSON.parse(fetchMock.mock.calls[0]?.[1].body as string)).toEqual(
            {
                body: 'Resposta',
                parent_id: 'cmt-1',
            },
        );
        expect(document.querySelectorAll('[data-depth="1"]')).toHaveLength(1);
        expect(
            document.querySelectorAll('[data-test="comment-reply-toggle"]'),
        ).toHaveLength(1);
    });

    it('edita o proprio comentario mantendo o selo e as respostas', async () => {
        fetchMock.mockResolvedValue({
            ok: true,
            json: async () => ({
                comment: comment({
                    body: 'Texto editado',
                    edited_at: '2026-11-15T10:00:00+00:00',
                }),
            }),
        });

        mountThread({
            canComment: true,
            data: [
                comment({
                    can_edit: true,
                    can_delete: true,
                    replies: [
                        comment({ id: 'cmt-2', body: 'Resposta antiga' }),
                    ],
                }),
            ],
        });

        await click('[data-test="comment-edit-toggle"]');

        const root = query('[data-depth="0"]');

        await typeIn(root, 'Texto editado');
        await submitIn(root);
        await flushPromises();

        expect(fetchMock.mock.calls[0]?.[1].method).toBe('PATCH');
        expect(fetchMock.mock.calls[0]?.[0]).toBe(
            '/api/tools/colors/comments/cmt-1',
        );
        expect(document.body.textContent).toContain('Texto editado');
        expect(document.body.textContent).toContain('EDITADO');
        expect(document.body.textContent).toContain('Resposta antiga');
        expect(exists('[data-test="comment-pending-badge"]')).toBe(true);
    });

    it('excluir pai com respostas vira tombstone e mantem a conversa', async () => {
        fetchMock.mockResolvedValue({
            ok: true,
            json: async () => ({
                deleted: true,
                tombstone: true,
                comment: comment({ deleted: true, body: '', can_edit: false }),
            }),
        });

        mountThread({
            canComment: true,
            data: [
                comment({
                    can_edit: true,
                    can_delete: true,
                    replies: [comment({ id: 'cmt-2', body: 'Resposta viva' })],
                }),
            ],
        });

        await click('[data-test="comment-delete-toggle"]');
        await click('[data-test="comment-delete-confirm-yes"]');
        await flushPromises();

        expect(fetchMock.mock.calls[0]?.[1].method).toBe('DELETE');
        expect(exists('[data-test="comment-tombstone"]')).toBe(true);
        expect(document.body.textContent).toContain('REMOVIDO');
        expect(document.body.textContent).toContain('Resposta viva');
    });

    it('excluir comentario sem respostas some da lista', async () => {
        fetchMock.mockResolvedValue({
            ok: true,
            json: async () => ({
                deleted: true,
                tombstone: false,
                comment: null,
            }),
        });

        mountThread({
            canComment: true,
            data: [comment({ can_edit: true, can_delete: true })],
        });

        await click('[data-test="comment-delete-toggle"]');
        await click('[data-test="comment-delete-confirm-yes"]');
        await flushPromises();

        expect(
            document.querySelectorAll('[data-test="comment-item"]'),
        ).toHaveLength(0);
        expect(exists('[data-test="comment-thread-empty"]')).toBe(true);
    });

    it('mostra o erro traduzido quando a API recusa', async () => {
        fetchMock.mockResolvedValue({
            ok: false,
            status: 422,
            json: async () => ({}),
        });

        mountThread({ data: [], canComment: true });

        await type('[data-test="comment-form-input"]', 'texto');
        await click('[data-test="comment-form-submit"]');
        await flushPromises();

        expect(query('[data-test="comment-form-error"]').textContent).toContain(
            'INVALIDO',
        );
    });

    it('nao envia comentario vazio', async () => {
        mountThread({ data: [], canComment: true });

        await click('[data-test="comment-form-submit"]');
        await nextTick();

        expect(fetchMock).not.toHaveBeenCalled();
        expect(query('[data-test="comment-form-error"]').textContent).toContain(
            'VAZIO',
        );
    });

    it('pagina com a query certa preservando a posicao', async () => {
        mountThread({ data: [comment()] });

        // Sem mais de uma pagina nao ha controles.
        expect(exists('[data-test="comment-pagination"]')).toBe(false);
    });
});

describe('Denuncias de comentario', () => {
    beforeEach(() => {
        fetchMock.mockReset();
        document.body.innerHTML = '';
    });

    it('denuncia o comentario escolhendo o motivo', async () => {
        fetchMock.mockResolvedValue({
            ok: true,
            json: async () => ({ report: { id: 'rep-1' } }),
        });

        mountThread();

        await click('[data-test="comment-report-toggle"]');

        expect(exists('[data-test="comment-report-dialog"]')).toBe(true);

        await choose('[data-test="comment-report-reason"]', 'harassment');
        await click('[data-test="comment-report-submit"]');
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(fetchMock.mock.calls[0]?.[0]).toBe(
            '/api/tools/colors/comments/cmt-1/report',
        );
        expect(fetchMock.mock.calls[0]?.[1].method).toBe('POST');
        expect(JSON.parse(fetchMock.mock.calls[0]?.[1].body as string)).toEqual(
            {
                reason: 'harassment',
            },
        );
        expect(
            query('[data-test="comment-report-sent"]').textContent,
        ).toContain('DENUNCIA_ENVIADA');
        expect(exists('[data-test="comment-report-toggle"]')).toBe(false);
    });

    it('nao oferece denuncia quando o servidor nao permite', () => {
        mountThread({ data: [comment({ can_report: false })] });

        expect(exists('[data-test="comment-report-toggle"]')).toBe(false);
    });

    it('mostra a mensagem que o servidor devolveu', async () => {
        fetchMock.mockResolvedValue({
            ok: false,
            status: 422,
            json: async () => ({
                error: {
                    details: {
                        errors: { reason: ['DUPLICADA'] },
                    },
                },
            }),
        });

        mountThread();

        await click('[data-test="comment-report-toggle"]');
        await click('[data-test="comment-report-submit"]');
        await flushPromises();

        expect(
            query('[data-test="comment-report-error"]').textContent,
        ).toContain('DUPLICADA');
        expect(exists('[data-test="comment-report-sent"]')).toBe(false);
    });

    it('cai no texto generico quando a recusa nao traz motivo', async () => {
        fetchMock.mockRejectedValue(new TypeError('Failed to fetch'));

        mountThread();

        await click('[data-test="comment-report-toggle"]');
        await click('[data-test="comment-report-submit"]');
        await flushPromises();

        expect(
            query('[data-test="comment-report-error"]').textContent,
        ).toContain('ERRO_GENERICO');
    });
});
