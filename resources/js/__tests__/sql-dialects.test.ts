import { describe, expect, it } from 'vitest';
import {
    DEFAULT_DIALECT,
    DIALECTS,
    highlighterOf,
    languageOf,
} from '@/tools/sql-formatter/lib/dialects';

/**
 * O mapa dos dialetos: o slug do glossario de um lado, os dois nomes que as
 * bibliotecas usam do outro.
 *
 * Os slugs sao contrato (SPEC secao 5, e o mesmo vocabulario do AI SQL Builder):
 * renomear um aqui sem renomear la faz "pgsql" querer dizer duas coisas. O que
 * se cobra, portanto, e a **lista** e a traducao - e nao o formato interno de
 * cada biblioteca.
 */

describe('dialetos', () => {
    it('sao os quatro do SPEC, na ordem do glossario', () => {
        expect([...DIALECTS]).toEqual(['mysql', 'pgsql', 'sqlsrv', 'sqlite']);
    });

    it('abre no MySQL', () => {
        expect(DEFAULT_DIALECT).toBe('mysql');
    });

    it('traduz cada slug para o nome da biblioteca de formatacao', () => {
        expect(DIALECTS.map((dialect) => languageOf(dialect))).toEqual([
            'mysql',
            'postgresql',
            // O SQL Server e o unico que muda de nome de verdade: o slug do
            // glossario e `sqlsrv`, e a biblioteca chama o dialeto de
            // `transactsql`.
            'transactsql',
            'sqlite',
        ]);
    });

    it('da um dialeto de realce a cada slug', () => {
        const realces = DIALECTS.map((dialect) => highlighterOf(dialect));

        // Quatro bancos: quatro objetos de dialeto, e nenhum vazio.
        expect(realces.every((dialect) => dialect !== undefined)).toBe(true);
        expect(new Set(realces).size).toBe(DIALECTS.length);
    });
});
