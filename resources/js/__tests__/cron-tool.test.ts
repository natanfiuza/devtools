import {
    flushPromises,
    mount,
    type DOMWrapper,
    type VueWrapper,
} from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import Tool from '@/tools/cron-explainer/Tool.vue';

/**
 * A tela do cron.
 *
 * O motor tem testes proprios (cron-parse/next/describe); aqui se cobra o que
 * a tela faz com ele: o campo unico, a explicacao campo a campo, a lista das
 * dez execucoes, os estados de erro e de horizonte esgotado, o exemplo e a
 * copia do relatorio.
 *
 * As traducoes sao atalhos que carregam os parametros (`AT:9`,
 * `DE:SEG:SEX`): se um parametro nao chegar, o teste mostra onde.
 */

const traducoes = {
    tools: {
        ui: {
            input: 'ENTRADA',
            output: 'SAIDA',
            resize: 'REDIMENSIONAR',
            actions: {
                example: 'EXEMPLO',
                clear: 'LIMPAR',
                copy: 'COPIAR',
                copied: 'COPIADO',
                copy_manual: 'COPIE_NA_MAO',
            },
            states: {
                empty: 'VAZIO',
            },
        },
        'cron-explainer': {
            panes: { expression: 'EXPRESSAO', runs: 'EXECUCOES' },
            expression: 'CAMPO_EXPRESSAO',
            hint: 'DICA',
            table: { field: 'CAMPO', value: 'VALOR', meaning: 'SENTIDO' },
            fields: {
                minute: 'Minuto',
                hour: 'Hora',
                day_of_month: 'DiaDoMes',
                month: 'Mes',
                day_of_week: 'DiaDaSemana',
            },
            names: {
                months: {
                    1: 'JAN',
                    2: 'FEV',
                    3: 'MAR',
                    4: 'ABR',
                    5: 'MAI',
                    6: 'JUN',
                    7: 'JUL',
                    8: 'AGO',
                    9: 'SET',
                    10: 'OUT',
                    11: 'NOV',
                    12: 'DEZ',
                },
                days: {
                    0: 'DOM',
                    1: 'SEG',
                    2: 'TER',
                    3: 'QUA',
                    4: 'QUI',
                    5: 'SEX',
                    6: 'SAB',
                },
            },
            meanings: {
                any: {
                    minute: 'ANY_MIN',
                    hour: 'ANY_HOUR',
                    day_of_month: 'ANY_DOM',
                    month: 'ANY_MONTH',
                    day_of_week: 'ANY_DOW',
                },
                step_star: {
                    minute: 'STAR_MIN:{step}',
                    hour: 'STAR_HOUR:{step}',
                    day_of_month: 'STAR_DOM:{step}',
                    month: 'STAR_MONTH:{step}',
                    day_of_week: 'STAR_DOW:{step}',
                },
                step_range: {
                    minute: 'STEP_MIN:{from}:{to}:{step}',
                    hour: 'STEP_HOUR:{from}:{to}:{step}',
                    day_of_month: 'STEP_DOM:{from}:{to}:{step}',
                    month: 'STEP_MONTH:{from}:{to}:{step}',
                    day_of_week: 'STEP_DOW:{from}:{to}:{step}',
                },
                range: 'DE:{from}:{to}',
                at: 'AT:{values}',
            },
            errors: {
                empty: 'ERRO:VAZIO',
                field_count: 'ERRO:CAMPOS:{token}',
                unknown_macro: 'ERRO:MACRO:{token}',
                macro_with_fields: 'ERRO:MACRO_CAMPOS:{token}',
                unknown_name: 'ERRO:NOME:{field}:{token}',
                invalid_value: 'ERRO:VALOR:{field}:{token}',
                invalid_range: 'ERRO:INTERVALO:{field}:{token}',
                invalid_step: 'ERRO:PASSO:{field}:{token}',
                invalid_syntax: 'ERRO:SINTAXE:{field}:{token}',
            },
            zone: 'FUSO:{zone}',
            exhausted: 'SEM_EXECUCAO',
            states: {
                empty: 'ESCREVA',
                invalid: 'INVALIDA',
            },
            status: {
                valid: 'OK:{count}:{zone}',
            },
            report: {
                zone: 'FUSO',
                runs: 'EXECUCOES',
                empty: 'SEM_EXECUCAO',
            },
        },
    },
};

function montar(): VueWrapper {
    const i18n = createI18nInstance();

    applyTranslations(i18n, { locale: 'pt_BR', translations: traducoes });

    return mount(Tool, {
        global: { plugins: [i18n] },
    });
}

function rotuloDoBotao(node: DOMWrapper<Element>): string {
    const copia = node.element.cloneNode(true) as Element;

    copia.querySelectorAll('kbd').forEach((tecla) => tecla.remove());

    return (copia.textContent ?? '').trim();
}

function botao(wrapper: VueWrapper, rotulo: string): DOMWrapper<Element> {
    const found = wrapper
        .findAll('button')
        .find((node) => rotuloDoBotao(node) === rotulo);

    if (found === undefined) {
        throw new Error(`o botao ${rotulo} nao esta na tela`);
    }

    return found;
}

function desabilitado(wrapper: VueWrapper, rotulo: string): boolean {
    return botao(wrapper, rotulo).attributes('disabled') !== undefined;
}

async function digitar(wrapper: VueWrapper, valor: string): Promise<void> {
    await wrapper.get('[data-test="cron-expression"]').setValue(valor);
    await flushPromises();
}

async function clicar(wrapper: VueWrapper, rotulo: string): Promise<void> {
    await botao(wrapper, rotulo).trigger('click');
    await flushPromises();
}

function campo(wrapper: VueWrapper, id: string) {
    return wrapper.get(`[data-test="cron-field"][data-field="${id}"]`);
}

function significado(wrapper: VueWrapper, id: string): string {
    return campo(wrapper, id).get('[data-test="cron-field-meaning"]').text();
}

function execucoes(wrapper: VueWrapper): string[] {
    return wrapper.findAll('[data-test="cron-run"]').map((node) => node.text());
}

describe('Cron Explainer', () => {
    let clipboard: { writeText: ReturnType<typeof vi.fn> };

    beforeEach(() => {
        clipboard = { writeText: vi.fn(() => Promise.resolve()) };

        Object.defineProperty(navigator, 'clipboard', {
            value: clipboard,
            configurable: true,
        });
    });

    it('abre vazio, com a dica e a copia desabilitada', () => {
        const wrapper = montar();

        expect(
            wrapper.get('[data-test="cron-expression"]').element,
        ).toHaveProperty('value', '');
        expect(wrapper.get('[data-test="cron-error"]').text()).toBe('DICA');
        expect(wrapper.get('[data-test="cron-state"]').text()).toBe('ESCREVA');
        expect(desabilitado(wrapper, 'COPIAR')).toBe(true);
        expect(wrapper.get('[data-test="cron-status"]').text()).toBe('VAZIO');
    });

    it('explica cada campo e lista dez execucoes', async () => {
        const wrapper = montar();

        await digitar(wrapper, '0 9 * * 1-5');

        expect(wrapper.findAll('[data-test="cron-field"]')).toHaveLength(5);
        expect(significado(wrapper, 'minute')).toBe('AT:0');
        expect(significado(wrapper, 'hour')).toBe('AT:9');
        expect(significado(wrapper, 'day_of_month')).toBe('ANY_DOM');
        expect(significado(wrapper, 'month')).toBe('ANY_MONTH');
        expect(significado(wrapper, 'day_of_week')).toBe('DE:SEG:SEX');
        expect(execucoes(wrapper)).toHaveLength(10);
        expect(wrapper.get('[data-test="cron-status"]').text()).toMatch(
            /^OK:10:/,
        );
        expect(wrapper.get('[data-test="cron-zone"]').text()).toMatch(/^FUSO:/);
    });

    it('traduz passos e nomes de mes na explicacao', async () => {
        const wrapper = montar();

        await digitar(wrapper, '*/15 * * JAN,MAR *');

        expect(significado(wrapper, 'minute')).toBe('STAR_MIN:15');
        expect(significado(wrapper, 'month')).toBe('AT:JAN, MAR');
    });

    it('mostra o erro no campo e invalida a saida', async () => {
        const wrapper = montar();

        await digitar(wrapper, '60 * * * *');

        expect(wrapper.get('[data-test="cron-error"]').text()).toBe(
            'ERRO:VALOR:Minuto:60',
        );
        expect(wrapper.get('[data-test="cron-state"]').text()).toBe('INVALIDA');
        expect(wrapper.findAll('[data-test="cron-field"]')).toHaveLength(0);
        expect(wrapper.get('[data-test="cron-status"]').text()).toBe(
            'ERRO:VALOR:Minuto:60',
        );
        expect(desabilitado(wrapper, 'COPIAR')).toBe(true);
    });

    it('avisa quando o horizonte acaba sem execucao', async () => {
        const wrapper = montar();

        await digitar(wrapper, '0 0 30 2 *');

        expect(execucoes(wrapper)).toHaveLength(0);
        expect(wrapper.find('[data-test="cron-exhausted"]').exists()).toBe(
            true,
        );
        expect(wrapper.get('[data-test="cron-status"]').text()).toMatch(
            /^OK:0:/,
        );
    });

    it('carrega o exemplo e limpa', async () => {
        const wrapper = montar();

        await clicar(wrapper, 'EXEMPLO');

        expect(
            wrapper.get('[data-test="cron-expression"]').element,
        ).toHaveProperty('value', '0 9 * * 1-5');
        expect(execucoes(wrapper)).toHaveLength(10);

        await clicar(wrapper, 'LIMPAR');

        expect(
            wrapper.get('[data-test="cron-expression"]').element,
        ).toHaveProperty('value', '');
        expect(wrapper.get('[data-test="cron-state"]').text()).toBe('ESCREVA');
    });

    it('copia o relatorio com a expressao, o fuso e as execucoes', async () => {
        const wrapper = montar();

        await digitar(wrapper, '0 9 * * 1-5');
        await clicar(wrapper, 'COPIAR');

        expect(clipboard.writeText).toHaveBeenCalledTimes(1);

        const text = clipboard.writeText.mock.calls[0]?.[0] as string;

        expect(text.split('\n')[0]).toBe('0 9 * * 1-5');
        expect(text).toContain('FUSO: ');
        expect(text).toContain('EXECUCOES:');
        expect(
            text.split('\n').filter((line) => /^\d+\. /.test(line)),
        ).toHaveLength(10);
        expect(wrapper.get('[data-test="cron-status"]').text()).toBe('COPIADO');
    });

    it('explica a macro pela expressao expandida', async () => {
        const wrapper = montar();

        await digitar(wrapper, '@daily');

        expect(wrapper.findAll('[data-test="cron-field"]')).toHaveLength(5);
        expect(campo(wrapper, 'minute').findAll('td')[0]?.text()).toBe('0');
        expect(significado(wrapper, 'day_of_month')).toBe('ANY_DOM');
        expect(execucoes(wrapper)).toHaveLength(10);
    });

    it('rotula os dois paineis', () => {
        const wrapper = montar();

        expect(wrapper.text()).toContain('EXPRESSAO');
        expect(wrapper.text()).toContain('EXECUCOES');
    });
});
