import { describe, expect, it } from 'vitest';
import {
    PALETTE_ALGORITHM_VERSION,
    PALETTE_TONES,
    buildPaletteScale,
    parseColor,
    type Rgba,
} from '@/lib/color';
import { PALETTE_ROLES, PALETTE_THEMES } from '@/lib/palettePayload';
import {
    COLOR_TOKEN_FORMAT_VERSION,
    buildDtcgTokens,
    buildStyleDictionaryTokens,
    buildTypeScriptTokens,
    gamutNote,
    tokenIdentifier,
} from '@/tools/palette-generator/lib/tokenExporters';
import type { PaletteRoleColors, PaletteRoles } from '@/types/colors';

/**
 * Exportadores de design tokens (F5, ADR-095).
 *
 * O arquivo baixado e contrato publico: os testes conferem o caminho de cada
 * token, a versao do formato, o alpha em HEX8 e a documentacao de gamut. A
 * semantica so existe quando os papeis do editor sao passados.
 */

function colorOf(input: string): Rgba {
    const parsed = parseColor(input);

    if (!parsed.ok) {
        throw new Error(`cor de teste invalida: ${input}`);
    }

    return parsed.color.rgba;
}

const SCALE = buildPaletteScale(colorOf('#6366f1'));
const ALPHA_SCALE = buildPaletteScale({ ...colorOf('#6366f1'), a: 0.5 });
const CLIPPED_SCALE = buildPaletteScale(colorOf('#00ff00'));
const GRAY_SCALE = buildPaletteScale(colorOf('#808080'));

const LIGHT_ROLES: PaletteRoleColors = {
    primary: '#332211',
    secondary: '#443322',
    accent: '#22aa6680',
    background: '#fefefe',
    surface: '#f5f5f5',
    text: '#111111',
    text_muted: '#555555',
    border: '#dddddd',
};

const DARK_ROLES: PaletteRoleColors = {
    primary: '#aabbcc',
    secondary: '#99aabb',
    accent: '#8899aa',
    background: '#0a0a0a',
    surface: '#151515',
    text: '#fafafa',
    text_muted: '#a0a0a0',
    border: '#333333',
};

const ROLES: PaletteRoles = { light: LIGHT_ROLES, dark: DARK_ROLES };

type DtcgToken = { $type: string; $value: string };

interface DtcgGroup {
    [key: string]: DtcgToken | DtcgGroup;
}

interface DtcgDocument {
    $description: string;
    $extensions: {
        'devtools.colors': {
            formatVersion: number;
            algorithmVersion: number;
            name: string;
            base: string;
            clippedTones: number[];
            gamut: string;
        };
    };
    color: Record<string, DtcgGroup>;
}

interface StyleDictionaryDocument {
    color: Record<
        string,
        Record<string, { value: string; type: string } | unknown>
    >;
}

function documentOf(contents: string): DtcgDocument {
    return JSON.parse(contents) as DtcgDocument;
}

describe('buildDtcgTokens', () => {
    it('monta color.{slug}.{tom} com $type e $value na ordem da escala', () => {
        const document = documentOf(buildDtcgTokens(SCALE, 'marca'));
        const group = document.color.marca;

        if (group === undefined) {
            throw new Error('grupo da paleta ausente');
        }

        expect(Object.keys(group)).toEqual(PALETTE_TONES.map(String));

        for (const tone of SCALE.tones) {
            expect(group[String(tone.tone)]).toEqual({
                $type: 'color',
                $value: tone.hex,
            });
        }

        expect(buildDtcgTokens(SCALE, 'marca').endsWith('\n')).toBe(true);
    });

    it('registra a versao do formato, o algoritmo, a base e o gamut', () => {
        const document = documentOf(buildDtcgTokens(SCALE, 'marca'));
        const meta = document.$extensions['devtools.colors'];

        expect(meta.formatVersion).toBe(COLOR_TOKEN_FORMAT_VERSION);
        expect(meta.algorithmVersion).toBe(PALETTE_ALGORITHM_VERSION);
        expect(meta.name).toBe('marca');
        expect(meta.base).toBe('#6366f1');
        expect(meta.clippedTones).toEqual(SCALE.clippedTones);
        expect(meta.gamut).toBe(gamutNote(SCALE));
        expect(document.$description).toContain(
            `tokens v${COLOR_TOKEN_FORMAT_VERSION}`,
        );
    });

    it('so tem semanticas quando recebe os papeis do editor', () => {
        const withoutRoles = documentOf(buildDtcgTokens(SCALE, 'marca'));
        const group = withoutRoles.color.marca;

        if (group === undefined) {
            throw new Error('grupo da paleta ausente');
        }

        expect(group.semantic).toBeUndefined();

        const withRoles = documentOf(buildDtcgTokens(SCALE, 'marca', ROLES));
        const semantic = withRoles.color.marca?.semantic as DtcgGroup;

        expect(Object.keys(semantic)).toEqual([...PALETTE_THEMES]);
        expect(Object.keys(semantic.light as DtcgGroup)).toEqual([
            ...PALETTE_ROLES,
        ]);
        expect((semantic.light as DtcgGroup).primary).toEqual({
            $type: 'color',
            $value: LIGHT_ROLES.primary,
        });
        expect((semantic.dark as DtcgGroup).border).toEqual({
            $type: 'color',
            $value: DARK_ROLES.border,
        });
    });

    it('preserva alpha em HEX8', () => {
        const document = documentOf(buildDtcgTokens(ALPHA_SCALE, 'vidro'));
        const tone = document.color.vidro?.['50'] as DtcgToken;

        expect(tone.$value).toMatch(/^#[0-9a-f]{8}$/);
        expect(tone.$value).toBe(ALPHA_SCALE.tones[0]?.hex);

        const withRoles = documentOf(
            buildDtcgTokens(SCALE, 'marca', ROLES),
        );
        const semantic = withRoles.color.marca?.semantic as DtcgGroup;
        const accent = (semantic.light as DtcgGroup).accent as DtcgToken;

        expect(accent.$value).toBe('#22aa6680');
    });

    it('e deterministico', () => {
        expect(buildDtcgTokens(SCALE, 'marca', ROLES)).toBe(
            buildDtcgTokens(SCALE, 'marca', ROLES),
        );
    });
});

describe('buildStyleDictionaryTokens', () => {
    it('usa a mesma arvore com value e type', () => {
        const document = JSON.parse(
            buildStyleDictionaryTokens(SCALE, 'marca', ROLES),
        ) as StyleDictionaryDocument;
        const group = document.color.marca;
        const tone = SCALE.tones.find((item) => item.tone === 500);

        if (group === undefined || tone === undefined) {
            throw new Error('grupo ou tom ausente');
        }

        expect(group['500']).toEqual({ value: tone.hex, type: 'color' });
        expect(group.semantic).toEqual({
            light: expect.objectContaining({
                primary: { value: LIGHT_ROLES.primary, type: 'color' },
            }),
            dark: expect.objectContaining({
                border: { value: DARK_ROLES.border, type: 'color' },
            }),
        });
    });

    it('nao carrega metadados DTCG nem semanticas sem papeis', () => {
        const contents = buildStyleDictionaryTokens(SCALE, 'marca');
        const document = JSON.parse(contents) as Record<string, unknown>;
        const group = (document.color as Record<string, unknown>).marca as
            | Record<string, unknown>
            | undefined;

        expect(document).not.toHaveProperty('$extensions');
        expect(contents).not.toContain('$type');
        expect(group?.semantic).toBeUndefined();

        for (const tone of SCALE.tones) {
            expect(group?.[String(tone.tone)]).toEqual({
                value: tone.hex,
                type: 'color',
            });
        }
    });
});

describe('buildTypeScriptTokens', () => {
    it('gera um objeto as const com primitivas e semanticas', () => {
        const contents = buildTypeScriptTokens(SCALE, 'minha-marca', ROLES);

        expect(contents).toContain('export const minhaMarcaTokens = {');
        expect(contents).toContain('    color: {');
        expect(contents).toContain(`        50: '${SCALE.tones[0]?.hex}',`);
        expect(contents).toContain(`                primary: '${LIGHT_ROLES.primary}',`);
        expect(contents).toContain(`                accent: '${LIGHT_ROLES.accent}',`);
        expect(contents).toContain('} as const;');
        expect(contents).toContain(
            `Tokens format v${COLOR_TOKEN_FORMAT_VERSION}`,
        );
        expect(contents.endsWith('\n')).toBe(true);
    });

    it('sem papeis nao inclui o grupo semantico', () => {
        const contents = buildTypeScriptTokens(SCALE, 'marca');

        expect(contents).not.toContain('semantic:');
        expect(contents).toContain(`export const marcaTokens = {`);
    });

    it('documenta o gamut quando o croma foi cortado', () => {
        const contents = buildTypeScriptTokens(CLIPPED_SCALE, 'lima');

        expect(CLIPPED_SCALE.clippedTones.length).toBeGreaterThan(0);
        expect(contents).toContain('chroma reduced to fit the sRGB gamut');
        expect(contents).toContain(CLIPPED_SCALE.clippedTones.join(', '));

        expect(gamutNote(GRAY_SCALE)).toContain('no chroma clipping');
        expect(gamutNote(CLIPPED_SCALE)).not.toContain('no chroma clipping');
    });
});

describe('tokenIdentifier', () => {
    it('converte o slug em identificador camelCase', () => {
        expect(tokenIdentifier('primary')).toBe('primaryTokens');
        expect(tokenIdentifier('minha-marca')).toBe('minhaMarcaTokens');
        expect(tokenIdentifier('cores-da-marca')).toBe('coresDaMarcaTokens');
    });

    it('prefixa quando o slug comeca com digito', () => {
        expect(tokenIdentifier('3d-cores')).toBe('palette3dCoresTokens');
    });
});
