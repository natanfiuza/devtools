import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

/**
 * O tema do editor so usa token do DESIGN.
 *
 * Duas travas, e as duas pegam erro que nao aparece na tela: cor literal faz o
 * editor parar de acompanhar o tema claro/escuro (e so da para ver trocando o
 * tema na mao), e `var()` de token que nao existe nao quebra nada - a cor
 * simplesmente nao pinta, e o texto sai com a cor herdada.
 */

const source = readFileSync(
    resolve(process.cwd(), 'resources/js/tools/json-viewer/editor/theme.ts'),
    'utf8',
);

const css = readFileSync(resolve(process.cwd(), 'resources/css/app.css'), 'utf8');

/** Os cinco tokens de sintaxe do DESIGN.md. */
const SYNTAX_TOKENS = [
    '--json-key',
    '--json-string',
    '--json-number',
    '--json-literal',
    '--json-punct',
];

function tokensIn(text: string): Set<string> {
    const tokens = new Set<string>();

    for (const match of text.matchAll(/var\((--[\w-]+)\)/g)) {
        if (match[1] !== undefined) {
            tokens.add(match[1]);
        }
    }

    return tokens;
}

describe('tema do editor', () => {
    it('pinta os cinco tokens de sintaxe', () => {
        for (const token of SYNTAX_TOKENS) {
            expect(source, `token ${token}`).toContain(`var(${token})`);
        }
    });

    it('nao tem cor literal', () => {
        expect(source).not.toMatch(/#[0-9a-f]{3,8}\b/i);
        expect(source).not.toMatch(/\b(?:rgb|rgba|hsl|hsla|oklch|lab|lch)\(/i);
    });

    it('so cita token que existe no app.css', () => {
        const used = tokensIn(source);

        expect(used.size).toBeGreaterThan(0);

        for (const token of used) {
            expect(css, `token ${token}`).toContain(`${token}:`);
        }
    });

    it('declara as cinco cores de sintaxe e mais nenhuma', () => {
        const syntax = [...tokensIn(source)].filter((token) => token.startsWith('--json-'));

        expect(new Set(syntax)).toEqual(new Set(SYNTAX_TOKENS));
    });
});
