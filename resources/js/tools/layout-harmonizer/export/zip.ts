import { strToU8, zipSync } from 'fflate';
import {
    LAYOUT_CSS_FILENAME,
    LAYOUT_MARKDOWN_FILENAME,
    zipFileName,
} from './filename';
import { LAYOUT_EXPORT_ZIP_MIME } from './types';

/**
 * ZIP com o Markdown e o CSS (F5, ADR-175).
 *
 * Os dois arquivos saem do mesmo snapshot e levam nomes fixos dentro do
 * pacote. O `fflate` sem `mtime` usa `Date.now()`; aqui o timestamp e o nivel
 * sao fixos para o mesmo conteudo gerar os mesmos bytes.
 */

/** Timestamp fixo dos dois arquivos (2026-01-01, hora zero UTC). */
export const LAYOUT_ZIP_MTIME = Date.UTC(2026, 0, 1);

const ZIP_LEVEL = 6;

export interface LayoutZipFile {
    filename: string;
    mime: string;
    blob: Blob;
}

export function buildLayoutZip(
    markdown: string,
    css: string,
    name: string,
): LayoutZipFile {
    const data = zipSync(
        {
            [LAYOUT_MARKDOWN_FILENAME]: [
                strToU8(markdown),
                { mtime: LAYOUT_ZIP_MTIME },
            ],
            [LAYOUT_CSS_FILENAME]: [strToU8(css), { mtime: LAYOUT_ZIP_MTIME }],
        },
        { level: ZIP_LEVEL, mtime: LAYOUT_ZIP_MTIME },
    );

    return {
        filename: zipFileName(name),
        mime: LAYOUT_EXPORT_ZIP_MIME,
        blob: new Blob([data], { type: LAYOUT_EXPORT_ZIP_MIME }),
    };
}
