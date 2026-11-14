import { byteLength } from '@/lib/bytes';
import {
    PALETTE_ALGORITHM_VERSION,
    PALETTE_TONES,
    parseColor,
    type PaletteScale,
} from '@/lib/color';
import { buildFrozenScale } from '@/lib/frozenScale';
import { analyzeJson } from '@/lib/json/parse';
import { PALETTE_ROLES, PALETTE_THEMES } from '@/lib/palettePayload';
import type {
    PaletteRole,
    PaletteRoleColors,
    PaletteRoles,
    PaletteTheme,
    TokenImportLimits,
} from '@/types/colors';
import {
    EXTRACTION_ALGORITHM_VERSION,
    EXTRACTION_COUNTS,
    type ExtractedColor,
    type ExtractionCount,
} from './extractContract';
import type { ExtractedPalette } from './extractedPalette';
import { COLOR_TOKEN_FORMAT_VERSION } from './tokenExporters';

/**
 * Importacao validada de design tokens (F5, Etapa 6).
 *
 * Le JSON nativo da ferramenta, W3C DTCG (Tokens Studio / Figma Variables) e
 * Style Dictionary. Nada e executado: o unico parser e o `JSON.parse` do motor,
 * via `analyzeJson` (que tambem descarta BOM e calcula linha/coluna). Aliases
 * `{caminho}` sao resolvidos como dados, com deteccao de ciclo.
 *
 * Puro e deterministico: sem Vue, DOM, i18n, rede ou relogio. As mensagens sao
 * **codigos internos**; a tela escolhe o texto. O parser devolve todos os
 * diagnosticos e o limite de exibicao fica com quem mostra.
 *
 * A validacao vem antes de qualquer mutacao de estado: enquanto houver erro,
 * `palette` e `null` e o chamador mantem a paleta atual.
 */

export const TOKEN_IMPORT_ERROR_CODES = [
    'empty_file',
    'too_large',
    'invalid_json',
    'root_not_object',
    'unsupported_format',
    'ambiguous_format',
    'unsupported_format_version',
    'too_deep',
    'too_many_tokens',
    'forbidden_key',
    'mixed_token_styles',
    'invalid_structure',
    'no_colors',
    'ambiguous_palette',
    'unsupported_color_count',
    'missing_tones',
    'invalid_color',
    'unknown_reference',
    'reference_not_color',
    'reference_cycle',
    'reference_depth',
] as const;

export const TOKEN_IMPORT_WARNING_CODES = [
    'algorithm_version_mismatch',
    'non_color_ignored',
    'semantic_incomplete',
    'extra_colors_ignored',
] as const;

export type TokenImportErrorCode = (typeof TOKEN_IMPORT_ERROR_CODES)[number];

export type TokenImportWarningCode =
    (typeof TOKEN_IMPORT_WARNING_CODES)[number];

export type TokenImportDiagnosticCode =
    | TokenImportErrorCode
    | TokenImportWarningCode;

export type TokenImportFormat = 'native' | 'dtcg' | 'style-dictionary';

export type TokenImportKind = 'scale' | 'extracted';

export interface TokenImportDiagnostic {
    code: TokenImportDiagnosticCode;
    severity: 'error' | 'warning';
    /** Caminho no documento (pontos), quando aplicavel. */
    path?: string;
    /** Valores para interpolar no texto de i18n. */
    params?: Record<string, string | number>;
}

export interface ImportedPalette {
    format: TokenImportFormat;
    kind: TokenImportKind;
    base: string;
    scale: PaletteScale | null;
    extracted: ExtractedPalette | null;
    /** `null` quando o bloco semantico nao veio completo. */
    roles: PaletteRoles | null;
    /** Tokens de cor encontrados no documento (nao so na paleta). */
    tokenCount: number;
    name: string | null;
}

export type TokenImportResult =
    | {
          ok: true;
          palette: ImportedPalette;
          diagnostics: TokenImportDiagnostic[];
      }
    | {
          ok: false;
          palette: null;
          diagnostics: TokenImportDiagnostic[];
      };

export interface TokenImportOptions {
    limits: TokenImportLimits;
    /** Nome do arquivo, ja truncado pelo limite do servidor. */
    sourceLabel: string;
}

const HEX = /^#(?:[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/;

const TONE_NAMES = new Set<string>(PALETTE_TONES.map(String));

const EXTRACTED_COUNTS: readonly number[] = EXTRACTION_COUNTS;

/** Metadados de grupo do DTCG; nunca viram segmento de caminho. */
const METADATA_KEYS = new Set(['$description', '$extensions', '$type']);

const FORBIDDEN_KEYS = new Set(['__proto__', 'constructor', 'prototype']);

const COLOR_TYPE = 'color';

type JsonRecord = Record<string, unknown>;

interface LeafRecord {
    path: string[];
    /** Tipo declarado (herdado do grupo ou do proprio token). */
    type: string | null;
    value: unknown;
}

interface GroupRecord {
    path: string[];
    /** Filhos diretos que sao tokens de cor (aliases inclusos). */
    leaves: LeafRecord[];
}

interface Metadata {
    algorithmVersion: number | null;
    base: string | null;
    clippedTones: number[];
    name: string | null;
}

interface Context {
    limits: TokenImportLimits;
    diagnostics: TokenImportDiagnostic[];
}

function isRecord(value: unknown): value is JsonRecord {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function own(record: JsonRecord, key: string): boolean {
    return Object.prototype.hasOwnProperty.call(record, key);
}

function pathKey(path: string[]): string {
    return path.join('.');
}

function pathOf(path: string[]): string | undefined {
    return path.length === 0 ? undefined : path.join('.');
}

function error(
    context: Context,
    code: TokenImportErrorCode,
    path?: string,
    params?: Record<string, string | number>,
): void {
    context.diagnostics.push({
        code,
        severity: 'error',
        ...(path === undefined ? {} : { path }),
        ...(params === undefined ? {} : { params }),
    });
}

function warn(
    context: Context,
    code: TokenImportWarningCode,
    path?: string,
    params?: Record<string, string | number>,
): void {
    context.diagnostics.push({
        code,
        severity: 'warning',
        ...(path === undefined ? {} : { path }),
        ...(params === undefined ? {} : { params }),
    });
}

function hasErrors(diagnostics: TokenImportDiagnostic[]): boolean {
    return diagnostics.some((entry) => entry.severity === 'error');
}

function failure(context: Context): TokenImportResult {
    return { ok: false, palette: null, diagnostics: context.diagnostics };
}

function numberOrNull(value: unknown): number | null {
    return typeof value === 'number' && Number.isFinite(value) ? value : null;
}

function validHexOrNull(value: unknown): string | null {
    return typeof value === 'string' && HEX.test(value) ? value : null;
}

function clippedTonesOf(value: unknown): number[] {
    if (!Array.isArray(value)) {
        return [];
    }

    const seen = new Set<number>();

    for (const entry of value) {
        if (typeof entry === 'number' && TONE_NAMES.has(String(entry))) {
            seen.add(entry);
        }
    }

    return PALETTE_TONES.filter((tone) => seen.has(tone));
}

function emptyMetadata(): Metadata {
    return {
        algorithmVersion: null,
        base: null,
        clippedTones: [],
        name: null,
    };
}

/**
 * Metadados sao sempre opcionais: arquivo de terceiro nao tem nenhum deles e
 * continua importavel. `formatVersion` so e validado quando esta presente.
 */
function metadataFrom(
    record: JsonRecord,
    context: Context,
): Metadata | null {
    if (
        own(record, 'formatVersion') &&
        record.formatVersion !== COLOR_TOKEN_FORMAT_VERSION
    ) {
        error(context, 'unsupported_format_version', undefined, {
            found: String(record.formatVersion),
            expected: COLOR_TOKEN_FORMAT_VERSION,
        });

        return null;
    }

    return {
        algorithmVersion: numberOrNull(record.algorithmVersion),
        base: validHexOrNull(record.base),
        clippedTones: clippedTonesOf(record.clippedTones),
        name: typeof record.name === 'string' ? record.name : null,
    };
}

function metadataOf(
    root: JsonRecord,
    format: TokenImportFormat,
    context: Context,
): Metadata | null {
    if (format === 'style-dictionary') {
        return emptyMetadata();
    }

    if (format === 'native') {
        return metadataFrom(root, context);
    }

    const extensions = root.$extensions;

    if (!isRecord(extensions)) {
        return emptyMetadata();
    }

    const namespace = extensions['devtools.colors'];

    return isRecord(namespace) ? metadataFrom(namespace, context) : emptyMetadata();
}

/** Aliases aceitam `{a.b.c}` com espacos internos nas bordas. */
function aliasTarget(value: string): string | null {
    const match = /^\{\s*([^{}]+?)\s*\}$/.exec(value.trim());

    return match === null ? null : (match[1] ?? null);
}

/**
 * Resolucao de alias iterativa. O mapa guarda todos os tokens do documento,
 * inclusive os que nao sao cor, para distinguir `unknown_reference` de
 * `reference_not_color`.
 */
function resolveLeafHex(
    start: LeafRecord,
    leaves: Map<string, LeafRecord>,
    context: Context,
    reportErrors: boolean,
): string | null {
    const at = pathOf(start.path);

    let current = start;
    let depth = 0;
    const seen = new Set<string>([pathKey(start.path)]);

    for (;;) {
        const value = current.value;

        if (typeof value !== 'string') {
            if (reportErrors) {
                error(context, 'invalid_color', at);
            }

            return null;
        }

        const reference = aliasTarget(value);

        if (reference === null) {
            if (!HEX.test(value)) {
                if (reportErrors) {
                    error(context, 'invalid_color', at);
                }

                return null;
            }

            return value;
        }

        if (depth >= context.limits.maxReferenceDepth) {
            if (reportErrors) {
                error(context, 'reference_depth', at, {
                    max: context.limits.maxReferenceDepth,
                });
            }

            return null;
        }

        depth += 1;

        if (seen.has(reference)) {
            if (reportErrors) {
                error(context, 'reference_cycle', at, { reference });
            }

            return null;
        }

        seen.add(reference);

        const target =
            leaves.get(reference) ??
            (reference.endsWith('.value')
                ? leaves.get(reference.slice(0, -'.value'.length))
                : undefined) ??
            (reference.endsWith('.$value')
                ? leaves.get(reference.slice(0, -'.$value'.length))
                : undefined);

        if (target === undefined) {
            if (reportErrors) {
                error(context, 'unknown_reference', at, { reference });
            }

            return null;
        }

        if (target.type !== null && target.type !== COLOR_TYPE) {
            if (reportErrors) {
                error(context, 'reference_not_color', at, { reference });
            }

            return null;
        }

        current = target;
    }
}

interface Traversal {
    groups: Map<string, GroupRecord>;
    leaves: Map<string, LeafRecord>;
    colorTokenCount: number;
    dtcgLeaves: number;
    sdLeaves: number;
}

/**
 * Travessia iterativa (pilha) do documento inteiro.
 *
 * Chaves proibidas nunca sao lidas como dado; profundidade e quantidade de
 * nos sao limitadas pelos numeros do servidor. Metadados do DTCG nao fazem
 * parte do caminho do token.
 */
function traverse(root: JsonRecord, context: Context): Traversal {
    const groups = new Map<string, GroupRecord>();
    const leaves = new Map<string, LeafRecord>();
    const stack: Array<{
        record: JsonRecord;
        path: string[];
        depth: number;
        inheritedType: string | null;
    }> = [{ record: root, path: [], depth: 0, inheritedType: null }];

    let visited = 0;
    let tooDeep = false;
    let tooMany = false;
    let colorTokenCount = 0;
    let dtcgLeaves = 0;
    let sdLeaves = 0;

    while (stack.length > 0) {
        const current = stack.pop();

        if (current === undefined) {
            break;
        }

        visited += 1;

        if (visited > context.limits.maxTokens) {
            tooMany = true;
            break;
        }

        const { record, path, depth, inheritedType } = current;

        const hasDtcgValue = own(record, '$value');
        const hasSdValue = own(record, 'value');

        if (hasDtcgValue || hasSdValue) {
            if (hasDtcgValue && hasSdValue) {
                error(context, 'mixed_token_styles', pathOf(path));
                continue;
            }

            if (hasDtcgValue) {
                dtcgLeaves += 1;
            } else {
                sdLeaves += 1;
            }

            const ownType = hasDtcgValue
                ? typeof record.$type === 'string'
                    ? record.$type
                    : null
                : typeof record.type === 'string'
                  ? record.type
                  : null;

            const leaf: LeafRecord = {
                path,
                type: ownType ?? inheritedType,
                value: hasDtcgValue ? record.$value : record.value,
            };

            leaves.set(pathKey(path), leaf);

            if (path[0] === 'color') {
                if (leaf.type === null || leaf.type === COLOR_TYPE) {
                    colorTokenCount += 1;
                    groups.get(pathKey(path.slice(0, -1)))?.leaves.push(leaf);
                } else {
                    warn(context, 'non_color_ignored', pathOf(path), {
                        type: leaf.type,
                    });
                }
            }

            continue;
        }

        const group: GroupRecord = { path, leaves: [] };

        groups.set(pathKey(path), group);

        const groupType =
            typeof record.$type === 'string'
                ? record.$type
                : typeof record.type === 'string'
                  ? record.type
                  : inheritedType;

        // Empilhado ao contrario para que a pilha devolva os filhos na ordem
        // do documento; a ordem dos filhos de um grupo e a ordem de peso da
        // paleta extraida.
        for (const key of Object.keys(record).reverse()) {
            if (FORBIDDEN_KEYS.has(key)) {
                error(context, 'forbidden_key', pathOf([...path, key]), {
                    key,
                });
                continue;
            }

            if (METADATA_KEYS.has(key)) {
                continue;
            }

            const child = record[key];

            if (!isRecord(child)) {
                continue;
            }

            if (depth + 1 > context.limits.maxDepth) {
                tooDeep = true;
                continue;
            }

            stack.push({
                record: child,
                path: [...path, key],
                depth: depth + 1,
                inheritedType: groupType,
            });
        }
    }

    if (tooDeep) {
        error(context, 'too_deep', undefined, {
            max: context.limits.maxDepth,
        });
    }

    if (tooMany) {
        error(context, 'too_many_tokens', undefined, {
            max: context.limits.maxTokens,
        });
    }

    return { groups, leaves, colorTokenCount, dtcgLeaves, sdLeaves };
}

function isEveryKeyATone(group: GroupRecord): boolean {
    return group.leaves.every((leaf) => {
        const key = leaf.path[leaf.path.length - 1] ?? '';

        return TONE_NAMES.has(key);
    });
}

function toneKeys(group: GroupRecord): Set<string> {
    return new Set(
        group.leaves.map((leaf) => leaf.path[leaf.path.length - 1] ?? ''),
    );
}

function hasAllTones(group: GroupRecord): boolean {
    const keys = toneKeys(group);

    return PALETTE_TONES.every((tone) => keys.has(String(tone)));
}

/** Grupo candidato so entre os descendentes de `color`, fora de `semantic`. */
function candidateGroups(traversal: Traversal): GroupRecord[] {
    return [...traversal.groups.values()].filter(
        (group) =>
            group.path[0] === 'color' &&
            !group.path.includes('semantic') &&
            group.leaves.length > 0,
    );
}

type Selection =
    | { ok: true; group: GroupRecord; kind: TokenImportKind }
    | { ok: false };

function selectGroup(traversal: Traversal, context: Context): Selection {
    const groups = candidateGroups(traversal);

    if (groups.length === 0) {
        error(context, 'no_colors');

        return { ok: false };
    }

    const scaleGroups = groups.filter(hasAllTones);

    if (scaleGroups.length > 1) {
        for (const group of scaleGroups) {
            error(context, 'ambiguous_palette', pathOf(group.path));
        }

        return { ok: false };
    }

    const scaleGroup = scaleGroups[0];

    if (scaleGroup !== undefined) {
        const extra = scaleGroup.leaves.length - PALETTE_TONES.length;

        if (extra > 0) {
            warn(context, 'extra_colors_ignored', pathOf(scaleGroup.path), {
                count: extra,
            });
        }

        return { ok: true, group: scaleGroup, kind: 'scale' };
    }

    const extractedGroups = groups.filter(
        (group) =>
            !isEveryKeyATone(group) &&
            EXTRACTED_COUNTS.includes(group.leaves.length),
    );

    if (extractedGroups.length > 1) {
        for (const group of extractedGroups) {
            error(context, 'ambiguous_palette', pathOf(group.path));
        }

        return { ok: false };
    }

    const extracted = extractedGroups[0];

    if (extracted !== undefined) {
        return { ok: true, group: extracted, kind: 'extracted' };
    }

    // Nenhum candidato: explica por grupo o que impediu a selecao.
    for (const group of groups) {
        const at = pathOf(group.path);

        if (isEveryKeyATone(group)) {
            const keys = toneKeys(group);
            const missing = PALETTE_TONES.filter(
                (tone) => !keys.has(String(tone)),
            );

            error(context, 'missing_tones', at, {
                missing: missing.join(', '),
            });
        } else {
            error(context, 'unsupported_color_count', at, {
                count: group.leaves.length,
            });
        }
    }

    return { ok: false };
}

function resolveGroup(
    group: GroupRecord,
    traversal: Traversal,
    context: Context,
): Map<string, string> | null {
    const resolved = new Map<string, string>();
    let failed = false;

    for (const leaf of group.leaves) {
        const key = leaf.path[leaf.path.length - 1] ?? '';
        const hex = resolveLeafHex(leaf, traversal.leaves, context, true);

        if (hex === null) {
            failed = true;
        } else if (!resolved.has(key)) {
            resolved.set(key, hex);
        }
    }

    return failed ? null : resolved;
}

function scaleBase(
    resolved: Map<string, string>,
    metadata: Metadata,
): string | null {
    return metadata.base ?? resolved.get('500') ?? null;
}

function buildScale(
    resolved: Map<string, string>,
    base: string,
    metadata: Metadata,
    context: Context,
    at: string | undefined,
): PaletteScale | null {
    const items: Array<{ tone: number; hex: string }> = [];

    for (const tone of PALETTE_TONES) {
        const hex = resolved.get(String(tone));

        if (hex === undefined) {
            error(context, 'missing_tones', at, { missing: String(tone) });

            return null;
        }

        items.push({ tone, hex });
    }

    try {
        return buildFrozenScale({
            base,
            items,
            algorithmVersion: PALETTE_ALGORITHM_VERSION,
            clippedTones: metadata.clippedTones,
        });
    } catch {
        error(context, 'invalid_structure', at);

        return null;
    }
}

function extractionBase(
    resolved: Map<string, string>,
    group: GroupRecord,
    metadata: Metadata,
): string | null {
    const colors = group.leaves
        .map((leaf) => resolved.get(leaf.path[leaf.path.length - 1] ?? ''))
        .filter((hex): hex is string => hex !== undefined);
    const normalized = new Set(colors.map((hex) => hex.toLowerCase()));

    if (
        metadata.base !== null &&
        normalized.has(metadata.base.toLowerCase())
    ) {
        return metadata.base;
    }

    return resolved.get('500') ?? colors[0] ?? null;
}

function buildExtracted(
    group: GroupRecord,
    resolved: Map<string, string>,
    base: string,
    sourceLabel: string,
    context: Context,
): ExtractedPalette | null {
    const seen = new Set<string>();
    const colors: ExtractedColor[] = [];

    // A ordem dos filhos e a ordem de peso da paleta extraida.
    for (const leaf of group.leaves) {
        const key = leaf.path[leaf.path.length - 1] ?? '';
        const hex = resolved.get(key);

        if (hex === undefined || seen.has(key)) {
            continue;
        }

        seen.add(key);

        const parsed = parseColor(hex);

        if (!parsed.ok) {
            error(context, 'invalid_color', pathOf(leaf.path));

            return null;
        }

        colors.push({
            hex,
            rgba: parsed.color.rgba,
            weight: 0,
            percentage: 0,
        });
    }

    if (!EXTRACTED_COUNTS.includes(colors.length)) {
        error(context, 'unsupported_color_count', pathOf(group.path), {
            count: colors.length,
        });

        return null;
    }

    return {
        colors,
        requested: colors.length as ExtractionCount,
        algorithmVersion: EXTRACTION_ALGORITHM_VERSION,
        sufficient: true,
        visiblePixels: 0,
        source: { kind: 'file', label: sourceLabel },
    };
}

function recordAt(root: JsonRecord, path: string[]): JsonRecord | null {
    let current: unknown = root;

    for (const segment of path) {
        if (!isRecord(current)) {
            return null;
        }

        current = current[segment];
    }

    return isRecord(current) ? current : null;
}

/**
 * Papeis so existem no bloco exportado `semantic.{light,dark}.{papel}`.
 * Qualquer ausencia ou cor invalida vira aviso e a paleta importa sem papeis;
 * o editor mantem os papeis atuais.
 */
function rolesFromTokens(
    root: JsonRecord,
    groupPath: string[],
    traversal: Traversal,
    context: Context,
): PaletteRoles | null {
    const semantic = recordAt(root, [...groupPath, 'semantic']);

    if (semantic === null) {
        return null;
    }

    const result = {} as PaletteRoles;
    const missing: string[] = [];

    for (const theme of PALETTE_THEMES) {
        const colors = {} as PaletteRoleColors;

        for (const role of PALETTE_ROLES) {
            const leaf =
                traversal.leaves.get(
                    pathKey([...groupPath, 'semantic', theme, role]),
                ) ?? null;

            const hex =
                leaf === null
                    ? null
                    : resolveLeafHex(leaf, traversal.leaves, context, false);

            colors[role as PaletteRole] = hex ?? '';

            if (hex === null) {
                missing.push(`${theme}.${role}`);
            }
        }

        result[theme as PaletteTheme] = colors;
    }

    if (missing.length > 0) {
        warn(context, 'semantic_incomplete', pathOf(groupPath), {
            missing: missing.join(', '),
        });

        return null;
    }

    return result;
}

function nativeRoles(root: JsonRecord, context: Context): PaletteRoles | null {
    const semantic = root.semantic;

    if (!isRecord(semantic)) {
        return null;
    }

    const result = {} as PaletteRoles;
    const missing: string[] = [];

    for (const theme of PALETTE_THEMES) {
        const themeRecord = semantic[theme];
        const colors = {} as PaletteRoleColors;

        for (const role of PALETTE_ROLES) {
            const value = isRecord(themeRecord) ? themeRecord[role] : undefined;

            if (typeof value === 'string' && HEX.test(value)) {
                colors[role as PaletteRole] = value;
            } else {
                colors[role as PaletteRole] = '';
                missing.push(`${theme}.${role}`);
            }
        }

        result[theme as PaletteTheme] = colors;
    }

    if (missing.length > 0) {
        warn(context, 'semantic_incomplete', 'semantic', {
            missing: missing.join(', '),
        });

        return null;
    }

    return result;
}

function selectNative(
    colors: JsonRecord,
    context: Context,
): { kind: TokenImportKind; keys: string[] } | null {
    const keys = Object.keys(colors);

    if (keys.length === 0) {
        error(context, 'no_colors');

        return null;
    }

    if (keys.length > context.limits.maxTokens) {
        error(context, 'too_many_tokens', undefined, {
            max: context.limits.maxTokens,
        });

        return null;
    }

    if (PALETTE_TONES.every((tone) => keys.includes(String(tone)))) {
        const extra = keys.length - PALETTE_TONES.length;

        if (extra > 0) {
            warn(context, 'extra_colors_ignored', 'colors', { count: extra });
        }

        return { kind: 'scale', keys };
    }

    if (keys.every((key) => TONE_NAMES.has(key))) {
        const missing = PALETTE_TONES.filter(
            (tone) => !keys.includes(String(tone)),
        );

        error(context, 'missing_tones', 'colors', {
            missing: missing.join(', '),
        });

        return null;
    }

    if (EXTRACTED_COUNTS.includes(keys.length)) {
        return { kind: 'extracted', keys };
    }

    error(context, 'unsupported_color_count', 'colors', {
        count: keys.length,
    });

    return null;
}

function importNative(
    root: JsonRecord,
    context: Context,
    sourceLabel: string,
): TokenImportResult {
    const colors = root.colors;

    if (!isRecord(colors)) {
        error(context, 'invalid_structure', 'colors');

        return failure(context);
    }

    if (own(root, 'color') && isRecord(root.color)) {
        error(context, 'ambiguous_format');

        return failure(context);
    }

    const metadata = metadataOf(root, 'native', context);

    if (metadata === null) {
        return failure(context);
    }

    const selection = selectNative(colors, context);

    if (selection === null) {
        return failure(context);
    }

    const resolved = new Map<string, string>();
    let failed = false;

    for (const key of selection.keys) {
        const value = colors[key];

        if (typeof value !== 'string' || !HEX.test(value)) {
            error(context, 'invalid_color', `colors.${key}`);
            failed = true;
            continue;
        }

        resolved.set(key, value);
    }

    if (failed) {
        return failure(context);
    }

    const expectedVersion =
        selection.kind === 'scale'
            ? PALETTE_ALGORITHM_VERSION
            : EXTRACTION_ALGORITHM_VERSION;

    if (
        metadata.algorithmVersion !== null &&
        metadata.algorithmVersion !== expectedVersion
    ) {
        warn(context, 'algorithm_version_mismatch', undefined, {
            found: metadata.algorithmVersion,
            expected: expectedVersion,
        });
    }

    if (selection.kind === 'scale') {
        const base = scaleBase(resolved, metadata);

        if (base === null) {
            error(context, 'invalid_color', 'colors');

            return failure(context);
        }

        const scale = buildScale(resolved, base, metadata, context, 'colors');

        if (scale === null) {
            return failure(context);
        }

        return {
            ok: true,
            palette: {
                format: 'native',
                kind: 'scale',
                base,
                scale,
                extracted: null,
                roles: nativeRoles(root, context),
                tokenCount: selection.keys.length,
                name: metadata.name,
            },
            diagnostics: context.diagnostics,
        };
    }

    const group: GroupRecord = {
        path: ['colors'],
        leaves: selection.keys.map((key) => ({
            path: ['colors', key],
            type: COLOR_TYPE,
            value: resolved.get(key),
        })),
    };
    const base = extractionBase(resolved, group, metadata);

    if (base === null) {
        error(context, 'invalid_color', 'colors');

        return failure(context);
    }

    const extracted = buildExtracted(group, resolved, base, sourceLabel, context);

    if (extracted === null) {
        return failure(context);
    }

    return {
        ok: true,
        palette: {
            format: 'native',
            kind: 'extracted',
            base,
            scale: null,
            extracted,
            roles: nativeRoles(root, context),
            tokenCount: selection.keys.length,
            name: metadata.name,
        },
        diagnostics: context.diagnostics,
    };
}

function importGeneric(
    root: JsonRecord,
    format: TokenImportFormat,
    traversal: Traversal,
    context: Context,
    sourceLabel: string,
): TokenImportResult {
    const metadata = metadataOf(root, format, context);

    if (metadata === null) {
        return failure(context);
    }

    if (traversal.colorTokenCount === 0) {
        error(context, 'no_colors');

        return failure(context);
    }

    const selection = selectGroup(traversal, context);

    if (!selection.ok) {
        return failure(context);
    }

    const resolved = resolveGroup(selection.group, traversal, context);

    if (resolved === null) {
        return failure(context);
    }

    const expectedVersion =
        selection.kind === 'scale'
            ? PALETTE_ALGORITHM_VERSION
            : EXTRACTION_ALGORITHM_VERSION;

    if (
        metadata.algorithmVersion !== null &&
        metadata.algorithmVersion !== expectedVersion
    ) {
        warn(context, 'algorithm_version_mismatch', undefined, {
            found: metadata.algorithmVersion,
            expected: expectedVersion,
        });
    }

    const roles = rolesFromTokens(
        root,
        selection.group.path,
        traversal,
        context,
    );

    if (selection.kind === 'scale') {
        const base = scaleBase(resolved, metadata);

        if (base === null) {
            error(context, 'invalid_color', pathOf(selection.group.path));

            return failure(context);
        }

        const scale = buildScale(
            resolved,
            base,
            metadata,
            context,
            pathOf(selection.group.path),
        );

        if (scale === null) {
            return failure(context);
        }

        return {
            ok: true,
            palette: {
                format,
                kind: 'scale',
                base,
                scale,
                extracted: null,
                roles,
                tokenCount: traversal.colorTokenCount,
                name: metadata.name,
            },
            diagnostics: context.diagnostics,
        };
    }

    const base = extractionBase(resolved, selection.group, metadata);

    if (base === null) {
        error(context, 'invalid_color', pathOf(selection.group.path));

        return failure(context);
    }

    const extracted = buildExtracted(
        selection.group,
        resolved,
        base,
        sourceLabel,
        context,
    );

    if (extracted === null) {
        return failure(context);
    }

    return {
        ok: true,
        palette: {
            format,
            kind: 'extracted',
            base,
            scale: null,
            extracted,
            roles,
            tokenCount: traversal.colorTokenCount,
            name: metadata.name,
        },
        diagnostics: context.diagnostics,
    };
}

export function importTokens(
    text: string,
    options: TokenImportOptions,
): TokenImportResult {
    const context: Context = {
        limits: options.limits,
        diagnostics: [],
    };

    if (text.trim() === '') {
        error(context, 'empty_file');

        return failure(context);
    }

    if (byteLength(text) > options.limits.maxBytes) {
        error(context, 'too_large', undefined, {
            max: options.limits.maxBytes,
        });

        return failure(context);
    }

    const parsed = analyzeJson(text);

    if (!parsed.ok) {
        error(context, 'invalid_json', undefined, {
            ...(parsed.line === null ? {} : { line: parsed.line }),
            ...(parsed.column === null ? {} : { column: parsed.column }),
        });

        return failure(context);
    }

    if (!isRecord(parsed.value)) {
        error(context, 'root_not_object');

        return failure(context);
    }

    const root = parsed.value;
    const hasNative = own(root, 'colors');
    const hasGeneric = own(root, 'color');

    if (hasNative && hasGeneric) {
        error(context, 'ambiguous_format');

        return failure(context);
    }

    if (hasNative) {
        return importNative(root, context, options.sourceLabel);
    }

    if (!hasGeneric) {
        error(context, 'unsupported_format');

        return failure(context);
    }

    if (!isRecord(root.color)) {
        error(context, 'invalid_structure', 'color');

        return failure(context);
    }

    const traversal = traverse(root, context);

    if (traversal.dtcgLeaves > 0 && traversal.sdLeaves > 0) {
        error(context, 'ambiguous_format');

        return failure(context);
    }

    if (hasErrors(context.diagnostics)) {
        return failure(context);
    }

    const format: TokenImportFormat =
        traversal.sdLeaves > 0 ? 'style-dictionary' : 'dtcg';

    return importGeneric(
        root,
        format,
        traversal,
        context,
        options.sourceLabel,
    );
}
