import type { JsonValue } from '@/lib/json/types';
import { emitPhp } from './emit/php';
import { emitPydantic } from './emit/pydantic';
import { emitTypeScript } from './emit/typescript';
import { infer } from './infer';
import { identifier } from './names';
import { planTypes, type TypePlan } from './plan';

/**
 * A geracao inteira: do valor JSON ao codigo do alvo escolhido.
 *
 * E o caminho das tres etapas - inferir, planejar os nomes, escrever - num
 * lugar so, que e o que a tela chama. Cada etapa tem o seu modulo porque cada
 * uma tem a sua pergunta: *o que o documento tem* (`infer`), *como os tipos se
 * chamam* (`plan`) e *como isso se escreve* (`emit`).
 */

/**
 * As linguagens de destino.
 *
 * Constante do modulo, e nao tabela nem configuracao: nao ha limite aqui, ha
 * uma lista de produto - as linguagens que a ferramenta sabe escrever. Acrescentar
 * uma e acrescentar um emissor, e nao mudar um numero.
 */
export const TARGETS = ['typescript', 'php', 'pydantic'] as const;

export type Target = (typeof TARGETS)[number];

/**
 * A extensao do arquivo baixado, por alvo.
 *
 * Codigo, e nao texto de tela: `ts`, `php` e `py` sao as extensoes da linguagem,
 * iguais nos cinco idiomas. O **nome** do arquivo e que passa pelo i18n.
 */
export const TARGET_EXTENSIONS: Record<Target, string> = {
    typescript: 'ts',
    php: 'php',
    pydantic: 'py',
};

/**
 * O nome da raiz quando o campo esta vazio.
 *
 * Identificador, e nao texto de tela: ele aparece no codigo gerado e e o mesmo
 * nos cinco idiomas, entao nao passa pelo i18n.
 */
export const DEFAULT_ROOT_NAME = 'Root';

export interface GenerateOptions {
    target: Target;
    /** O que foi digitado no cabecalho da saida; vazio ou invalido cai no padrao. */
    rootName: string;
}

export interface Generation {
    code: string;
    /** Quantos tipos a saida declara - a raiz conta como um. */
    types: number;
}

const EMITTERS: Record<Target, (plan: TypePlan) => string> = {
    typescript: emitTypeScript,
    php: emitPhp,
    pydantic: emitPydantic,
};

/**
 * Gera o codigo. Devolve `null` quando **nao ha o que gerar**.
 *
 * Os dois casos sao o mesmo problema visto de dois angulos: a raiz nao e um
 * objeto (um numero, um texto, uma lista) ou e um objeto **sem chave nenhuma**.
 * DTO de numero nao existe em PHP nem em Python, e uma classe sem campo nao
 * descreve nada - entao a tela diz que precisa de um objeto com chaves, em vez
 * de escrever um tipo vazio que pareceria resposta.
 */
export function generate(value: JsonValue, options: GenerateOptions): Generation | null {
    const root = infer(value);

    if (root.kind !== 'object') {
        return null;
    }

    const plan = planTypes(root, identifier(options.rootName, DEFAULT_ROOT_NAME));

    return { code: EMITTERS[options.target](plan), types: plan.declarations.length };
}
