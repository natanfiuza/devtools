import html2pdf from 'html2pdf.js';
import { getPaperDimensions, getPrintCss, MARGIN_VALUES_MM } from './themes';
import type { DocumentConfig } from './types';

/**
 * Utilitarios de exportacao e impressao para o conversor de Markdown para PDF.
 *
 * Processamento 100% no cliente (ADR-002):
 * - `exportToPdf`: Gera o arquivo binario `.pdf` localmente no navegador e dispara o download.
 * - `printDocument`: Dispara o dialogo nativo de impressao (`window.print`) atraves de um iframe
 *   isolado, aplicando regras CSS `@page` e `@media print` para renderizacao vetorial de alta nitidez.
 */

export async function exportToPdf(
    element: HTMLElement,
    filename: string,
    config: DocumentConfig,
): Promise<void> {
    const marginMm = MARGIN_VALUES_MM[config.margin];
    const { widthMm, minHeightMm } = getPaperDimensions(
        config.format,
        config.orientation,
    );

    const cleanFilename = filename.trim().toLowerCase().endsWith('.pdf')
        ? filename.trim()
        : `${filename.trim() || 'documento'}.pdf`;

    const opt = {
        margin: marginMm,
        filename: cleanFilename,
        image: { type: 'jpeg' as const, quality: 0.98 },
        enableLinks: true,
        html2canvas: {
            scale: 2,
            useCORS: true,
            letterRendering: true,
            logging: false,
        },
        jsPDF: {
            unit: 'mm' as const,
            format: [widthMm, minHeightMm] as [number, number],
            orientation: config.orientation,
            compress: true,
        },
        pagebreak: {
            mode: ['avoid-all', 'css', 'legacy'],
            avoid: [
                'h1',
                'h2',
                'h3',
                'h4',
                'pre',
                'table',
                'blockquote',
                'tr',
                'img',
            ],
        },
    };

    await html2pdf().from(element).set(opt).save();
}

export function printDocument(
    element: HTMLElement,
    title: string,
    config: DocumentConfig,
): void {
    if (typeof window === 'undefined' || typeof document === 'undefined') {
        return;
    }

    const printIframe = document.createElement('iframe');
    printIframe.setAttribute('title', 'Print preview');
    printIframe.style.position = 'fixed';
    printIframe.style.top = '-9999px';
    printIframe.style.left = '-9999px';
    printIframe.style.width = '0';
    printIframe.style.height = '0';
    printIframe.style.border = 'none';
    document.body.appendChild(printIframe);

    const doc =
        printIframe.contentDocument || printIframe.contentWindow?.document;

    if (!doc) {
        printIframe.remove();
        window.print();

        return;
    }

    const printCss = getPrintCss(config);

    doc.open();
    doc.write(`
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>${escapeHtml(title || 'Documento')}</title>
    <style>
        ${printCss}
    </style>
</head>
<body>
    <div class="markdown-pdf-document">
        ${element.innerHTML}
    </div>
</body>
</html>
    `);
    doc.close();

    const frameWindow = printIframe.contentWindow;

    if (!frameWindow) {
        printIframe.remove();

        return;
    }

    // Aguarda o frame carregar fontes e imagens antes de disparar o print
    const triggerPrint = (): void => {
        try {
            frameWindow.focus();
            frameWindow.print();
        } finally {
            // Remove o iframe apos breve intervalo
            window.setTimeout(() => {
                printIframe.remove();
            }, 1000);
        }
    };

    if (doc.readyState === 'complete') {
        triggerPrint();
    } else {
        frameWindow.onload = triggerPrint;
    }
}

function escapeHtml(text: string): string {
    return text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
