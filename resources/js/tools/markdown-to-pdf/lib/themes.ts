import type {
    ColorTheme,
    DocumentConfig,
    FontFamily,
    FontSize,
    MarginSize,
    PageOrientation,
    PaperFormat,
} from './types';

/**
 * Dimensoes fisicas dos papeis (em milimetros).
 */
export const PAPER_DIMENSIONS: Record<
    PaperFormat,
    { widthMm: number; heightMm: number }
> = {
    a4: { widthMm: 210, heightMm: 297 },
    letter: { widthMm: 215.9, heightMm: 279.4 },
    a5: { widthMm: 148, heightMm: 210 },
};

/**
 * Margens em milimetros.
 */
export const MARGIN_VALUES_MM: Record<MarginSize, number> = {
    compact: 10,
    normal: 20,
    wide: 30,
};

/**
 * Dimensoes calculadas para a folha de preview na tela e impressao.
 */
export function getPaperDimensions(
    format: PaperFormat,
    orientation: PageOrientation,
): { widthMm: number; minHeightMm: number } {
    const base = PAPER_DIMENSIONS[format];

    if (orientation === 'landscape') {
        return {
            widthMm: base.heightMm,
            minHeightMm: base.widthMm,
        };
    }

    return {
        widthMm: base.widthMm,
        minHeightMm: base.heightMm,
    };
}

/**
 * Estilo de familia tipografica.
 */
export function getFontFamilyCss(font: FontFamily): string {
    switch (font) {
        case 'serif':
            return 'Merriweather, Georgia, Cambria, "Times New Roman", serif';
        case 'mono':
            return '"JetBrains Mono", ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace';
        case 'fira-code':
            return '"Fira Code", "Cascadia Code", Consolas, monospace';
        case 'sans':
        default:
            return 'Inter, Arial, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
    }
}

/**
 * Valores tipograficos base (padrao Google Docs em pt/px e entrelinha).
 */
export function getFontSizeCss(size: FontSize): {
    fontSize: string;
    lineHeight: string;
} {
    switch (size) {
        case 'sm':
            return { fontSize: '9pt', lineHeight: '1.35' };
        case 'lg':
            return { fontSize: '11pt', lineHeight: '1.35' };
        case 'md':
        default:
            return { fontSize: '10pt', lineHeight: '1.35' };
    }
}

/**
 * Classes Tailwind para o tamanho base de fonte no padrao Google Docs.
 * - sm: 9pt (12px)
 * - md: 10pt (13.3px, padrao)
 * - lg: 11pt (14.67px)
 */
export function getFontSizeClasses(size: FontSize): string {
    switch (size) {
        case 'sm':
            return 'text-[12px] leading-[1.35]';
        case 'lg':
            return 'text-[14.67px] leading-[1.35]';
        case 'md':
        default:
            return 'text-[13.3px] leading-[1.35]';
    }
}

/**
 * Classes de tema de cores aplicadas ao container do documento.
 */
export function getThemeClasses(theme: ColorTheme): string {
    switch (theme) {
        case 'editorial':
            return 'theme-editorial bg-white';
        case 'minimal':
            return 'theme-minimal bg-white';
        case 'academic':
            return 'theme-academic bg-white';
        case 'modern':
        default:
            return 'theme-modern bg-white';
    }
}

/**
 * Gera os estilos CSS inline para a folha simulada na tela.
 * Usa width: 100% com maxWidth na medida fisica do papel para que o preview
 * se adapte ao painel sem rolagem horizontal, enquanto o exportToPdf trava
 * a medida fisica exata no onclone.
 */
export function getPageSheetStyle(
    config: DocumentConfig,
): Record<string, string> {
    const marginMm = MARGIN_VALUES_MM[config.margin];
    const { widthMm, minHeightMm } = getPaperDimensions(
        config.format,
        config.orientation,
    );

    return {
        width: '100%',
        maxWidth: `${widthMm}mm`,
        minHeight: `${minHeightMm}mm`,
        padding: `${marginMm}mm`,
        fontFamily: getFontFamilyCss(config.font),
    };
}

/**
 * Regras estruturais comuns (proporcao Google Docs em `em` e contencao estrita de tabelas/codigo).
 */
const BASE_DOCUMENT_STRUCTURE_CSS = `
.markdown-pdf-document {
    box-sizing: border-box;
    overflow-wrap: break-word;
    word-break: break-word;
}
.markdown-pdf-document > *:first-child {
    margin-top: 0 !important;
}
.markdown-pdf-document p {
    margin-top: 0;
    margin-bottom: 0.6em;
}
.markdown-pdf-document ul {
    list-style-type: disc;
    padding-left: 1.4em;
    margin-top: 0;
    margin-bottom: 0.6em;
}
.markdown-pdf-document ol {
    list-style-type: decimal;
    padding-left: 1.4em;
    margin-top: 0;
    margin-bottom: 0.6em;
}
.markdown-pdf-document li {
    margin-bottom: 0.2em;
}
.markdown-pdf-document input[type='checkbox'] {
    margin-right: 0.35em;
    vertical-align: middle;
}
.markdown-pdf-document img {
    max-width: 100%;
    height: auto;
    border-radius: 4px;
    margin: 0.75em 0;
}
.markdown-pdf-document table {
    width: 100%;
    max-width: 100%;
    border-collapse: collapse;
    margin: 0.75em 0;
    font-size: 0.9em;
}
.markdown-pdf-document th,
.markdown-pdf-document td {
    padding: 5px 8px;
    text-align: left;
    vertical-align: top;
    overflow-wrap: anywhere;
    word-break: break-word;
    white-space: normal;
}
.markdown-pdf-document th code,
.markdown-pdf-document td code {
    white-space: normal;
    overflow-wrap: anywhere;
    word-break: break-word;
}
.markdown-pdf-document pre {
    max-width: 100%;
    font-size: 0.9em;
    line-height: 1.45;
    padding: 0.65em 0.8em;
    margin: 0.75em 0;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
    word-break: break-word;
    font-family: monospace;
}
.markdown-pdf-document code {
    font-size: 0.9em;
    padding: 0.1em 0.3em;
    border-radius: 3px;
    font-family: monospace;
    overflow-wrap: anywhere;
    word-break: break-word;
}
.markdown-pdf-document pre code {
    background: transparent;
    color: inherit;
    padding: 0;
    border: none;
    font-size: 1em;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
    word-break: break-word;
}
`;

/**
 * Folha de estilos central dos elementos Markdown para preview e exportacao.
 */
export function getDocumentTypographyCss(theme: ColorTheme): string {
    switch (theme) {
        case 'editorial':
            return `
${BASE_DOCUMENT_STRUCTURE_CSS}
.markdown-pdf-document { color: #1c1917; background-color: #ffffff; }
.markdown-pdf-document h1 { color: #1e3a8a; font-size: 1.65em; line-height: 1.25; font-weight: 700; margin-top: 1.1em; margin-bottom: 0.45em; border-bottom: 1.5px solid #e2e8f0; padding-bottom: 0.25em; }
.markdown-pdf-document h2 { color: #1e3a8a; font-size: 1.35em; line-height: 1.3; font-weight: 600; margin-top: 1em; margin-bottom: 0.4em; border-bottom: 1px solid #cbd5e1; padding-bottom: 0.2em; }
.markdown-pdf-document h3 { color: #1e40af; font-size: 1.15em; line-height: 1.3; font-weight: 600; margin-top: 0.85em; margin-bottom: 0.35em; }
.markdown-pdf-document h4 { color: #1e40af; font-size: 1.05em; line-height: 1.35; font-weight: 600; margin-top: 0.7em; margin-bottom: 0.3em; }
.markdown-pdf-document h5, .markdown-pdf-document h6 { color: #1e40af; font-size: 0.95em; line-height: 1.35; font-weight: 600; margin-top: 0.6em; margin-bottom: 0.25em; }
.markdown-pdf-document a { color: #991b1b; text-decoration: underline; }
.markdown-pdf-document blockquote { border-left: 3px solid #991b1b; padding-left: 0.85em; color: #57534e; font-style: italic; margin: 0.75em 0; background: #fafaf9; padding-top: 0.35em; padding-bottom: 0.35em; }
.markdown-pdf-document pre { background: #f5f5f4; color: #292524; border: 1px solid #e7e5e4; border-radius: 4px; }
.markdown-pdf-document code { background: #f5f5f4; color: #991b1b; }
.markdown-pdf-document th, .markdown-pdf-document td { border: 1px solid #d6d3d1; }
.markdown-pdf-document th { background: #f5f5f4; color: #1e3a8a; font-weight: 600; }
.markdown-pdf-document hr { border: 0; border-top: 1px solid #d6d3d1; margin: 1em 0; }
`;
        case 'minimal':
            return `
${BASE_DOCUMENT_STRUCTURE_CSS}
.markdown-pdf-document { color: #000000; background-color: #ffffff; }
.markdown-pdf-document h1 { color: #000; font-size: 1.65em; line-height: 1.25; font-weight: 700; margin-top: 1.1em; margin-bottom: 0.45em; border-bottom: 1.5px solid #000; padding-bottom: 0.25em; }
.markdown-pdf-document h2 { color: #000; font-size: 1.35em; line-height: 1.3; font-weight: 700; margin-top: 1em; margin-bottom: 0.4em; border-bottom: 1px solid #000; padding-bottom: 0.2em; }
.markdown-pdf-document h3 { color: #000; font-size: 1.15em; line-height: 1.3; font-weight: 700; margin-top: 0.85em; margin-bottom: 0.35em; }
.markdown-pdf-document h4 { color: #000; font-size: 1.05em; line-height: 1.35; font-weight: 700; margin-top: 0.7em; margin-bottom: 0.3em; }
.markdown-pdf-document h5, .markdown-pdf-document h6 { color: #000; font-size: 0.95em; line-height: 1.35; font-weight: 700; margin-top: 0.6em; margin-bottom: 0.25em; }
.markdown-pdf-document a { color: #000; text-decoration: underline; font-weight: 500; }
.markdown-pdf-document blockquote { border-left: 3px solid #000; padding-left: 0.8em; color: #000; font-style: italic; margin: 0.75em 0; }
.markdown-pdf-document pre { background: #fff; color: #000; border: 1px solid #000; border-radius: 2px; }
.markdown-pdf-document code { background: #fff; color: #000; border: 1px solid #000; }
.markdown-pdf-document th, .markdown-pdf-document td { border: 1px solid #000; }
.markdown-pdf-document th { background: #fff; color: #000; font-weight: 700; }
.markdown-pdf-document hr { border: 0; border-top: 1px solid #000; margin: 1em 0; }
`;
        case 'academic':
            return `
${BASE_DOCUMENT_STRUCTURE_CSS}
.markdown-pdf-document { color: #171717; background-color: #ffffff; }
.markdown-pdf-document h1 { color: #171717; font-size: 1.6em; line-height: 1.25; font-weight: 700; margin-top: 1.1em; margin-bottom: 0.45em; text-align: center; }
.markdown-pdf-document h2 { color: #262626; font-size: 1.3em; line-height: 1.3; font-weight: 600; margin-top: 1em; margin-bottom: 0.4em; }
.markdown-pdf-document h3 { color: #404040; font-size: 1.15em; line-height: 1.3; font-weight: 600; margin-top: 0.85em; margin-bottom: 0.35em; }
.markdown-pdf-document h4 { color: #404040; font-size: 1.05em; line-height: 1.35; font-weight: 600; margin-top: 0.7em; margin-bottom: 0.3em; }
.markdown-pdf-document h5, .markdown-pdf-document h6 { color: #404040; font-size: 0.95em; line-height: 1.35; font-weight: 600; margin-top: 0.6em; margin-bottom: 0.25em; }
.markdown-pdf-document p { text-align: justify; text-indent: 1.25em; margin-bottom: 0.6em; line-height: 1.5; }
.markdown-pdf-document a { color: #0f172a; text-decoration: underline; }
.markdown-pdf-document blockquote { border-left: 3px solid #737373; padding-left: 1em; margin-left: 1em; color: #404040; font-style: italic; margin-top: 0.75em; margin-bottom: 0.75em; }
.markdown-pdf-document pre { background: #fafafa; color: #171717; border: 1px solid #e5e5e5; border-radius: 4px; }
.markdown-pdf-document code { background: #f5f5f5; color: #262626; }
.markdown-pdf-document th, .markdown-pdf-document td { border-top: 1px solid #171717; border-bottom: 1px solid #171717; }
.markdown-pdf-document th { border-bottom: 2px solid #171717; font-weight: 600; background: transparent; }
.markdown-pdf-document hr { border: 0; border-top: 1px solid #a3a3a3; margin: 1em 0; }
`;
        case 'modern':
        default:
            return `
${BASE_DOCUMENT_STRUCTURE_CSS}
.markdown-pdf-document { color: #1f2937; background-color: #ffffff; }
.markdown-pdf-document h1 { color: #111827; font-size: 1.65em; line-height: 1.25; font-weight: 600; margin-top: 1.1em; margin-bottom: 0.45em; border-bottom: 1px solid #e5e7eb; padding-bottom: 0.25em; }
.markdown-pdf-document h2 { color: #1f2937; font-size: 1.35em; line-height: 1.3; font-weight: 600; margin-top: 1em; margin-bottom: 0.4em; border-bottom: 1px solid #f3f4f6; padding-bottom: 0.2em; }
.markdown-pdf-document h3 { color: #374151; font-size: 1.15em; line-height: 1.3; font-weight: 600; margin-top: 0.85em; margin-bottom: 0.35em; }
.markdown-pdf-document h4 { color: #4b5563; font-size: 1.05em; line-height: 1.35; font-weight: 600; margin-top: 0.7em; margin-bottom: 0.3em; }
.markdown-pdf-document h5, .markdown-pdf-document h6 { color: #4b5563; font-size: 0.95em; line-height: 1.35; font-weight: 600; margin-top: 0.6em; margin-bottom: 0.25em; }
.markdown-pdf-document a { color: #1a73e8; text-decoration: underline; text-underline-offset: 2px; }
.markdown-pdf-document blockquote { border-left: 3px solid #1a73e8; padding-left: 0.85em; color: #4b5563; font-style: italic; margin: 0.75em 0; background: #f8f9fa; padding-top: 0.35em; padding-bottom: 0.35em; border-radius: 0 4px 4px 0; }
.markdown-pdf-document pre { background: #f8f9fa; color: #1f2937; border: 1px solid #dadce0; border-radius: 4px; }
.markdown-pdf-document code { background: #f1f3f4; color: #1f2937; }
.markdown-pdf-document th, .markdown-pdf-document td { border: 1px solid #dadce0; }
.markdown-pdf-document th { background: #f8f9fa; color: #111827; font-weight: 600; }
.markdown-pdf-document hr { border: 0; border-top: 1px solid #dadce0; margin: 1em 0; }
`;
    }
}

/**
 * Gera a folha de estilos CSS completa para o modo de impressao nativa `@media print` e `@page`.
 */
export function getPrintCss(config: DocumentConfig): string {
    const { widthMm, minHeightMm } = getPaperDimensions(
        config.format,
        config.orientation,
    );
    const marginMm = MARGIN_VALUES_MM[config.margin];
    const fontCss = getFontFamilyCss(config.font);
    const { fontSize, lineHeight } = getFontSizeCss(config.fontSize);
    const typographyCss = getDocumentTypographyCss(config.theme);

    return `
@page {
    size: ${widthMm}mm ${minHeightMm}mm;
    margin: ${marginMm}mm;
}

@media print {
    *, *::before, *::after {
        box-sizing: border-box;
    }

    body {
        margin: 0 !important;
        padding: 0 !important;
        background: #fff !important;
        color: #000 !important;
        font-family: ${fontCss} !important;
        font-size: ${fontSize} !important;
        line-height: ${lineHeight} !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .markdown-pdf-document {
        width: 100% !important;
        max-width: 100% !important;
        min-height: auto !important;
        padding: 0 !important;
        margin: 0 !important;
        box-shadow: none !important;
        border: none !important;
        font-family: ${fontCss} !important;
        font-size: ${fontSize} !important;
        line-height: ${lineHeight} !important;
    }

    /* Quebras de pagina inteligentes: permite tabelas longas fluirem entre paginas protegendo linhas (tr) */
    thead {
        display: table-header-group;
    }

    h1, h2, h3, h4, h5, h6 {
        page-break-after: avoid;
        break-after: avoid;
    }

    blockquote, img, tr {
        page-break-inside: avoid;
        break-inside: avoid;
    }
}

${typographyCss}
`;
}
