/**
 * Tipos do registro de tools (docs/SPEC.md secao 4).
 *
 * A fonte e o proprio diretorio de modulos: cada tool tem um `manifest.json`
 * com identidade e ciclo de vida; nenhum texto visivel mora ali (i18n por
 * convencao: `tools.<slug>.name`, `tools.<slug>.description`,
 * `tools.<slug>.keywords`).
 */

/** Ciclo de vida da tool: `hidden` nao aparece em lugar nenhum. */
export type ToolStatus = 'soon' | 'available' | 'hidden';

export type ToolCategoryId =
    | 'json'
    | 'sql'
    | 'encode'
    | 'security'
    | 'time'
    | 'text'
    | 'badges'
    | 'colors';

/**
 * Namespace de URL de uma tool (`/{locale}/tools/<group>/<slug>`). E so de
 * URL: nao troca a categoria e nao tem landing propria (ADR-116).
 */
export type ToolGroupId = 'colors';

export interface ToolManifest {
    /** Identificador da tool; e tambem o nome do diretorio e o prefixo das chaves de traducao. */
    slug: string;
    status: ToolStatus;
    category: ToolCategoryId;
    /** Quando existe, a URL da tool ganha um nivel e `toolPath` o respeita. */
    group?: ToolGroupId;
    /** Nome no mapa de `icons.ts` - o JSON nao carrega componente. */
    icon: string;
    /** Ordem de exibicao (menor primeiro). */
    order: number;
    /** Termos tecnicos de busca que nao aparecem na tela (o i18n soma os traduzidos). */
    keywords: string[];
}

export interface ToolCategory {
    id: ToolCategoryId;
    icon: string;
}
