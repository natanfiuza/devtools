import html2canvas from 'html2canvas-pro';
import { jsPDF } from 'jspdf';
import { getPaperDimensions, getPrintCss } from './themes';
import type { DocumentConfig } from './types';

/**
 * Utilitarios de exportacao e impressao para o conversor de Markdown para PDF.
 *
 * Processamento 100% no cliente (ADR-002, ADR-037):
 * - `exportToPdf`: Renderiza o documento via html2canvas-pro (com suporte nativo a oklab/oklch do Tailwind 4)
 *   e gera o arquivo binario `.pdf` localmente no navegador atraves do jsPDF.
 * - `printDocument`: Dispara o dialogo nativo de impressao (`window.print`) atraves de um iframe
 *   isolado, aplicando regras CSS `@page` e `@media print` para renderizacao vetorial de alta nitidez.
 */

export async function exportToPdf(
    element: HTMLElement,
    filename: string,
    config: DocumentConfig,
): Promise<void> {
    const { widthMm, minHeightMm } = getPaperDimensions(
        config.format,
        config.orientation,
    );

    const cleanFilename = filename.trim().toLowerCase().endsWith('.pdf')
        ? filename.trim()
        : `${filename.trim() || 'documento'}.pdf`;

    // Renderiza o elemento em canvas de alta resolucao (scale: 2)
    const canvas = await html2canvas(element, {
        scale: 2,
        useCORS: true,
        logging: false,
        backgroundColor: '#ffffff',
        onclone: (_clonedDoc: Document, clonedElement: HTMLElement) => {
            // Remove estilizacao visual de card do preview antes de fotografar a folha
            clonedElement.style.boxShadow = 'none';
            clonedElement.style.border = 'none';
            clonedElement.style.borderRadius = '0';
            clonedElement.style.backgroundColor = '#ffffff';
            clonedElement.classList.remove(
                'rounded',
                'border',
                'border-neutral-200/80',
                'shadow-md',
                'transition-all',
                'duration-150',
                'dark:border-neutral-700/80',
            );
        },
    });

    const pdf = new jsPDF({
        unit: 'mm',
        format: [widthMm, minHeightMm],
        orientation: config.orientation,
        compress: true,
    });

    const pxFullHeight = canvas.height;
    const pxPageHeight = Math.floor(canvas.width * (minHeightMm / widthMm));
    const nPages = Math.max(1, Math.ceil(pxFullHeight / pxPageHeight));

    for (let page = 0; page < nPages; page++) {
        const sourceY = page * pxPageHeight;
        const currentSliceHeight = Math.min(
            pxPageHeight,
            pxFullHeight - sourceY,
        );

        if (currentSliceHeight <= 0) {
            break;
        }

        const pageCanvas = document.createElement('canvas');
        pageCanvas.width = canvas.width;
        pageCanvas.height = currentSliceHeight;

        const pageCtx = pageCanvas.getContext('2d');
        if (pageCtx) {
            pageCtx.fillStyle = '#ffffff';
            pageCtx.fillRect(0, 0, pageCanvas.width, pageCanvas.height);
            pageCtx.drawImage(
                canvas,
                0,
                sourceY,
                canvas.width,
                currentSliceHeight,
                0,
                0,
                canvas.width,
                currentSliceHeight,
            );
        }

        const sliceHeightMm = (currentSliceHeight / pxPageHeight) * minHeightMm;
        const imgData = pageCanvas.toDataURL('image/jpeg', 0.98);

        if (page > 0) {
            pdf.addPage([widthMm, minHeightMm], config.orientation);
        }

        pdf.addImage(
            imgData,
            'JPEG',
            0,
            0,
            widthMm,
            sliceHeightMm,
            undefined,
            'FAST',
        );
    }

    pdf.save(cleanFilename);
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
