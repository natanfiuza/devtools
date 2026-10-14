import { toolKeys } from './registry';
import type { ToolManifest } from './types';

export type TranslateFn = (key: string) => string;

/** Marcas de acento separadas pelo `normalize('NFD')`. */
const DIACRITICS = /\p{Diacritic}/gu;

/**
 * Normaliza para comparar: sem acento, minusculo, sem espaco nas pontas.
 * "Codificacao" e "Codificacao" com acento precisam casar na busca.
 */
export function normalize(text: string): string {
    return text.normalize('NFD').replace(DIACRITICS, '').toLowerCase().trim();
}

/**
 * Le uma chave traduzida tratando ausencia: o vue-i18n devolve a propria
 * chave quando ela nao existe, e a chave contem o slug - se entrasse no
 * texto de busca, "viewer" acharia qualquer coisa pelo nome da chave.
 */
function safeTranslate(t: TranslateFn, key: string): string {
    const value = t(key);

    return value === key ? '' : value;
}

function keywordsOf(t: TranslateFn, tool: ToolManifest): string {
    const localized = safeTranslate(t, toolKeys.keywords(tool))
        .split(',')
        .map((keyword) => normalize(keyword))
        .filter((keyword) => keyword !== '');

    return [...tool.keywords.map(normalize), ...localized].join(' ');
}

/**
 * Onde a busca achou: menor e melhor. Nome que comeca com o termo ganha de
 * nome que contem, que ganha de categoria/palavra-chave, que ganha da
 * descricao. `-1` e "nao achou".
 */
function rank(t: TranslateFn, tool: ToolManifest, needle: string): number {
    const name = normalize(safeTranslate(t, toolKeys.name(tool)));

    if (name.startsWith(needle)) {
        return 0;
    }

    if (name.includes(needle)) {
        return 1;
    }

    const category = normalize(safeTranslate(t, `tools.categories.${tool.category}`));

    if (category.includes(needle) || keywordsOf(t, tool).includes(needle)) {
        return 2;
    }

    const description = normalize(
        safeTranslate(t, toolKeys.description(tool)),
    );

    return description.includes(needle) ? 3 : -1;
}

/**
 * Filtra e ordena as tools para a palette. Consulta vazia devolve a lista
 * inteira (a palette abre mostrando tudo, como um indice).
 */
export function searchTools(
    tools: ToolManifest[],
    query: string,
    t: TranslateFn,
): ToolManifest[] {
    const needle = normalize(query);

    if (needle === '') {
        return [...tools];
    }

    return tools
        .map((tool) => ({ tool, score: rank(t, tool, needle) }))
        .filter((entry) => entry.score >= 0)
        .sort((a, b) => a.score - b.score || a.tool.order - b.tool.order)
        .map((entry) => entry.tool);
}
