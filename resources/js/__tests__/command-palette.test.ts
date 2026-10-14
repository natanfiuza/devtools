import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { nextTick } from 'vue';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import CommandPalette from '@/components/CommandPalette.vue';
import { useCommandPalette } from '@/composables/useCommandPalette';
import { allTools } from '@/tools/registry';
import type { ToolManifest } from '@/tools/types';

/**
 * Palette de busca (Cmd+K).
 *
 * O conteudo do Dialog nasce num Teleport para o body (o stub de teleport do
 * Vue Test Utils nao renderiza o conteudo), entao as consultas sao ao DOM.
 *
 * O registro e trocado por uma fixture de duas tools - uma disponivel e uma
 * "em breve" - porque na F1 nenhuma tool de verdade funciona ainda e o
 * caminho de navegacao precisa ser exercitado. As traducoes sao marcadores,
 * para denunciar texto fixo no componente.
 */

const { routerVisit, fixtureTools } = vi.hoisted(() => ({
    routerVisit: vi.fn(),
    fixtureTools: [
        {
            slug: 'alpha',
            status: 'available',
            category: 'json',
            icon: 'braces',
            order: 10,
            keywords: ['tree'],
        },
        {
            slug: 'beta',
            status: 'soon',
            category: 'sql',
            icon: 'database',
            order: 20,
            keywords: ['checksum'],
        },
    ] satisfies ToolManifest[],
}));

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({ props: { localeSlug: 'pt-br' } }),
    router: { visit: routerVisit },
}));

vi.mock('@/tools/registry', async () => {
    const actual =
        await vi.importActual<typeof import('@/tools/registry')>(
            '@/tools/registry',
        );

    return { ...actual, allTools: fixtureTools };
});

const toolsTraducoes: Record<string, unknown> = {
    status: { soon: 'SELO_EM_BREVE' },
    categories: {
        json: 'CAT_JSON',
        sql: 'CAT_SQL',
        encode: 'CAT_ENCODE',
        security: 'CAT_SEG',
        time: 'CAT_TEMPO',
        text: 'CAT_TEXTO',
    },
};

for (const tool of fixtureTools) {
    toolsTraducoes[tool.slug] = {
        name: `NOME_${tool.slug}`,
        description: `DESC_${tool.slug}`,
        keywords: `PALAVRA_${tool.slug}`,
    };
}

const traducoes = {
    app: {
        search: {
            trigger: 'BUSCAR_FERRAMENTAS',
            placeholder: 'DICA_BUSCA',
            title: 'TITULO_BUSCA',
            description: 'DESCRICAO_BUSCA',
            empty: 'VAZIO:{query}',
            count: 'TOTAL:{count}',
            hint_navigate: 'NAVEGAR',
            hint_select: 'ABRIR',
            hint_close: 'FECHAR',
        },
    },
    tools: toolsTraducoes,
};

let wrapper: VueWrapper | null = null;

function montar(): void {
    wrapper?.unmount();

    const i18n = createI18nInstance();
    applyTranslations(i18n, { locale: 'pt_BR', translations: traducoes });

    wrapper = mount(CommandPalette, { global: { plugins: [i18n] } });
}

function achar(seletor: string): HTMLElement | null {
    return document.querySelector<HTMLElement>(seletor);
}

function campo(): HTMLInputElement {
    const input = achar('input[role="combobox"]');

    if (input === null) {
        throw new Error('o campo de busca nao esta na tela');
    }

    return input as HTMLInputElement;
}

function opcoes(): HTMLElement[] {
    return Array.from(document.querySelectorAll<HTMLElement>('[role="option"]'));
}

/**
 * Tecla no documento, como o navegador faz (o alvo e o elemento focado).
 *
 * `flushPromises` porque o Presence do reka-ui decide se desmonta o conteudo
 * so depois de um tick (ele espera a animacao de saida).
 */
async function teclar(
    target: EventTarget,
    key: string,
    options: KeyboardEventInit = {},
): Promise<void> {
    target.dispatchEvent(
        new KeyboardEvent('keydown', { key, bubbles: true, ...options }),
    );

    await nextTick();
    await flushPromises();
}

async function digitar(valor: string): Promise<void> {
    const input = campo();
    input.value = valor;
    input.dispatchEvent(new Event('input', { bubbles: true }));

    await nextTick();
    await flushPromises();
}

function ativo(): string | null {
    return campo().getAttribute('aria-activedescendant');
}

beforeEach(() => {
    useCommandPalette().close();
});

afterEach(() => {
    wrapper?.unmount();
    wrapper = null;
    document.body.innerHTML = '';
});

describe('atalho', () => {
    it('abre e fecha no Cmd/Ctrl+K', async () => {
        montar();

        expect(achar('[role="listbox"]')).toBeNull();

        await teclar(window, 'k', { ctrlKey: true });
        expect(achar('[role="listbox"]')).not.toBeNull();

        await teclar(window, 'k', { metaKey: true });
        expect(achar('[role="listbox"]')).toBeNull();
    });

    it('abre no "/" fora de campo de texto e ignora dentro dele', async () => {
        montar();

        const outro = document.createElement('input');
        document.body.append(outro);

        await teclar(outro, '/');
        expect(achar('[role="listbox"]')).toBeNull();

        await teclar(document.body, '/');
        expect(achar('[role="listbox"]')).not.toBeNull();
    });
});

describe('resultados', () => {
    it('abre com todas as tools registradas e conta os resultados', async () => {
        montar();
        await teclar(window, 'k', { ctrlKey: true });

        expect(opcoes()).toHaveLength(2);
        expect(achar('[data-test="command-palette-count"]')?.textContent).toBe(
            'TOTAL:2',
        );
    });

    it('filtra pelo termo e avisa quando nada casa', async () => {
        montar();
        await teclar(window, 'k', { ctrlKey: true });

        await digitar('checksum');
        expect(opcoes()).toHaveLength(1);
        expect(opcoes()[0]?.textContent).toContain('NOME_beta');

        await digitar('kubernetes');
        expect(opcoes()).toHaveLength(0);
        expect(
            achar('[data-test="command-palette-empty"]')?.textContent,
        ).toBe('VAZIO:kubernetes');
    });

    it('escreve nome, categoria, selo e dicas pelo i18n', async () => {
        montar();
        await teclar(window, 'k', { ctrlKey: true });

        expect(opcoes()[0]?.textContent).toContain('NOME_alpha');
        expect(opcoes()[0]?.textContent).toContain('CAT_JSON');

        // Selo so na tool que ainda nao funciona.
        const selos = document.querySelectorAll(
            '[data-test="command-palette-badge"]',
        );
        expect(selos).toHaveLength(1);
        expect(selos[0]?.textContent).toBe('SELO_EM_BREVE');

        const rodape = achar('[data-test="command-palette-footer"]');
        expect(rodape?.textContent).toContain('NAVEGAR');
        expect(rodape?.textContent).toContain('ABRIR');
        expect(rodape?.textContent).toContain('FECHAR');
    });
});

describe('teclado e navegacao', () => {
    it('ativa o primeiro resultado e anda com as setas, dando a volta', async () => {
        montar();
        await teclar(window, 'k', { ctrlKey: true });

        expect(ativo()).toBe('command-palette-option-alpha');

        await teclar(campo(), 'ArrowDown');
        expect(ativo()).toBe('command-palette-option-beta');

        // Do primeiro para tras, cai no ultimo.
        await teclar(campo(), 'ArrowUp');
        expect(ativo()).toBe('command-palette-option-alpha');

        await teclar(campo(), 'ArrowUp');
        expect(ativo()).toBe('command-palette-option-beta');
    });

    it('Enter na tool disponivel navega e fecha', async () => {
        montar();
        await teclar(window, 'k', { ctrlKey: true });

        await teclar(campo(), 'Enter');

        expect(routerVisit).toHaveBeenCalledWith('/pt-br/tools/alpha');
        expect(achar('[role="listbox"]')).toBeNull();
    });

    it('Enter na tool "em breve" nao navega nem fecha', async () => {
        montar();
        await teclar(window, 'k', { ctrlKey: true });

        await teclar(campo(), 'ArrowDown');
        await teclar(campo(), 'Enter');

        expect(routerVisit).not.toHaveBeenCalled();
        expect(achar('[role="listbox"]')).not.toBeNull();
    });

    it('clicar na tool disponivel navega', async () => {
        montar();
        await teclar(window, 'k', { ctrlKey: true });

        achar('[data-test="command-palette-option-alpha"]')?.dispatchEvent(
            new MouseEvent('click', { bubbles: true }),
        );
        await nextTick();
        await flushPromises();

        expect(routerVisit).toHaveBeenCalledWith('/pt-br/tools/alpha');
        expect(achar('[role="listbox"]')).toBeNull();
    });

    it('fecha no Esc', async () => {
        montar();
        await teclar(window, 'k', { ctrlKey: true });

        await teclar(document, 'Escape');

        expect(achar('[role="listbox"]')).toBeNull();
    });

    it('reabre com o campo limpo', async () => {
        montar();

        await teclar(window, 'k', { ctrlKey: true });
        await digitar('checksum');
        expect(opcoes()).toHaveLength(1);

        await teclar(window, 'k', { ctrlKey: true });
        await teclar(window, 'k', { ctrlKey: true });

        expect(campo().value).toBe('');
        expect(opcoes()).toHaveLength(allTools.length);
    });
});
