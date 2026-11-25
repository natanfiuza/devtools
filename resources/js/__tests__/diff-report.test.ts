import { describe, expect, it } from 'vitest';
import { diffDocuments } from '@/tools/diff/lib/diff';
import { buildReport, type ReportLabels } from '@/tools/diff/lib/report';

/**
 * O relatorio de texto do diff.
 *
 * O relatorio e o que sai da tela (copiar/baixar): ele leva o diff inteiro no
 * dialeto do `diff` e do JSON Path, e os rotulos chegam traduzidos de fora -
 * aqui, um conjunto fixo que deixa o texto esperado legivel.
 */

const labels: ReportLabels = {
    title: 'DIFF',
    text: 'TEXTO',
    json: 'JSON',
    original: 'ORIGINAL',
    modified: 'MODIFICADO',
    added: 'ADIANTADAS',
    removed: 'REMOCAO',
    changed: 'ALTERADAS',
    noChanges: 'SEM_DIFERENCA',
    truncated: 'GROSSEIRO',
    invalid: 'JSON_INVALIDO',
    line: 'linha',
    column: 'coluna',
};

function textReport(left: string, right: string): string {
    return buildReport(diffDocuments({ left, right, mode: 'text' }), labels);
}

function jsonReport(left: string, right: string): string {
    return buildReport(diffDocuments({ left, right, mode: 'json' }), labels);
}

describe('relatorio do modo texto', () => {
    it('escreve cabecalho, resumo e as linhas com o simbolo do diff', () => {
        expect(textReport('a\nb\nc', 'a\nB\nc')).toBe(
            [
                'DIFF',
                'TEXTO: ORIGINAL → MODIFICADO',
                'ADIANTADAS: 1 · REMOCAO: 1',
                '@@ -2 +2 @@',
                '-b',
                '+B',
            ].join('\n'),
        );
    });

    it('anca cada hunk na linha certa dos dois lados', () => {
        const relatorio = textReport('a\nb\nc\nd', 'a\nX\nY\nZ\nd');

        expect(relatorio).toContain('@@ -2 +2 @@');
    });

    it('diz que nao ha diferenca quando os textos sao iguais', () => {
        expect(textReport('a\nb', 'a\nb')).toBe(
            [
                'DIFF',
                'TEXTO: ORIGINAL → MODIFICADO',
                'ADIANTADAS: 0 · REMOCAO: 0',
                'SEM_DIFERENCA',
            ].join('\n'),
        );
    });
});

describe('relatorio do modo JSON', () => {
    it('escreve um caminho por linha', () => {
        expect(jsonReport('{"a":1,"b":2}', '{"a":1,"b":3,"c":4}')).toBe(
            [
                'DIFF',
                'JSON: ORIGINAL → MODIFICADO',
                'ADIANTADAS: 1 · REMOCAO: 0 · ALTERADAS: 1',
                '~ $.b 2 → 3',
                '+ $.c 4',
            ].join('\n'),
        );
    });

    it('diz que nao ha diferenca quando o documento e o mesmo', () => {
        expect(jsonReport('{"a":1}', '{"a":1}')).toContain('SEM_DIFERENCA');
    });

    it('aponta o lado invalido e a posicao', () => {
        const relatorio = jsonReport('{\n  "a": 1\n  "b": 2\n}', '{"a":1}');

        expect(relatorio).toContain(
            'JSON_INVALIDO: ORIGINAL (linha 3, coluna ',
        );
    });

    it('aponta o lado direito invalido', () => {
        const relatorio = jsonReport('{"a":1}', '{"a":}');

        expect(relatorio).toContain('JSON_INVALIDO: MODIFICADO');
    });

    it('nao inventa posicao quando o motor nao deu nenhuma', () => {
        // "Unexpected end of JSON input" nao traz posicao; o relatorio fica
        // so com o lado, em vez de escrever linha/coluna falsas.
        const relatorio = buildReport(
            {
                mode: 'json',
                json: { ok: false, side: 'left', line: null, column: null },
            },
            labels,
        );

        expect(relatorio).toBe(
            [
                'DIFF',
                'JSON: ORIGINAL → MODIFICADO',
                'JSON_INVALIDO: ORIGINAL',
            ].join('\n'),
        );
    });
});

describe('relatorio do diff grosseiro', () => {
    it('avisa no fim quando o orcamento estourou', () => {
        const esquerda = Array.from({ length: 1001 }, (_, i) => `esq-${i}`);
        const direita = Array.from({ length: 1000 }, (_, i) => `dir-${i}`);
        const relatorio = textReport(esquerda.join('\n'), direita.join('\n'));

        expect(relatorio.endsWith('GROSSEIRO')).toBe(true);
    });
});
