import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';
import { PALETTE_ALGORITHM_VERSION } from '@/lib/color';
import {
    payloadFromExtracted,
    payloadFromScale,
} from '@/lib/palettePayload';
import {
    importTokens,
    type ImportedPalette,
    type TokenImportDiagnostic,
    type TokenImportOptions,
    type TokenImportResult,
} from '@/tools/palette-generator/lib/tokenImport';
import type { TokenImportLimits } from '@/types/colors';

/**
 * Importacao validada de design tokens (F5, Etapa 6).
 *
 * Os fixtures sao a mesma fonte do teste Pest: o JSON de entrada e o payload
 * esperado (`expected/*.payload.json`) conferem o parser sem reimplementar a
 * resolucao em PHP. O restante cobre o que um fixture fixo nao alcanca -
 * limites, versoes, chaves proibidas e a forma de cada diagnostico.
 */

const FIXTURES = resolve(process.cwd(), 'tests/Fixtures/colors/tokens');

const LIMITS: TokenImportLimits = {
    maxBytes: 512 * 1024,
    maxTokens: 5000,
    maxDepth: 40,
    maxReferenceDepth: 10,
    maxDiagnostics: 20,
};

const SOURCE_LABEL = 'tokens.json';

function fixture(name: string): string {
    return readFileSync(resolve(FIXTURES, name), 'utf8');
}

function expected(name: string): Record<string, unknown> {
    return JSON.parse(
        readFileSync(resolve(FIXTURES, 'expected', name), 'utf8'),
    ) as Record<string, unknown>;
}

function run(
    text: string,
    overrides: Partial<TokenImportLimits> = {},
    sourceLabel = SOURCE_LABEL,
): TokenImportResult {
    const options: TokenImportOptions = {
        limits: { ...LIMITS, ...overrides },
        sourceLabel,
    };

    return importTokens(text, options);
}

function expectOk(result: TokenImportResult): ImportedPalette {
    if (!result.ok) {
        throw new Error(
            `importacao falhou: ${JSON.stringify(result.diagnostics)}`,
        );
    }

    return result.palette;
}

function codesOf(diagnostics: TokenImportDiagnostic[]): string[] {
    return diagnostics.map((diagnostic) => diagnostic.code);
}

function onlyDiagnostic(
    diagnostics: TokenImportDiagnostic[],
): TokenImportDiagnostic {
    expect(diagnostics).toHaveLength(1);

    return diagnostics[0] as TokenImportDiagnostic;
}

/**
 * Projecao comparavel ao `expected/*.payload.json`: so o que o payload do
 * servidor precisa (tipo, base, itens, papeis e contagem).
 */
function projection(palette: ImportedPalette): Record<string, unknown> {
    return {
        format: palette.format,
        kind: palette.kind,
        base: palette.base,
        clippedTones: palette.scale?.clippedTones ?? [],
        items:
            palette.scale !== null
                ? palette.scale.tones.map((tone) => ({
                      tone: tone.tone,
                      hex: tone.hex,
                  }))
                : (palette.extracted?.colors ?? []).map((color) => ({
                      hex: color.hex,
                  })),
        roles: palette.roles,
        tokenCount: palette.tokenCount,
    };
}

const DETAILS = {
    name: 'Importada',
    description: null,
    visibility: 'private' as const,
    tags: [],
};

const TONES = [
    50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950,
] as const;

function tonesObject(value = '#123456'): Record<string, string> {
    return Object.fromEntries(TONES.map((tone) => [String(tone), value]));
}

describe('fixtures — JSON nativo', () => {
    it('importa a escala completa com base, tons cortados e papeis', () => {
        const result = run(fixture('native-scale.json'));
        const palette = expectOk(result);

        expect(palette.name).toBe('aurora');
        expect(palette.scale?.algorithmVersion).toBe(
            PALETTE_ALGORITHM_VERSION,
        );
        expect(projection(palette)).toEqual(
            expected('native-scale.payload.json'),
        );
        expect(result.diagnostics).toEqual([]);
    });

    it('aceita o JSON legado sem base nem versao e cai no tom 500', () => {
        const result = run(fixture('native-legacy.json'));
        const palette = expectOk(result);

        expect(palette.roles).toBeNull();
        expect(projection(palette)).toEqual(
            expected('native-legacy.payload.json'),
        );
    });

    it('descarta o BOM antes do parse', () => {
        const result = run(fixture('bom-native.txt'));
        const palette = expectOk(result);

        expect(palette.format).toBe('native');
        expect(palette.kind).toBe('scale');
        expect(palette.base).toBe('#737373');
    });
});

describe('fixtures — W3C DTCG', () => {
    it('importa a escala com $type herdado e metadados do namespace', () => {
        const result = run(fixture('dtcg-scale.tokens.json'));
        const palette = expectOk(result);

        expect(palette.name).toBe('aurora');
        expect(projection(palette)).toEqual(
            expected('dtcg-scale.payload.json'),
        );

        // O token de texto dentro do grupo nao e cor e vira aviso.
        const warning = onlyDiagnostic(result.diagnostics);

        expect(warning).toMatchObject({
            code: 'non_color_ignored',
            severity: 'warning',
            path: 'color.aurora.note',
            params: { type: 'string' },
        });
    });

    it('resolve aliases em papeis, inclusive a forma .value', () => {
        const palette = expectOk(run(fixture('dtcg-aliases.tokens.json')));

        expect(projection(palette)).toEqual(
            expected('dtcg-aliases.payload.json'),
        );
    });

    it('rejeita ciclo de aliases com o caminho do token', () => {
        const result = run(fixture('dtcg-cycle.tokens.json'));

        expect(result.ok).toBe(false);
        expect(result.palette).toBeNull();

        const cycles = result.diagnostics.filter(
            (diagnostic) => diagnostic.code === 'reference_cycle',
        );

        expect(cycles.length).toBeGreaterThan(0);
        expect(cycles.every((entry) => entry.severity === 'error')).toBe(true);
        expect(cycles.map((entry) => entry.path)).toEqual(
            expect.arrayContaining(['color.cycled.a', 'color.cycled.b']),
        );
    });

    it('rejeita mais de um grupo com os 11 tons', () => {
        const result = run(fixture('dtcg-ambiguous.tokens.json'));

        expect(result.ok).toBe(false);

        const ambiguous = result.diagnostics.filter(
            (diagnostic) => diagnostic.code === 'ambiguous_palette',
        );

        expect(ambiguous.map((entry) => entry.path)).toEqual(
            expect.arrayContaining(['color.first', 'color.second']),
        );
    });
});

describe('fixtures — Style Dictionary', () => {
    it('importa a paleta extraida na ordem do documento', () => {
        const result = run(fixture('style-dictionary-extracted.json'));
        const palette = expectOk(result);

        expect(palette.extracted?.source).toEqual({
            kind: 'file',
            label: SOURCE_LABEL,
        });
        expect(palette.extracted?.sufficient).toBe(true);
        expect(palette.extracted?.visiblePixels).toBe(0);
        expect(
            palette.extracted?.colors.map((color) => color.weight),
        ).toEqual([0, 0, 0, 0, 0]);
        expect(
            palette.extracted?.colors.map((color) => color.percentage),
        ).toEqual([0, 0, 0, 0, 0]);
        expect(projection(palette)).toEqual(
            expected('style-dictionary-extracted.payload.json'),
        );
    });
});

describe('fixtures — arquivos invalidos', () => {
    it('aponta linha e coluna do JSON invalido', () => {
        const result = run(fixture('invalid-json.txt'));

        expect(result.ok).toBe(false);

        const failure = onlyDiagnostic(result.diagnostics);

        expect(failure.code).toBe('invalid_json');
        expect(failure.severity).toBe('error');
        expect(failure.params?.line).toBe(1);
        expect(typeof failure.params?.column).toBe('number');
    });
});

describe('formato e estrutura', () => {
    it('rejeita arquivo vazio', () => {
        expect(codesOf(run('   ').diagnostics)).toEqual(['empty_file']);
    });

    it('rejeita arquivo acima do teto antes de ler o JSON', () => {
        const result = run(fixture('native-legacy.json'), { maxBytes: 10 });

        expect(codesOf(result.diagnostics)).toEqual(['too_large']);
        expect(result.diagnostics[0]?.params).toEqual({ max: 10 });
    });

    it('rejeita raiz que nao e objeto', () => {
        expect(codesOf(run('[]').diagnostics)).toEqual(['root_not_object']);
    });

    it('rejeita documento sem colors nem color', () => {
        expect(codesOf(run('{"foo":{"bar":1}}').diagnostics)).toEqual([
            'unsupported_format',
        ]);
    });

    it('rejeita documento com colors e color ao mesmo tempo', () => {
        const text = JSON.stringify({
            colors: tonesObject(),
            color: { brand: { $value: '#123456' } },
        });

        expect(codesOf(run(text).diagnostics)).toEqual(['ambiguous_format']);
    });

    it('rejeita mistura de $value e value no mesmo documento', () => {
        const text = JSON.stringify({
            color: {
                pal: {
                    a: { $value: '#111111' },
                    b: { value: '#222222', type: 'color' },
                },
            },
        });

        expect(codesOf(run(text).diagnostics)).toEqual(['ambiguous_format']);
    });

    it('rejeita versao de formato desconhecida', () => {
        const text = JSON.stringify({
            formatVersion: 2,
            colors: tonesObject(),
        });
        const failure = onlyDiagnostic(run(text).diagnostics);

        expect(failure.code).toBe('unsupported_format_version');
        expect(failure.params).toEqual({ found: '2', expected: 1 });
    });

    it('avisa quando a versao do algoritmo diverge e regrava com a atual', () => {
        const text = JSON.stringify({
            formatVersion: 1,
            algorithmVersion: 99,
            colors: tonesObject(),
        });
        const result = run(text);
        const palette = expectOk(result);

        expect(palette.scale?.algorithmVersion).toBe(
            PALETTE_ALGORITHM_VERSION,
        );
        expect(onlyDiagnostic(result.diagnostics)).toMatchObject({
            code: 'algorithm_version_mismatch',
            severity: 'warning',
            params: { found: 99, expected: PALETTE_ALGORITHM_VERSION },
        });
    });

    it('rejeita escala sem todos os tons', () => {
        const colors = tonesObject();
        delete colors['950'];

        const failure = onlyDiagnostic(
            run(JSON.stringify({ colors })).diagnostics,
        );

        expect(failure.code).toBe('missing_tones');
        expect(failure.params?.missing).toContain('950');
    });

    it('rejeita contagem de cores extraida fora de 5/7/9/11', () => {
        const colors = [50, 100, 200, 300, 400, 500].reduce<
            Record<string, string>
        >((carry, index) => {
            carry[`cor${index}`] = '#123456';

            return carry;
        }, {});

        expect(codesOf(run(JSON.stringify({ colors })).diagnostics)).toEqual([
            'unsupported_color_count',
        ]);
    });

    it('rejeita cor que nao e HEX6/HEX8', () => {
        const colors = tonesObject();
        colors['500'] = 'rgb(1, 2, 3)';

        const failure = onlyDiagnostic(
            run(JSON.stringify({ colors })).diagnostics,
        );

        expect(failure.code).toBe('invalid_color');
        expect(failure.path).toBe('colors.500');
    });

    it('rejeita chave proibida na arvore', () => {
        // `JSON.stringify` nao serializa `__proto__` como chave propria; o
        // texto e montado a mao para o parser ver a chave.
        const raw =
            '{"color":{"pal":{"__proto__":{"$value":"#123456"},"a":{"$value":"#111111"}}}}';

        expect(codesOf(run(raw).diagnostics)).toContain('forbidden_key');
    });

    it('respeita profundidade e quantidade de nos', () => {
        const deep = { color: { a: { b: { c: { $value: '#123456' } } } } };

        expect(
            codesOf(run(JSON.stringify(deep), { maxDepth: 2 }).diagnostics),
        ).toContain('too_deep');

        const many = JSON.stringify({ colors: tonesObject() });

        expect(
            codesOf(run(many, { maxTokens: 3 }).diagnostics),
        ).toContain('too_many_tokens');
    });

    it('rejeita semanticas incompletas com aviso e mantem a paleta', () => {
        const text = JSON.stringify({
            color: {
                pal: {
                    $type: 'color',
                    ...Object.fromEntries(
                        TONES.map((tone) => [
                            String(tone),
                            { $value: '#123456' },
                        ]),
                    ),
                    semantic: {
                        light: {
                            primary: { $value: '#111111' },
                        },
                    },
                },
            },
        });
        const result = run(text);
        const palette = expectOk(result);

        expect(palette.roles).toBeNull();
        expect(onlyDiagnostic(result.diagnostics)).toMatchObject({
            code: 'semantic_incomplete',
            severity: 'warning',
        });
    });
});

describe('referencias', () => {
    function extractedWithAlias(
        alias: string,
        extras: Record<string, unknown> = {},
    ): string {
        return JSON.stringify({
            color: {
                pal: {
                    $type: 'color',
                    a: { $value: alias },
                    b: { $value: '#222222' },
                    c: { $value: '#333333' },
                    d: { $value: '#444444' },
                    e: { $value: '#555555' },
                },
            },
            ...extras,
        });
    }

    it('rejeita referencia desconhecida', () => {
        const result = run(extractedWithAlias('{color.pal.missing}'));
        const failure = result.diagnostics.find(
            (entry) => entry.code === 'unknown_reference',
        );

        expect(failure?.params).toEqual({ reference: 'color.pal.missing' });
    });

    it('rejeita referencia a token que nao e cor', () => {
        const result = run(
            extractedWithAlias('{typography.body}', {
                typography: {
                    body: { $type: 'fontFamily', $value: 'Inter' },
                },
            }),
        );

        expect(codesOf(result.diagnostics)).toContain(
            'reference_not_color',
        );
    });

    it('respeita o teto de encadeamento de aliases', () => {
        const text = JSON.stringify({
            color: {
                pal: {
                    $type: 'color',
                    a: { $value: '{color.pal.b}' },
                    b: { $value: '{color.pal.c}' },
                    c: { $value: '{color.pal.d}' },
                    d: { $value: '{color.pal.e}' },
                    e: { $value: '#555555' },
                },
            },
        });
        const result = run(text, { maxReferenceDepth: 2 });

        expect(codesOf(result.diagnostics)).toContain('reference_depth');
    });
});

describe('payload resultante', () => {
    it('a escala importada vira payload aceito por payloadFromScale', () => {
        const palette = expectOk(run(fixture('native-scale.json')));

        if (palette.scale === null) {
            throw new Error('fixture sem escala');
        }

        const payload = payloadFromScale(palette.scale, DETAILS);

        expect(payload.kind).toBe('scale');
        expect(payload.algorithm_version).toBe(PALETTE_ALGORITHM_VERSION);
        expect(payload.colors.items).toHaveLength(11);
        expect(payload.roles.light.primary).toMatch(/^#[0-9a-f]{6}$/);
    });

    it('a extracao importada vira payload aceito por payloadFromExtracted', () => {
        const palette = expectOk(
            run(fixture('style-dictionary-extracted.json')),
        );

        if (palette.extracted === null) {
            throw new Error('fixture sem extracao');
        }

        const payload = payloadFromExtracted(
            palette.extracted,
            palette.base,
            DETAILS,
        );

        expect(payload.kind).toBe('extracted');
        expect(payload.colors.base).toBe(palette.base);
        expect(payload.colors.items).toHaveLength(5);
    });

    it('e deterministico', () => {
        const first = run(fixture('dtcg-scale.tokens.json'));
        const second = run(fixture('dtcg-scale.tokens.json'));

        expect(first).toEqual(second);
    });
});

describe('limites de exibicao', () => {
    it('devolve todos os diagnosticos e deixa o corte para a tela', () => {
        // 12 grupos com contagem invalida: o parser nao corta nada.
        const color: Record<string, unknown> = {};

        for (let index = 0; index < 12; index += 1) {
            color[`group${index}`] = { a: { $value: '#123456' } };
        }

        const result = run(JSON.stringify({ color }), {
            maxDiagnostics: 2,
        });

        expect(result.ok).toBe(false);
        expect(result.diagnostics.length).toBeGreaterThan(2);
    });
});
