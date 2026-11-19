import type { PaletteRole, PaletteRoleColors } from '@/types/colors';
import type { LayoutThemeBoard, LayoutTokenId } from '../engine';

/**
 * Papeis do protótipo alimentados pelo quadro do sistema de layout (F4, ADR-173).
 *
 * O mapa é fixo e não tem editor: o protótipo é só uma vitrine dos tokens
 * gravados. O usuário edita o token no quadro, e o protótipo acompanha. Os
 * papéis que o layout não possui (como `text`) saem de tokens equivalentes.
 */

/** Papel do protótipo → token do layout. */
export const LAYOUT_ROLE_MAP: Record<PaletteRole, LayoutTokenId> = {
    primary: 'primary',
    secondary: 'secondary',
    accent: 'accent',
    background: 'background',
    surface: 'surface',
    text: 'on-background',
    text_muted: 'on-surface-variant',
    border: 'hairline',
};

/** Cores de um tema do quadro no formato que o protótipo desenha. */
export function layoutPrototypeColors(
    board: LayoutThemeBoard,
): PaletteRoleColors {
    const colors = {} as PaletteRoleColors;

    for (const [role, token] of Object.entries(LAYOUT_ROLE_MAP) as [
        PaletteRole,
        LayoutTokenId,
    ][]) {
        colors[role] = board.tokens[token].hex;
    }

    return colors;
}
