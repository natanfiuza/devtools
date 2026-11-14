import type { PaletteScale } from '@/lib/color';
import type { PaletteDraftTab } from '@/lib/saveDraft';
import type { ExtractedPalette } from '@/tools/palette-generator/lib/extractedPalette';
import type {
    PaletteKind,
    PaletteRoles,
} from '@/types/colors';

/**
 * O que o modal de salvar recebe do gerador (F4, ADR-123).
 *
 * A escala vai inteira, com os HEX efetivos; a extraida vai com o peso e a
 * origem que a tela mostrou. Em nenhum dos casos o modal recalcula cor: ele
 * so acrescenta nome, descricao, visibilidade e tags e monta o payload.
 *
 * `roles` so vem de uma importacao de tokens (F5, Etapa 6): quando presente,
 * substitui os papeis calculados pelos defaults do exportador.
 */
export type PaletteSaveSource =
    | { kind: 'scale'; scale: PaletteScale; roles?: PaletteRoles | null }
    | {
          kind: 'extracted';
          palette: ExtractedPalette;
          base: string;
          roles?: PaletteRoles | null;
      };

/** O que a assinatura do estado importado precisa comparar. */
export interface PaletteSignatureSource {
    kind: PaletteKind;
    base: string;
    scale?: PaletteScale | null;
    extracted?: ExtractedPalette | null;
}

/**
 * Identidade do snapshot: tipo, base e as cores efetivas.
 *
 * Os papeis importados so continuam validos enquanto a tela tiver exatamente
 * estas cores. Mudar a base, aplicar um preset ou reeditar a extracao muda a
 * assinatura e os papeis importados sao descartados.
 */
export function paletteSignature(source: PaletteSignatureSource): string {
    const base = source.base.trim().toLowerCase();

    if (source.kind === 'scale') {
        if (source.scale === null || source.scale === undefined) {
            return `scale|${base}`;
        }

        return [
            'scale',
            base,
            ...source.scale.tones.map(
                (tone) => `${tone.tone}:${tone.hex.toLowerCase()}`,
            ),
        ].join('|');
    }

    if (source.extracted === null || source.extracted === undefined) {
        return `extracted|${base}`;
    }

    return [
        'extracted',
        base,
        ...source.extracted.colors.map((color) => color.hex.toLowerCase()),
    ].join('|');
}

/** Estado do workspace no instante em que "Salvar" foi clicado. */
export interface WorkspaceSnapshot {
    kind: PaletteKind;
    tab: PaletteDraftTab;
    isEmpty: boolean;
    base: string;
    scale: PaletteScale;
    extracted: ExtractedPalette | null;
}
