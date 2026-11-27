import { describe, expect, it } from 'vitest';
import { parseEnv } from '@/tools/env-example/lib/parse';
import type { EnvPairLine } from '@/tools/env-example/types';

/**
 * O parser do `.env`.
 *
 * O que se cobra e a leitura que a mascara usa: onde a chave termina, onde o
 * valor comeca, qual e a aspa, onde esta o comentario em linha e quantas linhas
 * o par ocupa. O resto - a linha crua - sai daqui intocado.
 */

function pares(source: string): EnvPairLine[] {
    return parseEnv(source).lines.filter(
        (line): line is EnvPairLine => line.kind === 'pair',
    );
}

describe('parseEnv', () => {
    it('classifica linhas e numera a partir de 1', () => {
        const doc = parseEnv('# app\n\nAPP_NAME=Demo\nSEM_IGUAL');

        expect(doc.lines.map((line) => line.kind)).toEqual([
            'comment',
            'blank',
            'pair',
            'invalid',
        ]);
        expect(doc.lines.map((line) => line.line)).toEqual([1, 2, 3, 4]);
    });

    it('separa chave e valor sem aspas', () => {
        const [pair] = pares('APP_NAME=Demo');

        expect(pair).toMatchObject({
            key: 'APP_NAME',
            lead: 'APP_NAME=',
            quote: '',
            value: 'Demo',
            comment: '',
            lines: 1,
            raw: 'APP_NAME=Demo',
        });
    });

    it('guarda o export e os espacos de antes da chave', () => {
        const [pair] = pares('  export APP_KEY = abc');

        expect(pair).toMatchObject({
            key: 'APP_KEY',
            lead: '  export APP_KEY = ',
            value: 'abc',
        });
    });

    it('aceita valor com aspas simples e duplas', () => {
        const [single, double] = pares('A=\'x y\'\nB="z w"');

        expect(single).toMatchObject({ quote: "'", value: 'x y' });
        expect(double).toMatchObject({ quote: '"', value: 'z w' });
    });

    it('separa o comentario em linha do valor sem aspas', () => {
        const [pair] = pares('DB_PASSWORD = abc # banco');

        expect(pair).toMatchObject({
            key: 'DB_PASSWORD',
            lead: 'DB_PASSWORD = ',
            value: 'abc',
            comment: ' # banco',
        });
    });

    it('nao trata # no meio do valor como comentario', () => {
        const [pair] = pares('AUTH_HASH=abc#def');

        expect(pair).toMatchObject({ value: 'abc#def', comment: '' });
    });

    it('guarda o comentario que segue a aspa de fechamento', () => {
        const [pair] = pares('APP_KEY="abc"  # segredo');

        expect(pair).toMatchObject({
            quote: '"',
            value: 'abc',
            comment: '  # segredo',
            lines: 1,
        });
    });

    it('le valor multi-linha entre aspas', () => {
        const [pair] = pares('PRIVATE_KEY="BEGIN\nmeio\nEND"\nNEXT=1');

        expect(pair).toMatchObject({
            line: 1,
            key: 'PRIVATE_KEY',
            quote: '"',
            value: 'BEGIN\nmeio\nEND',
            lines: 3,
            raw: 'PRIVATE_KEY="BEGIN\nmeio\nEND"',
        });
    });

    it('escapa a aspa dentro de aspas duplas', () => {
        const [pair] = pares('KEY="a\\"b"');

        expect(pair).toMatchObject({ value: 'a\\"b', comment: '' });
    });

    it('aceita CRLF sem deixar o retorno no valor', () => {
        const doc = parseEnv('A=1\r\nB=2\r\n');

        expect(doc.lines.map((line) => line.raw)).toEqual(['A=1', 'B=2', '']);
    });

    it('le chave vazia e chave so com comentario', () => {
        const [empty, commented] = pares('A=\nB=# nota');

        expect(empty).toMatchObject({ value: '', comment: '' });
        expect(commented).toMatchObject({ value: '', comment: '# nota' });
    });

    it('recusa linha sem igual', () => {
        const doc = parseEnv('SÓ_ISSO');

        expect(doc.lines[0]).toMatchObject({
            kind: 'invalid',
            line: 1,
            raw: 'SÓ_ISSO',
        });
    });

    it('recusa aspa sem par sem engolir as linhas seguintes', () => {
        const doc = parseEnv('A="sem fim\nB=2');

        expect(doc.lines.map((line) => line.kind)).toEqual(['invalid', 'pair']);
        expect(doc.lines[0]).toMatchObject({ kind: 'invalid', line: 1 });
    });

    it('recusa texto depois da aspa de fechamento', () => {
        const doc = parseEnv('A="x"lixo\nB=2');

        expect(doc.lines.map((line) => line.kind)).toEqual(['invalid', 'pair']);
    });

    it('recusa texto depois da aspa no fim de valor multi-linha', () => {
        const doc = parseEnv('A="x\ny"lixo\nB=2');

        expect(doc.lines.map((line) => line.kind)).toEqual(['invalid', 'pair']);
        expect(doc.lines[0]?.raw).toBe('A="x\ny"lixo');
    });
});
