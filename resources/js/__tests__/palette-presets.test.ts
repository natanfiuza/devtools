import { flushPromises, mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import PresetDialog from '@/components/colors/PresetDialog.vue';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';

/**
 * Diálogo de presets do gerador (F5, ADR-093).
 *
 * A lista, a cota e o teto do nome vêm do servidor (`GET /presets`); o
 * cliente só evita o previsível (nome vazio, duplicado, teto atingido) e
 * devolve a base escolhida ao gerador. Criar, renomear e excluir são as
 * únicas escritas.
 */

function i18n() {
    const instance = createI18nInstance();

    applyTranslations(instance, {
        locale: 'pt-BR',
        translations: {
            colors: {
                presets: {
                    open: 'Presets',
                    title: 'Presets do gerador',
                    description: 'Um preset guarda o nome e a cor base.',
                    name_label: 'Nome do preset',
                    name_placeholder: 'Azul da marca',
                    current_base: 'Cor base: {hex}',
                    create: 'Salvar preset',
                    saving: 'Salvando…',
                    loading: 'Carregando presets…',
                    load_error: 'Não foi possível carregar os presets.',
                    retry: 'Tentar de novo',
                    empty: 'Nenhum preset ainda.',
                    apply: 'Aplicar',
                    applied: 'Preset aplicado.',
                    rename: 'Renomear',
                    rename_save: 'Salvar',
                    renaming: 'Salvando…',
                    cancel: 'Cancelar',
                    delete: 'Excluir',
                    deleting: 'Excluindo…',
                    confirm_delete: 'Excluir “{name}”?',
                    confirm_delete_hint: 'Sai da sua conta.',
                    updated_at: 'Atualizado em {date}',
                    limit_reached: 'Limite de presets ({max}).',
                    duplicate: 'Já tem um preset com este nome.',
                    empty_base: 'Escolha uma cor base.',
                    error: 'Não foi possível salvar o preset.',
                    close: 'Fechar',
                },
            },
        },
    });

    return instance;
}

type PresetBody = {
    data: Array<{
        id: string;
        name: string;
        base: string;
        created_at: string | null;
        updated_at: string | null;
    }>;
    limits: { limit: number | null; used: number };
    form: { name_max_length: number };
};

const PRESET = {
    id: '01JZZZZZZZZZZZZZZZZZZZZZZZ',
    name: 'Verde',
    base: '#22aa66',
    created_at: '2026-11-13T00:00:00+00:00',
    updated_at: '2026-11-13T00:00:00+00:00',
};

function listBody(overrides: Partial<PresetBody> = {}): PresetBody {
    return {
        data: [PRESET],
        limits: { limit: null, used: 1 },
        form: { name_max_length: 60 },
        ...overrides,
    };
}

function jsonResponse(body: unknown, status = 200) {
    return {
        ok: status >= 200 && status < 300,
        status,
        json: async () => body,
    };
}

function mountDialog(
    props: Partial<{ base: string; hasBase: boolean }> = {},
): ReturnType<typeof mount> {
    return mount(PresetDialog, {
        props: { open: false, base: '#3366ff', hasBase: true, ...props },
        global: { plugins: [i18n()] },
    });
}

async function openDialog(
    wrapper: ReturnType<typeof mountDialog>,
): Promise<void> {
    await wrapper.setProps({ open: true });
    await flushPromises();
}

function rows(): NodeListOf<HTMLElement> {
    return document.querySelectorAll('[data-test="preset-row"]');
}

async function typeName(value: string): Promise<void> {
    const input = document.querySelector(
        '[data-test="preset-name"]',
    ) as HTMLInputElement | null;

    if (input === null) {
        throw new Error('campo de nome do preset ausente');
    }

    input.value = value;
    input.dispatchEvent(new Event('input'));
    await nextTick();
}

function submitCreate(): void {
    document
        .querySelector('[data-test="presets-dialog"] form')
        ?.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
}

beforeEach(() => {
    document.body.innerHTML = '';
});

afterEach(() => {
    vi.unstubAllGlobals();
    document.body.innerHTML = '';
});

describe('PresetDialog', () => {
    it('carrega a lista da conta ao abrir', async () => {
        const fetchMock = vi.fn(
            async (_url: string, _init?: RequestInit) =>
                jsonResponse(listBody()),
        );
        vi.stubGlobal('fetch', fetchMock);

        const wrapper = mountDialog();
        await openDialog(wrapper);

        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(fetchMock.mock.calls[0]?.[0]).toBe('/api/tools/colors/presets');
        expect(rows()).toHaveLength(1);
        expect(
            document
                .querySelector('[data-test="preset-name-label"]')
                ?.textContent?.trim(),
        ).toBe('Verde');
        expect(
            document.querySelector('[data-test="presets-current-base"]')
                ?.textContent,
        ).toContain('#3366ff');
    });

    it('cria um preset com a base atual do gerador', async () => {
        const created = { ...PRESET, id: '01JYYYYYYYYYYYYYYYYYYYYYYY', name: 'Marca' };
        const fetchMock = vi.fn(async (_url: string, init?: RequestInit) => {
            if (init?.method === 'POST') {
                return jsonResponse({ preset: created }, 201);
            }

            return jsonResponse(listBody());
        });
        vi.stubGlobal('fetch', fetchMock);

        const wrapper = mountDialog();
        await openDialog(wrapper);
        await typeName('Marca');
        submitCreate();
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledWith(
            '/api/tools/colors/presets',
            expect.objectContaining({
                method: 'POST',
                body: JSON.stringify({ name: 'Marca', base: '#3366ff' }),
            }),
        );
        expect(rows()).toHaveLength(2);
        expect(
            document
                .querySelectorAll('[data-test="preset-name-label"]')[0]
                ?.textContent?.trim(),
        ).toBe('Marca');
    });

    it('desabilita a criação com nome duplicado ou teto atingido', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(async (_url: string, _init?: RequestInit) =>
                jsonResponse(listBody({ limits: { limit: 1, used: 1 } })),
            ),
        );

        const wrapper = mountDialog();
        await openDialog(wrapper);

        const create = () =>
            document.querySelector(
                '[data-test="presets-create"]',
            ) as HTMLButtonElement;

        expect(create().disabled).toBe(true);
        expect(
            document.querySelector('[data-test="presets-limit"]'),
        ).not.toBeNull();

        vi.stubGlobal(
            'fetch',
            vi.fn(async (_url: string, _init?: RequestInit) =>
                jsonResponse(listBody()),
            ),
        );
        await wrapper.setProps({ open: false });
        await wrapper.setProps({ open: true });
        await flushPromises();

        await typeName('verde');
        await nextTick();

        expect(
            document.querySelector('[data-test="presets-duplicate"]'),
        ).not.toBeNull();
        expect(create().disabled).toBe(true);

        await typeName('Outra');
        await nextTick();

        expect(create().disabled).toBe(false);
    });

    it('aplicar emite a base do preset e fecha', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(async (_url: string, _init?: RequestInit) =>
                jsonResponse(listBody()),
            ),
        );

        const wrapper = mountDialog();
        await openDialog(wrapper);

        (
            document.querySelector('[data-test="preset-apply"]') as HTMLButtonElement
        ).click();
        await flushPromises();

        expect(wrapper.emitted('apply')).toEqual([['#22aa66']]);
        expect(wrapper.emitted('close')).toHaveLength(1);
    });

    it('exclui só depois da confirmação', async () => {
        const fetchMock = vi.fn(async (_url: string, init?: RequestInit) => {
            if (init?.method === 'DELETE') {
                return jsonResponse({ deleted: true });
            }

            return jsonResponse(listBody());
        });
        vi.stubGlobal('fetch', fetchMock);

        const wrapper = mountDialog();
        await openDialog(wrapper);

        (
            document.querySelector('[data-test="preset-delete"]') as HTMLButtonElement
        ).click();
        await nextTick();

        expect(
            document.querySelector('[data-test="preset-delete-confirm"]'),
        ).not.toBeNull();
        expect(fetchMock).toHaveBeenCalledTimes(1);

        (
            document.querySelector(
                '[data-test="preset-delete-confirm-button"]',
            ) as HTMLButtonElement
        ).click();
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledWith(
            `/api/tools/colors/presets/${PRESET.id}`,
            expect.objectContaining({ method: 'DELETE' }),
        );
        expect(rows()).toHaveLength(0);
    });

    it('renomeia e traz o preset para o topo', async () => {
        const second = {
            ...PRESET,
            id: '01JWWWWWWWWWWWWWWWWWWWWWWW',
            name: 'Azul',
        };
        const renamed = { ...second, name: 'Azul novo' };
        const fetchMock = vi.fn(async (_url: string, init?: RequestInit) => {
            if (init?.method === 'PATCH') {
                return jsonResponse({ preset: renamed });
            }

            return jsonResponse(listBody({ data: [PRESET, second], limits: { limit: null, used: 2 } }));
        });
        vi.stubGlobal('fetch', fetchMock);

        const wrapper = mountDialog();
        await openDialog(wrapper);

        (
            document.querySelectorAll('[data-test="preset-rename"]')[1] as HTMLButtonElement
        ).click();
        await nextTick();

        const input = document.querySelector(
            '[data-test="preset-rename-input"]',
        ) as HTMLInputElement;

        input.value = 'Azul novo';
        input.dispatchEvent(new Event('input'));
        await nextTick();

        (
            document.querySelector(
                '[data-test="preset-rename-save"]',
            ) as HTMLButtonElement
        ).click();
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledWith(
            `/api/tools/colors/presets/${second.id}`,
            expect.objectContaining({
                method: 'PATCH',
                body: JSON.stringify({ name: 'Azul novo' }),
            }),
        );
        expect(
            document
                .querySelectorAll('[data-test="preset-name-label"]')[0]
                ?.textContent?.trim(),
        ).toBe('Azul novo');
    });

    it('mostra o erro de leitura e permite tentar de novo', async () => {
        const fetchMock = vi
            .fn()
            .mockRejectedValueOnce(new Error('offline'))
            .mockResolvedValueOnce(jsonResponse(listBody()));
        vi.stubGlobal('fetch', fetchMock);

        const wrapper = mountDialog();
        await openDialog(wrapper);

        expect(
            document.querySelector('[data-test="presets-load-error"]'),
        ).not.toBeNull();

        const retry = Array.from(
            document.querySelectorAll(
                '[data-test="presets-load-error"] button',
            ),
        )[0] as HTMLButtonElement;

        retry.click();
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledTimes(2);
        expect(rows()).toHaveLength(1);
    });
});
