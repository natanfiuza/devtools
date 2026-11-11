import type { PaletteRole, PaletteTheme } from '@/types/colors';

/**
 * Tipos do renderizador de prototipos (F4, ADR-126).
 *
 * O prototipo nao e uma captura de tela: e uma arvore de primitivas SVG
 * desenhada a partir dos papeis de cor da paleta. `renderSvg` serializa esta
 * arvore; o PNG rasteriza exatamente o mesmo SVG, entao os dois formatos nunca
 * divergem.
 */

/** Os templates fixos do prototipo; a OG usa o mesmo renderizador. */
export type PrototypeTemplate = 'site' | 'mobile';

/** Tipo de documento: os dois templates e a imagem de compartilhamento. */
export type PrototypeDocumentKind = PrototypeTemplate | 'og';

export type PrototypeSize = {
    width: number;
    height: number;
};

/** Regiao da interface; vai para o SVG como `data-section`. */
export type PrototypeSection =
    | 'canvas'
    | 'header'
    | 'appbar'
    | 'tabs'
    | 'hero'
    | 'buttons'
    | 'links'
    | 'cards'
    | 'content'
    | 'form'
    | 'footer'
    | 'palette';

export type SvgTextAnchor = 'start' | 'middle' | 'end';

type BaseNode = {
    fill: string;
    /** Papel de cor que pintou o elemento; vai para `data-role`. */
    role?: PaletteRole;
    section?: PrototypeSection;
};

export type SvgRectNode = BaseNode & {
    kind: 'rect';
    x: number;
    y: number;
    width: number;
    height: number;
    radius?: number;
    stroke?: string;
    strokeWidth?: number;
};

export type SvgCircleNode = BaseNode & {
    kind: 'circle';
    cx: number;
    cy: number;
    radius: number;
};

export type SvgTextNode = BaseNode & {
    kind: 'text';
    x: number;
    y: number;
    text: string;
    size: number;
    weight: 400 | 600 | 700;
    anchor?: SvgTextAnchor;
};

export type SvgNode = SvgRectNode | SvgCircleNode | SvgTextNode;

/**
 * Texto de exemplo que aparece dentro do prototipo.
 *
 * Vem do i18n de quem chama: o renderizador e puro e nunca traduz nada. O
 * conteudo e proposital (`Acme`, `Learn more`), so para mostrar como a paleta
 * se comporta em uma interface real.
 */
export type PrototypeCopy = {
    brand: string;
    nav: [string, string, string];
    heading: string;
    body: string;
    primaryAction: string;
    secondaryAction: string;
    links: [string, string, string];
    sectionTitle: string;
    cards: [string, string, string];
    cardBody: string;
    cardLink: string;
    formTitle: string;
    formLabel: string;
    formPlaceholder: string;
    formAction: string;
    footer: string;
    tabs: [string, string, string];
    /** Subtitulo da imagem de compartilhamento. */
    tagline: string;
};

/** Documento pronto para serializar. */
export type PrototypeDocument = {
    kind: PrototypeDocumentKind;
    theme: PaletteTheme;
    width: number;
    height: number;
    /** Nome acessivel do SVG (`role="img"` + `<title>`), ja traduzido. */
    label: string;
    nodes: SvgNode[];
};

export type RenderOptions = {
    /**
     * CSS a embutir em `<style>` (o `@font-face` com a fonte em data URI).
     * Fica de fora por padrao para o preview ao vivo usar a fonte do app e
     * para os snapshots nao carregarem ~290 KB de base64.
     */
    fonts?: string;
};
