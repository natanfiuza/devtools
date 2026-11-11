import type { PaletteScale } from '@/lib/color';
import type { PaletteDraftTab } from '@/lib/saveDraft';
import type { ExtractedPalette } from '@/tools/palette-generator/lib/extractedPalette';
import type { PaletteKind } from '@/types/colors';

/**
 * O que o modal de salvar recebe do gerador (F4, ADR-123).
 *
 * A escala vai inteira, com os HEX efetivos; a extraida vai com o peso e a
 * origem que a tela mostrou. Em nenhum dos casos o modal recalcula cor: ele
 * so acrescenta nome, descricao, visibilidade e tags e monta o payload.
 */
export type PaletteSaveSource =
    | { kind: 'scale'; scale: PaletteScale }
    | { kind: 'extracted'; palette: ExtractedPalette; base: string };

/** Estado do workspace no instante em que "Salvar" foi clicado. */
export interface WorkspaceSnapshot {
    kind: PaletteKind;
    tab: PaletteDraftTab;
    isEmpty: boolean;
    base: string;
    scale: PaletteScale;
    extracted: ExtractedPalette | null;
}
