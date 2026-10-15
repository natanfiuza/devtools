import { describe, expect, it } from 'vitest';
import {
    DEFAULT_KEYWORD_CASE,
    formatSql,
    KEYWORD_CASES,
    type FormatFailure,
    type FormatOptions,
    type FormatResult,
} from '@/tools/sql-formatter/lib/format';

/**
 * A formatacao: o `sql-formatter` por dentro, e um resultado proprio por fora.
 *
 * O que se cobra aqui e o **contrato** - a biblioteca nunca lanca para fora, o
 * erro vira linha e coluna, e as duas escolhas da tela chegam na biblioteca -,
 * e nao a indentacao exata de cada consulta: essa e da biblioteca, e fixa-la
 * linha a linha faria o teste quebrar a cada versao sem nada ter mudado na tela.
 * O que aparece escrito por inteiro sao as saidas curtas, que qualquer versao
 * formata igual.
 */

const PADRAO: FormatOptions = { dialect: 'mysql', keywordCase: DEFAULT_KEYWORD_CASE };

function formatar(text: string, options: Partial<FormatOptions> = {}): FormatResult {
    return formatSql(text, { ...PADRAO, ...options });
}

/** O texto da formatacao que deu certo; o teste que espera erro usa `falha`. */
function texto(result: FormatResult): string {
    if (!result.ok) {
        throw new Error(`esperava uma formatacao valida, veio ${result.message}`);
    }

    return result.text;
}

function falha(result: FormatResult): FormatFailure {
    if (result.ok) {
        throw new Error(`esperava um erro, veio ${JSON.stringify(result.text)}`);
    }

    return result;
}

describe('formatSql', () => {
    it('indenta com dois espacos, o fixo da tela', () => {
        expect(texto(formatar('select id from clientes'))).toBe('SELECT\n  id\nFROM\n  clientes');
    });

    it('sobe as palavras-chave por padrao', () => {
        expect(DEFAULT_KEYWORD_CASE).toBe('upper');
        expect(texto(formatar('select id from clientes'))).toContain('SELECT');
    });

    it('desce as palavras-chave quando pedido', () => {
        expect(texto(formatar('SELECT ID FROM CLIENTES', { keywordCase: 'lower' }))).toBe(
            'select\n  ID\nfrom\n  CLIENTES',
        );
    });

    it('mantem a caixa do autor quando pedido', () => {
        expect(texto(formatar('SeLeCt id FROM clientes', { keywordCase: 'preserve' }))).toBe(
            'SeLeCt\n  id\nFROM\n  clientes',
        );
    });

    it('oferece as tres caixas e nenhuma outra', () => {
        expect([...KEYWORD_CASES]).toEqual(['upper', 'lower', 'preserve']);
    });

    it('guarda os comentarios do autor', () => {
        expect(texto(formatar('-- quem comprou\nselect id from clientes'))).toContain(
            '-- quem comprou',
        );
    });
});

describe('o dialeto chega na biblioteca', () => {
    it('trata o mesmo texto de forma diferente em cada banco', () => {
        // `top` e palavra-chave so no SQL Server: nos outros tres e um
        // identificador qualquer, e a biblioteca deixa como esta.
        const consulta = 'select top 10 id from clientes';

        for (const dialect of ['mysql', 'pgsql', 'sqlite'] as const) {
            expect(texto(formatar(consulta, { dialect })), dialect).toContain('top 10');
        }

        expect(texto(formatar(consulta, { dialect: 'sqlsrv' }))).toContain('TOP 10');
    });

    it('aceita a aspa do MySQL onde ela e aspa', () => {
        const consulta = 'select `id` from `clientes`';

        expect(texto(formatar(consulta, { dialect: 'mysql' }))).toBe(
            'SELECT\n  `id`\nFROM\n  `clientes`',
        );
        // No PostgreSQL a crase nao abre identificador: a biblioteca recusa, e a
        // recusa chega na tela como erro com posicao - e nao como excecao.
        expect(falha(formatar(consulta, { dialect: 'pgsql' })).line).toBe(1);
    });
});

describe('o que nao e SQL', () => {
    it('devolve a posicao do erro em vez de lancar', () => {
        const erro = falha(formatar("select 'abc"));

        // A biblioteca conta a coluna a partir de 1, e o `'` e o oitavo
        // caractere do texto.
        expect(erro.line).toBe(1);
        expect(erro.column).toBe(8);
        expect(erro.message).toBe(`Parse error: Unexpected "'abc" at line 1 column 8.`);
    });

    it('aponta o fim do texto quando o que falta esta la', () => {
        const erro = falha(formatar('select id from clientes where (ativo = 1'));

        expect(erro.line).toBe(1);
        expect(erro.column).toBe(41);
        expect(erro.message).toBe('Parse error at token: «EOF» at line 1 column 41');
    });

    it('conta a coluna com o BOM, como o editor conta', () => {
        // A biblioteca ve o BOM como um caractere a mais, e o editor tambem:
        // sem essa coincidencia a marca do editor cairia um caractere antes.
        const erro = falha(formatar(`${String.fromCharCode(0xfeff)}select 'abc`));

        expect(erro.line).toBe(1);
        expect(erro.column).toBe(9);
    });

    it('guarda so a primeira linha da mensagem', () => {
        // A segunda forma de erro traz a gramatica inteira do motor depois da
        // primeira linha - dezenas de linhas que nao cabem em lugar nenhum.
        const erro = falha(formatar('select id from clientes) '));

        expect(erro.message).not.toContain('\n');
        expect(erro.message.startsWith('Parse error')).toBe(true);
    });

    it('nao inventa posicao para o texto vazio', () => {
        expect(texto(formatar(''))).toBe('');
    });

    it('deixa passar o texto que nao e SQL nenhum', () => {
        // Comportamento da biblioteca, fixado porque muda o que a tela promete:
        // prosa nao e recusada, e sim "formatada". O aviso de entrada invalida
        // so aparece quando o motor **nao entende** o que leu, e uma frase solta
        // ele entende como uma consulta de uma palavra.
        expect(texto(formatar('isso aqui nao e sql'))).toBe('isso aqui nao e SQL');
    });
});
