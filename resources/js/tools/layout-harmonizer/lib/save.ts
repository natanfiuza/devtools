import type { Rgb } from '@/lib/color';
import type { LayoutSystemVisibility } from '@/types/layoutSystem';
import type { LayoutStrategy } from '../engine';
import type { LayoutSnapshot } from './snapshot';
import {
    buildLayoutState,
    type LayoutStateInputsV1,
    type LayoutSystemStateV1,
} from './state';

/**
 * Salvamento do sistema de layout (F4, ADR-168).
 *
 * O modal apenas acrescenta metadados: o envelope persistido vai inteiro do
 * workspace (`inputs` crus, secundaria efetiva e snapshot), sem recalcular
 * nenhum token. O nome sugerido e uma funcao pura e sempre editavel.
 */

/** `t` do vue-i18n reduzido ao que o nome sugerido usa. */
export type LayoutMessageTranslator = (
    key: string,
    named?: Record<string, string | number>,
) => string;

export interface LayoutSystemDetails {
    name: string;
    description: string;
    visibility: LayoutSystemVisibility;
    tags: string[];
}

/** O que o workspace entrega ao modal. */
export interface LayoutSystemSaveSource {
    inputs: LayoutStateInputsV1;
    secondary: Rgb | null;
    snapshot: LayoutSnapshot;
}

export interface LayoutSystemSavePayload {
    name: string;
    description: string | null;
    visibility: LayoutSystemVisibility;
    tags: string[];
    state: LayoutSystemStateV1;
    /** So quando a criacao veio do CTA de uma paleta acessivel. */
    source_palette_id?: string;
}

/** Nome no limite configurado, sem espaco sobrando no fim. */
export function truncateLayoutName(name: string, maxLength: number): string {
    const trimmed = name.trim();

    if (maxLength <= 0 || trimmed.length <= maxLength) {
        return trimmed;
    }

    return trimmed.slice(0, maxLength).trimEnd();
}

export interface SuggestNameOptions {
    /** Nome da paleta de origem, quando o CTA veio de uma paleta. */
    originName?: string | null;
    /** Estrategia ja traduzida. */
    strategyLabel: string;
    /** HEX da seed efetiva. */
    hex: string;
    t: LayoutMessageTranslator;
    maxLength: number;
}

/**
 * Nome sugerido na criacao: o da paleta de origem quando existe, senao a
 * estrategia com o HEX da seed. Nunca e imposto: o campo abre editavel.
 */
export function suggestLayoutSystemName(options: SuggestNameOptions): string {
    const origin = options.originName?.trim() ?? '';

    const raw =
        origin !== ''
            ? options.t('tools.layout-harmonizer.save.name_from_palette', {
                  name: origin,
              })
            : options.t('tools.layout-harmonizer.save.name_from_strategy', {
                  strategy: options.strategyLabel,
                  hex: options.hex,
              });

    return truncateLayoutName(raw, options.maxLength);
}

export interface SuggestCopyOptions {
    currentName: string;
    t: LayoutMessageTranslator;
    maxLength: number;
}

/**
 * "Salvar como novo": nome atual + sufixo de copia. O nome cede espaco ao
 * sufixo para o resultado nunca perder o marcador de copia no limite.
 */
export function suggestCopyName(options: SuggestCopyOptions): string {
    const suffix = options.t('colors.palette.copy_suffix');
    const room = Math.max(0, options.maxLength - suffix.length);
    const base = truncateLayoutName(options.currentName, room);

    return truncateLayoutName(`${base}${suffix}`, options.maxLength);
}

/**
 * Corpo enviado ao servidor. O `source_palette_id` so viaja quando a paleta
 * ainda existe; o credito congelado e sempre do servidor.
 */
export function layoutSystemPayload(
    source: LayoutSystemSaveSource,
    details: LayoutSystemDetails,
    sourcePaletteId: string | null = null,
): LayoutSystemSavePayload {
    const description = details.description.trim();

    const payload: LayoutSystemSavePayload = {
        name: details.name.trim(),
        description: description === '' ? null : description,
        visibility: details.visibility,
        tags: [...details.tags],
        state: buildLayoutState(
            source.inputs,
            source.secondary,
            source.snapshot,
        ),
    };

    if (sourcePaletteId !== null && sourcePaletteId !== '') {
        payload.source_palette_id = sourcePaletteId;
    }

    return payload;
}

/** Identidade do envelope para a guarda de alteracoes. */
export function layoutStateFingerprint(
    state: LayoutSystemStateV1 | null,
): string {
    return state === null ? '' : JSON.stringify(state);
}

/** Chave i18n da estrategia, usada no nome sugerido e nas telas. */
export function strategyMessageKey(strategy: LayoutStrategy): string {
    return `tools.layout-harmonizer.strategy.${strategy}.name`;
}
