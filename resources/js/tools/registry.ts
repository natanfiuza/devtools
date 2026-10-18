import { categoryOrder } from './categories';
import type { ToolCategoryId, ToolManifest } from './types';

/**
 * O registro e lido direto dos `manifest.json` pelo Vite: uma fonte, dois
 * leitores - aqui no frontend (home e busca) e no PHP (`App\Support\Tools`,
 * para o sitemap). `eager` porque a home e a busca precisam da lista de
 * forma sincrona, no primeiro render.
 */
const modules = import.meta.glob('./*/manifest.json', {
    eager: true,
    import: 'default',
}) as Record<string, ToolManifest>;

/** Tools visiveis, na ordem de exibicao. `hidden` nao aparece em lugar nenhum. */
export const allTools: ToolManifest[] = Object.values(modules)
    .filter((tool) => tool.status !== 'hidden')
    .sort((a, b) => a.order - b.order);

/** Tools que ja funcionam: as unicas que o card linka e o sitemap lista. */
export const availableTools: ToolManifest[] = allTools.filter(
    (tool) => tool.status === 'available',
);

/**
 * Categorias que tem pelo menos uma tool na lista, na ordem do catalogo.
 * A lista e parametro para o grid poder filtrar o que recebeu por prop; sem
 * argumento, vale o registro inteiro.
 */
export function categoriesInUse(
    tools: ToolManifest[] = allTools,
): ToolCategoryId[] {
    const used = new Set(tools.map((tool) => tool.category));

    return categoryOrder.filter((id) => used.has(id));
}

/** Tools de uma categoria (ou todas, com `null`). */
export function toolsByCategory(
    category: ToolCategoryId | null,
    tools: ToolManifest[] = allTools,
): ToolManifest[] {
    return category === null
        ? tools
        : tools.filter((tool) => tool.category === category);
}

/**
 * Caminho publico da tool, ou `null` enquanto ela nao funciona: card de tool
 * `soon` nao tem link (a rota entra na F2, junto com a primeira tool).
 */
export function toolPath(
    tool: ToolManifest,
    localeSlug: string,
): string | null {
    return tool.status === 'available'
        ? `/${localeSlug}/tools/${tool.slug}`
        : null;
}

/**
 * Chaves de traducao por convencao: nenhum texto visivel mora no manifest.
 * O prefixo e o slug, entao o manifest e o arquivo de lang casam pelo nome.
 */
export const toolKeys = {
    name: (tool: ToolManifest): string => `tools.${tool.slug}.name`,
    description: (tool: ToolManifest): string =>
        `tools.${tool.slug}.description`,
    keywords: (tool: ToolManifest): string => `tools.${tool.slug}.keywords`,
};

/** Rotulo do ciclo de vida, para o selo que nao seja `available`. */
export function statusKey(tool: ToolManifest): string {
    return `tools.status.${tool.status}`;
}
