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
            return 'Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
    }
}

/**
 * Classes Tailwind ou CSS para o tamanho base de fonte.
 */
export function getFontSizeClasses(size: FontSize): string {
    switch (size) {
        case 'sm':
            return 'text-[12.5px] leading-relaxed';
        case 'lg':
            return 'text-[16px] leading-relaxed';
        case 'md':
        default:
            return 'text-[14px] leading-relaxed';
    }
}

/**
 * Classes de tema de cores aplicadas ao container do documento.
 */
export function getThemeClasses(theme: ColorTheme): string {
    switch (theme) {
        case 'editorial':
            return 'theme-editorial bg-white text-stone-900';
        case 'minimal':
            return 'theme-minimal bg-white text-black';
        case 'academic':
            return 'theme-academic bg-white text-neutral-900';
        case 'modern':
        default:
            return 'theme-modern bg-white text-slate-800';
    }
}

/**
 * Gera os estilos CSS inline para a folha simulada na tela.
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
        width: `${widthMm}mm`,
        minHeight: `${minHeightMm}mm`,
        padding: `${marginMm}mm`,
        fontFamily: getFontFamilyCss(config.font),
    };
}

/**
 * Folha de estilos central dos elementos Markdown para preview e exportacao.
 */
export function getDocumentTypographyCss(theme: ColorTheme): string {
    switch (theme) {
        case 'editorial':
            return `
.markdown-pdf-document h1 { color: #1e3a8a; font-size: 2rem; font-weight: 700; margin-top: 1.5rem; margin-bottom: 0.8rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.4rem; }
.markdown-pdf-document h2 { color: #1e3a8a; font-size: 1.5rem; font-weight: 600; margin-top: 1.25rem; margin-bottom: 0.6rem; border-bottom: 1px solid #cbd5e1; padding-bottom: 0.3rem; }
.markdown-pdf-document h3 { color: #1e40af; font-size: 1.25rem; font-weight: 600; margin-top: 1rem; margin-bottom: 0.5rem; }
.markdown-pdf-document h4, .markdown-pdf-document h5, .markdown-pdf-document h6 { color: #1e40af; font-weight: 600; margin-top: 0.8rem; margin-bottom: 0.4rem; }
.markdown-pdf-document a { color: #991b1b; text-decoration: underline; }
.markdown-pdf-document blockquote { border-left: 4px solid #991b1b; padding-left: 1rem; color: #57534e; font-style: italic; margin: 1rem 0; background: #fafaf9; padding-top: 0.5rem; padding-bottom: 0.5rem; }
.markdown-pdf-document pre { background: #f5f5f4; color: #292524; border: 1px solid #e7e5e4; border-radius: 6px; padding: 0.8rem; overflow-x: auto; font-family: monospace; }
.markdown-pdf-document code { background: #f5f5f4; color: #991b1b; padding: 0.15rem 0.35rem; border-radius: 4px; font-family: monospace; font-size: 0.9em; }
.markdown-pdf-document pre code { background: transparent; color: inherit; padding: 0; }
.markdown-pdf-document table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
.markdown-pdf-document th, .markdown-pdf-document td { border: 1px solid #d6d3d1; padding: 0.6rem 0.8rem; text-align: left; }
.markdown-pdf-document th { background: #f5f5f4; color: #1e3a8a; font-weight: 600; }
.markdown-pdf-document hr { border: 0; border-top: 1px solid #d6d3d1; margin: 1.5rem 0; }
`;
        case 'minimal':
            return `
.markdown-pdf-document h1 { color: #000; font-size: 1.85rem; font-weight: 700; margin-top: 1.4rem; margin-bottom: 0.7rem; border-bottom: 1.5px solid #000; padding-bottom: 0.3rem; }
.markdown-pdf-document h2 { color: #000; font-size: 1.4rem; font-weight: 700; margin-top: 1.2rem; margin-bottom: 0.5rem; border-bottom: 1px solid #000; padding-bottom: 0.2rem; }
.markdown-pdf-document h3 { color: #000; font-size: 1.15rem; font-weight: 700; margin-top: 0.9rem; margin-bottom: 0.4rem; }
.markdown-pdf-document h4, .markdown-pdf-document h5, .markdown-pdf-document h6 { color: #000; font-weight: 700; margin-top: 0.7rem; margin-bottom: 0.3rem; }
.markdown-pdf-document a { color: #000; text-decoration: underline; font-weight: 500; }
.markdown-pdf-document blockquote { border-left: 3px solid #000; padding-left: 0.8rem; color: #000; font-style: italic; margin: 0.8rem 0; }
.markdown-pdf-document pre { background: #fff; color: #000; border: 1px solid #000; border-radius: 2px; padding: 0.7rem; overflow-x: auto; font-family: monospace; }
.markdown-pdf-document code { background: #fff; color: #000; border: 1px solid #000; padding: 0.1rem 0.25rem; font-family: monospace; font-size: 0.9em; }
.markdown-pdf-document pre code { border: none; padding: 0; }
.markdown-pdf-document table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
.markdown-pdf-document th, .markdown-pdf-document td { border: 1px solid #000; padding: 0.5rem 0.7rem; text-align: left; }
.markdown-pdf-document th { background: #fff; color: #000; font-weight: 700; }
.markdown-pdf-document hr { border: 0; border-top: 1px solid #000; margin: 1.2rem 0; }
`;
        case 'academic':
            return `
.markdown-pdf-document h1 { color: #171717; font-size: 1.75rem; font-weight: 700; margin-top: 1.5rem; margin-bottom: 0.75rem; text-align: center; }
.markdown-pdf-document h2 { color: #262626; font-size: 1.35rem; font-weight: 600; margin-top: 1.3rem; margin-bottom: 0.6rem; }
.markdown-pdf-document h3 { color: #404040; font-size: 1.15rem; font-weight: 600; margin-top: 1rem; margin-bottom: 0.5rem; }
.markdown-pdf-document p { text-align: justify; text-indent: 1.5em; margin-bottom: 0.8rem; line-height: 1.75; }
.markdown-pdf-document a { color: #0f172a; text-decoration: underline; }
.markdown-pdf-document blockquote { border-left: 3px solid #737373; padding-left: 1.5rem; margin-left: 1.5rem; color: #404040; font-style: italic; margin-top: 1rem; margin-bottom: 1rem; }
.markdown-pdf-document pre { background: #fafafa; color: #171717; border: 1px solid #e5e5e5; border-radius: 4px; padding: 0.75rem; overflow-x: auto; font-family: monospace; }
.markdown-pdf-document code { background: #f5f5f5; color: #262626; padding: 0.15rem 0.3rem; border-radius: 3px; font-family: monospace; font-size: 0.9em; }
.markdown-pdf-document pre code { background: transparent; padding: 0; }
.markdown-pdf-document table { width: 100%; border-collapse: collapse; margin: 1.2rem 0; }
.markdown-pdf-document th, .markdown-pdf-document td { border-top: 1px solid #171717; border-bottom: 1px solid #171717; padding: 0.5rem 0.75rem; text-align: left; }
.markdown-pdf-document th { border-bottom: 2px solid #171717; font-weight: 600; background: transparent; }
.markdown-pdf-document hr { border: 0; border-top: 1px solid #a3a3a3; margin: 1.5rem 0; }
`;
        case 'modern':
        default:
            return `
.markdown-pdf-document h1 { color: #0f172a; font-size: 1.9rem; font-weight: 700; margin-top: 1.4rem; margin-bottom: 0.75rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.4rem; }
.markdown-pdf-document h2 { color: #1e293b; font-size: 1.45rem; font-weight: 600; margin-top: 1.25rem; margin-bottom: 0.6rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.3rem; }
.markdown-pdf-document h3 { color: #334155; font-size: 1.2rem; font-weight: 600; margin-top: 1rem; margin-bottom: 0.5rem; }
.markdown-pdf-document h4, .markdown-pdf-document h5, .markdown-pdf-document h6 { color: #475569; font-weight: 600; margin-top: 0.8rem; margin-bottom: 0.4rem; }
.markdown-pdf-document a { color: #2563eb; text-decoration: underline; text-underline-offset: 2px; }
.markdown-pdf-document blockquote { border-left: 4px solid #3b82f6; padding-left: 1rem; color: #475569; font-style: italic; margin: 1rem 0; background: #f8fafc; padding-top: 0.5rem; padding-bottom: 0.5rem; border-radius: 0 4px 4px 0; }
.markdown-pdf-document pre { background: #0f172a; color: #f8fafc; border-radius: 6px; padding: 0.85rem; overflow-x: auto; font-family: monospace; margin: 1rem 0; }
.markdown-pdf-document code { background: #f1f5f9; color: #0f172a; padding: 0.15rem 0.35rem; border-radius: 4px; font-family: monospace; font-size: 0.9em; }
.markdown-pdf-document pre code { background: transparent; color: inherit; padding: 0; }
.markdown-pdf-document table { width: 100%; border-collapse: collapse; margin: 1.2rem 0; }
.markdown-pdf-document th, .markdown-pdf-document td { border: 1px solid #e2e8f0; padding: 0.6rem 0.8rem; text-align: left; }
.markdown-pdf-document th { background: #f8fafc; color: #0f172a; font-weight: 600; }
.markdown-pdf-document hr { border: 0; border-top: 1px solid #e2e8f0; margin: 1.5rem 0; }
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
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .markdown-pdf-document {
        width: 100% !important;
        max-width: none !important;
        min-height: auto !important;
        padding: 0 !important;
        margin: 0 !important;
        box-shadow: none !important;
        border: none !important;
        font-family: ${fontCss} !important;
    }

    /* Quebras de pagina inteligentes */
    h1, h2, h3, h4, h5, h6 {
        page-break-after: avoid;
        break-after: avoid;
    }

    table, pre, blockquote, img {
        page-break-inside: avoid;
        break-inside: avoid;
    }

    tr {
        page-break-inside: avoid;
        break-inside: avoid;
    }
}

${typographyCss}
`;
}
