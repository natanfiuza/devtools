import { describe, expect, it } from 'vitest';
import { strFromU8, unzipSync } from 'fflate';
import {
    markdownFileName,
    sanitizeExportName,
    zipFileName,
} from '@/tools/layout-harmonizer/export/filename';
import { buildLayoutZip } from '@/tools/layout-harmonizer/export/zip';

/**
 * ZIP exportado (F5, ADR-175).
 *
 * Dois arquivos com nomes fixos, UTF-8 preservado e bytes deterministicos:
 * o `mtime` e o nivel de compressao sao fixos, entao a mesma entrada gera o
 * mesmo pacote dentro do mesmo processo.
 */

const MARKDOWN = '# Título — ação\n\nTexto com acentuação.\n';
const CSS = ':root { --lh-primary: #3b82f6; }\n';

async function unzippedOf(name = 'Sistema Ação'): Promise<{
    files: Record<string, string>;
    names: string[];
    bytes: Uint8Array;
    filename: string;
    mime: string;
}> {
    const zip = buildLayoutZip(MARKDOWN, CSS, name);
    const bytes = new Uint8Array(await zip.blob.arrayBuffer());
    const files = unzipSync(bytes);
    const names = Object.keys(files).sort();

    return {
        files: Object.fromEntries(
            Object.entries(files).map(([path, data]) => [
                path,
                strFromU8(data),
            ]),
        ),
        names,
        bytes,
        filename: zip.filename,
        mime: zip.mime,
    };
}

describe('export/filename', () => {
    it('sanitiza o nome do ZIP e tem fallback', () => {
        expect(sanitizeExportName('Sistema Ação */')).toBe('sistema-acao');
        expect(sanitizeExportName('***')).toBe('layout-harmonizer');
        expect(sanitizeExportName(`${'a'.repeat(40)}`)).toHaveLength(32);
        expect(zipFileName('Sistema Ação')).toBe('sistema-acao-layout.zip');
    });

    it('nomeia o Markdown avulso pelo idioma', () => {
        expect(markdownFileName('pt_BR')).toBe('design-instructions.pt-BR.md');
        expect(markdownFileName('en')).toBe('design-instructions.en.md');
    });
});

describe('export/zip', () => {
    it('empacota o Markdown e o CSS com nomes fixos', async () => {
        const { names } = await unzippedOf();

        expect(names).toEqual(['colors.css', 'design-instructions.md']);
    });

    it('preserva UTF-8 e o conteudo exato dos arquivos', async () => {
        const { files } = await unzippedOf();

        expect(files['design-instructions.md']).toBe(MARKDOWN);
        expect(files['colors.css']).toBe(CSS);
    });

    it('nomeia o pacote pelo systema e usa o tipo ZIP', async () => {
        const { filename, mime } = await unzippedOf();

        expect(filename).toBe('sistema-acao-layout.zip');
        expect(mime).toBe('application/zip;charset=utf-8');
    });

    it('gera os mesmos bytes para a mesma entrada', async () => {
        const first = await unzippedOf();
        const second = await unzippedOf();

        expect(Array.from(first.bytes)).toEqual(Array.from(second.bytes));
    });
});
