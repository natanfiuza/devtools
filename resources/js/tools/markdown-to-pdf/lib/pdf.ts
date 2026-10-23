import html2canvas from 'html2canvas-pro';
import { jsPDF } from 'jspdf';
import {
    getPaperDimensions,
    getPrintCss,
    MARGIN_VALUES_MM,
} from './themes';
import type { DocumentConfig } from './types';

/**
 * Utilitarios de exportacao e impressao para o conversor de Markdown para PDF.
 *
 * Processamento 100% no cliente (ADR-002, ADR-037):
 * - `exportToPdf`: Renderiza o documento via html2canvas-pro na largura fisica util exata
 *   da folha (`widthMm - 2 * marginMm`), fatia as paginas evitando cortar linhas de texto
 *   ao meio e posiciona cada pagina no jsPDF respeitando as 4 margens configuradas.
 * - `printDocument`: Dispara o dialogo nativo de impressao (`window.print`) atraves de um iframe
 *   isolado, aplicando regras CSS `@page` e `@media print` para renderizacao vetorial de alta nitidez.
 */

const MM_TO_PX = 96 / 25.4;

/**
 * Localiza uma linha de corte segura (espaco entre linhas de texto/tabela) proxima ao limite da pagina
 * para evitar cortar caracteres horizontalmente ao meio na virada de pagina.
 */
export function findSafeSliceHeight(
    canvas: HTMLCanvasElement,
    sourceY: number,
    maxSliceHeight: number,
): number {
    const remainingHeight = canvas.height - sourceY;

    if (remainingHeight <= maxSliceHeight) {
        return remainingHeight;
    }

    if (typeof canvas.getContext !== 'function') {
        return maxSliceHeight;
    }

    const ctx = canvas.getContext('2d');
    if (!ctx || typeof ctx.getImageData !== 'function') {
        return maxSliceHeight;
    }

    const searchRange = Math.max(16, Math.floor(maxSliceHeight * 0.2));
    const searchStartY = Math.max(sourceY + 1, sourceY + maxSliceHeight - searchRange);
    const bandHeight = sourceY + maxSliceHeight - searchStartY;

    if (bandHeight <= 4 || canvas.width <= 0) {
        return maxSliceHeight;
    }

    try {
        const imageData = ctx.getImageData(
            0,
            searchStartY,
            canvas.width,
            bandHeight,
        );
        const { data, width, height } = imageData;
        // Permite ate 1.5% de pixels escuros na linha (ex.: bordas verticais de 1px de tabelas)
        const maxDarkPixelsPerRow = Math.max(2, Math.floor(width * 0.015));

        const isRowClean = (rowIdx: number): boolean => {
            let darkCount = 0;
            const rowOffset = rowIdx * width * 4;

            for (let x = 0; x < width; x++) {
                const idx = rowOffset + x * 4;
                const r = data[idx] ?? 255;
                const g = data[idx + 1] ?? 255;
                const b = data[idx + 2] ?? 255;

                if (r < 185 || g < 185 || b < 185) {
                    darkCount++;
                    if (darkCount > maxDarkPixelsPerRow) {
                        return false;
                    }
                }
            }

            return true;
        };

        // Procura de baixo para cima por 3 linhas consecutivas limpas de texto
        for (let r = height - 2; r >= 2; r--) {
            if (isRowClean(r) && isRowClean(r - 1) && isRowClean(r - 2)) {
                return searchStartY - sourceY + r;
            }
        }
    } catch {
        return maxSliceHeight;
    }

    return maxSliceHeight;
}

export async function exportToPdf(
    element: HTMLElement,
    filename: string,
    config: DocumentConfig,
): Promise<void> {
    const { widthMm, minHeightMm } = getPaperDimensions(
        config.format,
        config.orientation,
    );
    const marginMm = MARGIN_VALUES_MM[config.margin];
    const contentWidthMm = Math.max(40, widthMm - 2 * marginMm);
    const contentHeightMm = Math.max(40, minHeightMm - 2 * marginMm);
    const contentWidthPx = Math.round(contentWidthMm * MM_TO_PX);

    const cleanFilename = filename.trim().toLowerCase().endsWith('.pdf')
        ? filename.trim()
        : `${filename.trim() || 'documento'}.pdf`;

    // Renderiza o elemento na largura fisica util exata da folha em alta resolucao (scale: 2)
    const canvas = await html2canvas(element, {
        scale: 2,
        useCORS: true,
        logging: false,
        backgroundColor: '#ffffff',
        windowWidth: Math.max(1200, contentWidthPx + 120),
        onclone: (_clonedDoc: Document, clonedElement: HTMLElement) => {
            // Remove estilizacao visual de card do preview e trava a largura util fisica da folha
            clonedElement.style.boxShadow = 'none';
            clonedElement.style.border = 'none';
            clonedElement.style.borderRadius = '0';
            clonedElement.style.backgroundColor = '#ffffff';
            clonedElement.style.width = `${contentWidthMm}mm`;
            clonedElement.style.maxWidth = `${contentWidthMm}mm`;
            clonedElement.style.minWidth = `${contentWidthMm}mm`;
            clonedElement.style.minHeight = 'auto';
            clonedElement.style.padding = '0';
            clonedElement.style.margin = '0';
            clonedElement.style.overflow = 'hidden';
            clonedElement.classList.remove(
                'rounded',
                'border',
                'border-neutral-200/80',
                'shadow-md',
                'transition-all',
                'duration-150',
                'dark:border-neutral-700/80',
            );

            if (clonedElement.parentElement) {
                clonedElement.parentElement.style.display = 'block';
                clonedElement.parentElement.style.width = `${contentWidthPx + 40}px`;
            }
        },
    });

    const pdf = new jsPDF({
        unit: 'mm',
        format: [widthMm, minHeightMm],
        orientation: config.orientation,
        compress: true,
    });

    const pxFullHeight = canvas.height;
    const pxPageHeight = Math.max(
        1,
        Math.floor(canvas.width * (contentHeightMm / contentWidthMm)),
    );

    let sourceY = 0;
    let page = 0;

    while (sourceY < pxFullHeight) {
        const currentSliceHeight = findSafeSliceHeight(
            canvas,
            sourceY,
            pxPageHeight,
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

        const sliceHeightMm =
            (currentSliceHeight / pxPageHeight) * contentHeightMm;
        const imgData = pageCanvas.toDataURL('image/jpeg', 0.98);

        if (page > 0) {
            pdf.addPage([widthMm, minHeightMm], config.orientation);
        }

        pdf.addImage(
            imgData,
            'JPEG',
            marginMm,
            marginMm,
            contentWidthMm,
            sliceHeightMm,
            undefined,
            'FAST',
        );

        sourceY += currentSliceHeight;
        page++;
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
