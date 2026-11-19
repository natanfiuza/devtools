import { DOMWrapper, flushPromises, mount } from '@vue/test-utils';
import { defineComponent, h, nextTick } from 'vue';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import LayoutWorkspace from '@/tools/layout-harmonizer/LayoutWorkspace.vue';
import { useLayoutSystem } from '@/tools/layout-harmonizer/composables/useLayoutSystem';
import { exportMessages } from './support/langMessages';

const { writeClipboard } = vi.hoisted(() => ({ writeClipboard: vi.fn() }));

vi.mock('@/lib/clipboard', () => ({ writeClipboard }));

// O motor gera 66 tokens por montagem; sob a suite inteira em paralelo o
// seletor de 5 s padrao aperta. O limite maior so cobre a montagem pesada.
vi.setConfig({ testTimeout: 20_000 });

/**
 * Workspace da F2: estado vazio, os tres modos de visualizacao, o ultimo
 * quadro valido e o tema do site intocado. Cada quadro tem 33 tokens; o modo
 * Comparar mostra os dois (66 celulas).
 *
 * Desde a F4 o workspace recebe o quadro pronto pelo `controller`; o harness
 * monta o controller live (o mesmo caminho do painel) e nao o proprio painel,
 * para o teste continuar medindo so o corpo da ferramenta.
 */

function i18n() {
    const instance = createI18nInstance();

    applyTranslations(instance, {
        locale: 'pt_BR',
        translations: exportMessages().pt_BR,
    });

    return instance;
}

const Harness = defineComponent({
    name: 'WorkspaceHarness',
    setup() {
        const controller = useLayoutSystem();

        return () => h(LayoutWorkspace, { controller });
    },
});

function mountWorkspace() {
    return mount(Harness, { global: { plugins: [i18n()] } });
}

async function typeSeed(
    wrapper: ReturnType<typeof mountWorkspace>,
    value: string,
) {
    await wrapper.get('[data-test="color-input"]').setValue(value);
    await nextTick();
}

afterEach(() => {
    vi.mocked(writeClipboard).mockReset();
    document.documentElement.className = '';
});

describe('estado vazio', () => {
    it('abre sem quadro e com o picker sem valor', () => {
        const wrapper = mountWorkspace();

        expect(wrapper.find('[data-test="layout-empty"]').exists()).toBe(true);
        expect(wrapper.find('[data-test="theme-board"]').exists()).toBe(false);
        expect(wrapper.find('[data-test="picker-empty"]').exists()).toBe(true);
        expect(wrapper.get('[data-test="layout-notice"]').text()).toBe('');
    });

    it('deixa de mostrar o vazio quando ha seed valida', async () => {
        const wrapper = mountWorkspace();

        await typeSeed(wrapper, '#3b82f6');

        expect(wrapper.find('[data-test="layout-empty"]').exists()).toBe(false);
        expect(wrapper.find('[data-test="picker-empty"]').exists()).toBe(false);
    });
});

describe('modos de visualizacao', () => {
    it('abre em Comparar, com os dois quadros e 66 celulas', async () => {
        const wrapper = mountWorkspace();

        await typeSeed(wrapper, '#3b82f6');

        expect(wrapper.findAll('[data-test="theme-board"]')).toHaveLength(2);
        expect(wrapper.findAll('[data-test="token-cell"]')).toHaveLength(66);
        expect(
            wrapper
                .get('[data-test="view-mode-compare"]')
                .attributes('aria-pressed'),
        ).toBe('true');
    });

    it('mostra um quadro por vez nos modos Claro e Escuro', async () => {
        const wrapper = mountWorkspace();

        await typeSeed(wrapper, '#3b82f6');

        await wrapper.get('[data-test="view-mode-light"]').trigger('click');
        await nextTick();

        expect(wrapper.findAll('[data-test="theme-board"]')).toHaveLength(1);
        expect(wrapper.findAll('[data-test="token-cell"]')).toHaveLength(33);
        expect(wrapper.find('[data-test="theme-comparison"]').exists()).toBe(
            false,
        );
        expect(
            wrapper
                .get('[data-test="view-mode-light"]')
                .attributes('aria-pressed'),
        ).toBe('true');

        await wrapper.get('[data-test="view-mode-dark"]').trigger('click');
        await nextTick();

        expect(wrapper.get('[data-test="theme-board"]').text()).toContain(
            'Tema escuro',
        );
    });

    it('nao altera o tema do site ao alternar o quadro', async () => {
        document.documentElement.className = 'dark';

        const wrapper = mountWorkspace();

        await typeSeed(wrapper, '#3b82f6');
        await wrapper.get('[data-test="view-mode-light"]').trigger('click');
        await nextTick();
        await wrapper.get('[data-test="view-mode-dark"]').trigger('click');
        await nextTick();

        expect(document.documentElement.className).toBe('dark');
    });
});

describe('ultimo quadro valido', () => {
    it('mantem as 66 celulas e avisa quando o texto fica invalido', async () => {
        const wrapper = mountWorkspace();

        await typeSeed(wrapper, '#3b82f6');

        const first = wrapper.get('[data-test="copy-hex"]').element;
        const hex = wrapper.get('[data-test="token-hex"]').text();

        await typeSeed(wrapper, 'nao-e-uma-cor');

        expect(wrapper.findAll('[data-test="token-cell"]')).toHaveLength(66);
        expect(wrapper.get('[data-test="token-hex"]').text()).toBe(hex);
        expect(wrapper.get('[data-test="layout-notice"]').text()).toContain(
            'não é válida',
        );
        expect(wrapper.get('[data-test="layout-notice"]').text()).toContain(
            'última entrada válida',
        );
        // O botao nao e remontado: o foco de quem copia continua onde estava.
        expect(wrapper.get('[data-test="copy-hex"]').element).toBe(first);
    });
});

describe('alpha e fundo explicito', () => {
    it('so mostra o campo de fundo quando a seed tem transparencia', async () => {
        const wrapper = mountWorkspace();

        expect(
            wrapper.find('[data-test="seed-background-field"]').exists(),
        ).toBe(false);

        await typeSeed(wrapper, 'rgba(59, 130, 246, 0.6)');

        expect(
            wrapper.find('[data-test="seed-background-field"]').exists(),
        ).toBe(true);
        expect(wrapper.find('[data-test="layout-empty"]').exists()).toBe(true);
        expect(wrapper.get('[data-test="layout-notice"]').text()).toContain(
            'transparência',
        );
    });

    it('gera o quadro depois de um fundo valido e resume a composicao', async () => {
        const wrapper = mountWorkspace();

        await typeSeed(wrapper, 'rgba(59, 130, 246, 0.6)');

        await wrapper
            .get('[data-test="seed-background-input"]')
            .setValue('white');
        await nextTick();

        expect(wrapper.findAll('[data-test="token-cell"]')).toHaveLength(66);
        expect(wrapper.get('[data-test="layout-notice"]').text()).toBe('');
        expect(wrapper.get('[data-test="seed-summary"]').text()).toContain(
            '#ffffff',
        );
    });
});

describe('edicao no quadro (F3)', () => {
    it('mostra secundaria, restaurar e sugestoes junto ao quadro', async () => {
        const wrapper = mountWorkspace();

        await typeSeed(wrapper, '#3b82f6');

        expect(wrapper.find('[data-test="secondary-field"]').exists()).toBe(
            true,
        );
        expect(wrapper.find('[data-test="suggestions-panel"]').exists()).toBe(
            true,
        );
        expect(wrapper.find('[data-test="restore-controls"]').exists()).toBe(
            true,
        );
        expect(
            wrapper.get('[data-test="restore-all"]').attributes('disabled'),
        ).toBe('');
    });

    it('bloqueia pelo cabecalho e restaura tudo apos confirmar', async () => {
        const wrapper = mountWorkspace();

        await typeSeed(wrapper, '#3b82f6');

        const lock = wrapper.get('[data-test="token-lock"]');

        expect(lock.attributes('aria-pressed')).toBe('false');

        await lock.trigger('click');
        await nextTick();

        expect(
            wrapper.get('[data-test="token-lock"]').attributes('aria-pressed'),
        ).toBe('true');
        expect(
            wrapper.get('[data-test="restore-all"]').attributes('disabled'),
        ).toBe(undefined);

        await wrapper.get('[data-test="restore-all"]').trigger('click');
        await nextTick();

        const confirm = document.body.querySelector(
            '[data-test="restore-confirm"]',
        );

        expect(confirm).not.toBeNull();

        new DOMWrapper(confirm!).trigger('click');
        await nextTick();
        await nextTick();

        expect(
            wrapper.get('[data-test="restore-all"]').attributes('disabled'),
        ).toBe('');
        expect(
            wrapper.get('[data-test="token-lock"]').attributes('aria-pressed'),
        ).toBe('false');
    });
});

describe('secundaria manual (F3)', () => {
    it('aplica a cor e volta ao automatico pelo botao', async () => {
        const wrapper = mountWorkspace();

        await typeSeed(wrapper, '#3b82f6');

        expect(wrapper.get('[data-test="secondary-source"]').text()).toContain(
            'automática',
        );

        await wrapper.get('[data-test="secondary-input"]').setValue('#00ff00');
        await nextTick();

        expect(wrapper.get('[data-test="secondary-source"]').text()).toContain(
            'manual',
        );
        expect(
            wrapper.get('[data-test="secondary-reset"]').attributes('disabled'),
        ).toBe(undefined);

        await wrapper.get('[data-test="secondary-reset"]').trigger('click');
        await nextTick();

        expect(wrapper.get('[data-test="secondary-source"]').text()).toContain(
            'automática',
        );
    });
});

describe('sugestoes (F3)', () => {
    it('aceita a proposta em um tema sem tocar o outro', async () => {
        const wrapper = mountWorkspace();

        await typeSeed(wrapper, '#777777');

        const row = wrapper
            .findAll('[data-test="suggestion-row"]')
            .find(
                (item) =>
                    item.attributes('data-theme') === 'dark' &&
                    item.attributes('data-token') === 'on-primary',
            );

        expect(row).toBeDefined();

        await row!.get('[data-test="suggestion-accept"]').trigger('click');
        await nextTick();

        expect(
            wrapper
                .findAll('[data-test="suggestion-row"]')
                .some(
                    (item) =>
                        item.attributes('data-theme') === 'dark' &&
                        item.attributes('data-token') === 'on-primary',
                ),
        ).toBe(false);
        expect(
            wrapper
                .findAll('[data-test="suggestion-row"]')
                .some(
                    (item) =>
                        item.attributes('data-theme') === 'light' &&
                        item.attributes('data-token') === 'on-primary',
                ),
        ).toBe(true);
    });

    it('troca para AAA sem alterar os HEX do quadro', async () => {
        const wrapper = mountWorkspace();

        await typeSeed(wrapper, '#3b82f6');

        const before = wrapper.get('[data-test="token-hex"]').text();

        await wrapper
            .get('[data-test="suggestion-target-aaa"]')
            .trigger('click');
        await nextTick();

        expect(wrapper.get('[data-test="token-hex"]').text()).toBe(before);
        expect(
            wrapper
                .get('[data-test="suggestion-target-aaa"]')
                .attributes('aria-pressed'),
        ).toBe('true');
    });
});

describe('copiar', () => {
    it('anuncia a copia na linha de status persistente', async () => {
        vi.mocked(writeClipboard).mockResolvedValue(undefined);

        const wrapper = mountWorkspace();

        await typeSeed(wrapper, '#3b82f6');

        await wrapper.get('[data-test="copy-hex"]').trigger('click');
        await flushPromises();

        expect(writeClipboard).toHaveBeenCalledTimes(1);
        expect(wrapper.get('[data-test="layout-feedback"]').text()).toContain(
            'Copiado',
        );
        expect(wrapper.get('[data-test="layout-feedback"]').text()).toContain(
            'HEX',
        );
    });
});
