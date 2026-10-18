import { describe, expect, it } from 'vitest';
import {
    acceptsFile,
    acceptAttribute,
    readTextFile,
    type FileRules,
} from '@/lib/file';

/**
 * A leitura do arquivo escolhido, antes de qualquer tool decidir o que fazer
 * com o texto.
 *
 * As regras chegam de fora, porque cada tool aceita um conjunto - o que este
 * modulo decide e a **ordem** das checagens e o motivo que volta em cada
 * recusa: o recado na tela precisa dizer o que de fato barrou o arquivo.
 */

const REGRAS: FileRules = {
    maxBytes: 1024,
    extensions: ['.json', '.txt'],
    types: ['application/json', 'text/plain'],
};

/** Um arquivo com tamanho previsivel: um caractere ASCII e um byte. */
function arquivo(name: string, content: string, type = ''): File {
    return new File([content], name, { type });
}

describe('acceptsFile', () => {
    it('aceita pela extensao', () => {
        expect(acceptsFile(arquivo('dados.json', '{}'), REGRAS)).toBe(true);
        expect(acceptsFile(arquivo('NOTAS.TXT', 'oi'), REGRAS)).toBe(true);
    });

    it('aceita pelo tipo quando a extensao nao ajuda', () => {
        expect(
            acceptsFile(
                arquivo('consulta', 'select 1', 'application/json'),
                REGRAS,
            ),
        ).toBe(true);
    });

    it('aceita .sql sem tipo informado', () => {
        // O navegador manda `type` vazio para muita coisa: exigir o MIME
        // recusaria arquivo legitimo.
        const regras: FileRules = { ...REGRAS, extensions: ['.sql'] };

        expect(acceptsFile(arquivo('schema.sql', 'select 1'), regras)).toBe(
            true,
        );
    });

    it('recusa o que nao e nem extensao nem tipo', () => {
        expect(
            acceptsFile(arquivo('nota.csv', 'a;b', 'text/csv'), REGRAS),
        ).toBe(false);
    });
});

describe('readTextFile', () => {
    it('devolve o texto do arquivo aceito', async () => {
        const result = await readTextFile(
            arquivo('dados.json', '{"a":1}'),
            REGRAS,
        );

        expect(result).toEqual({ ok: true, text: '{"a":1}' });
    });

    it('deixa passar o arquivo exatamente no limite', async () => {
        const result = await readTextFile(
            arquivo('dados.txt', 'a'.repeat(1024)),
            REGRAS,
        );

        expect(result.ok).toBe(true);
    });

    it('recusa pelo tamanho antes de olhar o tipo', async () => {
        // O arquivo e grande **e** de outro formato: quem barrou foi o tamanho,
        // e o recado precisa citar o tamanho.
        const grande = arquivo('planilha.csv', 'a'.repeat(2048), 'text/csv');
        const result = await readTextFile(grande, REGRAS);

        expect(result).toEqual({
            ok: false,
            kind: 'file_too_large',
            size: 2048,
        });
    });

    it('recusa pelo tipo', async () => {
        const result = await readTextFile(
            arquivo('nota.csv', 'a;b', 'text/csv'),
            REGRAS,
        );

        expect(result).toEqual({ ok: false, kind: 'file_type', size: 3 });
    });

    it('recusa o arquivo que o navegador nao conseguiu ler', async () => {
        // Nao da para produzir um arquivo ilegivel de verdade no jsdom: um
        // objeto que nao e `Blob` derruba a leitura, que e o caminho do erro.
        const quebrado = {
            name: 'dados.json',
            size: 4,
            type: '',
        } as unknown as File;
        const result = await readTextFile(quebrado, REGRAS);

        expect(result).toEqual({ ok: false, kind: 'file_unreadable', size: 4 });
    });
});

describe('acceptAttribute', () => {
    it('junta extensoes e tipos, na ordem', () => {
        expect(acceptAttribute(REGRAS)).toBe(
            '.json,.txt,application/json,text/plain',
        );
    });
});
