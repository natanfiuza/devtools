import type { PaletteRoleColors } from '@/types/colors';
import { roleStrip } from '../renderSvg';
import type { PrototypeCopy, SvgNode } from '../types';

/**
 * Imagem de compartilhamento (1200x630) desenhada pelo mesmo renderizador
 * (F4, ADR-125/ADR-126).
 *
 * A faixa mostra a paleta de verdade: a escala/cor extraida quando a pagina
 * manda as cores, senao os 6 papeis principais. O upload do PNG e validado no
 * servidor, que exige exatamente estas dimensoes.
 */

const MARGIN = 80;
const STRIP_WIDTH = 1200 - MARGIN * 2;
const STRIP_GAP = 8;

export function ogNodes(
    roles: PaletteRoleColors,
    copy: PrototypeCopy,
    colors: string[] = [],
): SvgNode[] {
    const strip = colors.length > 0 ? colors : roleStrip(roles);
    const cellWidth =
        (STRIP_WIDTH - STRIP_GAP * (strip.length - 1)) / strip.length;
    const nodes: SvgNode[] = [
        {
            kind: 'rect',
            x: 0,
            y: 0,
            width: 1200,
            height: 630,
            fill: roles.background,
            role: 'background',
            section: 'canvas',
        },

        // Cabecalho
        {
            kind: 'text',
            x: MARGIN,
            y: 104,
            text: copy.brand,
            size: 22,
            weight: 700,
            fill: roles.text,
            role: 'text',
            section: 'header',
        },
        {
            kind: 'rect',
            x: MARGIN,
            y: 140,
            width: 96,
            height: 6,
            radius: 3,
            fill: roles.primary,
            role: 'primary',
            section: 'header',
        },

        // Nome e subtitulo
        {
            kind: 'text',
            x: MARGIN,
            y: 264,
            text: copy.heading,
            size: 64,
            weight: 700,
            fill: roles.text,
            role: 'text',
            section: 'hero',
        },
        {
            kind: 'text',
            x: MARGIN,
            y: 308,
            text: copy.tagline,
            size: 22,
            weight: 400,
            fill: roles.text_muted,
            role: 'text_muted',
            section: 'hero',
        },
    ];

    for (const [index, color] of strip.entries()) {
        nodes.push({
            kind: 'rect',
            x: MARGIN + index * (cellWidth + STRIP_GAP),
            y: 360,
            width: cellWidth,
            height: 170,
            radius: 8,
            fill: color,
            section: 'palette',
        });
    }

    nodes.push(
        {
            kind: 'text',
            x: MARGIN,
            y: 584,
            text: copy.footer,
            size: 16,
            weight: 400,
            fill: roles.text_muted,
            role: 'text_muted',
            section: 'footer',
        },
    );

    return nodes;
}
