import { truncateText } from '@/lib/paletteOg';
import { readableOn } from '../prototype/renderSvg';
import type { PrototypeDocument, SvgNode } from '../prototype/types';
import {
    IMAGE_BACKGROUND,
    IMAGE_MUTED,
    IMAGE_TEXT,
    PALETTE_IMAGE_SIZES,
    PALETTE_IMAGE_WATERMARK,
    type PaletteImageLayout,
    type PaletteImageSource,
} from './types';

/**
 * Documento SVG da imagem tipografica da paleta (F6, ADR-138).
 *
 * Puro e deterministico: mesma paleta, mesmo layout, mesmo texto. A fonte
 * recebe so o nome e os HEX - nada da conta, nada de URL de origem. As cores
 * invalidas sao descartadas aqui, entao um dado corrompido nunca vira atributo
 * estranho no SVG.
 */

const HEX_PATTERN = /^#[0-9a-f]{6}([0-9a-f]{2})?$/;

const NAME_MAX_CHARS: Record<PaletteImageLayout, number> = {
    horizontal: 26,
    vertical: 22,
    card: 22,
};

/** HEX validos, em minusculas e na ordem gravada. */
export function imageColors(source: PaletteImageSource): string[] {
    return source.colors
        .map((hex) => hex.trim().toLowerCase())
        .filter((hex) => HEX_PATTERN.test(hex));
}

function watermark(
    x: number,
    y: number,
    anchor: 'start' | 'middle' | 'end',
): SvgNode {
    return {
        kind: 'text',
        x,
        y,
        text: PALETTE_IMAGE_WATERMARK,
        size: 20,
        weight: 400,
        anchor,
        fill: IMAGE_MUTED,
    };
}

function horizontal(name: string, colors: string[]): SvgNode[] {
    const { width, height } = PALETTE_IMAGE_SIZES.horizontal;
    const band = 240;
    const nodes: SvgNode[] = [
        { kind: 'rect', x: 0, y: 0, width, height, fill: IMAGE_BACKGROUND },
    ];

    const segment = colors.length === 0 ? 0 : width / colors.length;

    colors.forEach((hex, index) => {
        nodes.push({
            kind: 'rect',
            x: index * segment,
            y: 0,
            width: segment,
            height: band,
            fill: hex,
        });
        nodes.push({
            kind: 'text',
            x: index * segment + segment / 2,
            y: 430,
            text: hex,
            size: 20,
            weight: 400,
            anchor: 'middle',
            fill: IMAGE_TEXT,
        });
    });

    nodes.push({
        kind: 'text',
        x: 80,
        y: 350,
        text: truncateText(name, NAME_MAX_CHARS.horizontal),
        size: 80,
        weight: 700,
        fill: IMAGE_TEXT,
    });
    nodes.push(watermark(width - 80, height - 30, 'end'));

    return nodes;
}

function vertical(name: string, colors: string[]): SvgNode[] {
    const { width, height } = PALETTE_IMAGE_SIZES.vertical;
    const top = 220;
    const bottom = 120;
    const nodes: SvgNode[] = [
        { kind: 'rect', x: 0, y: 0, width, height, fill: IMAGE_BACKGROUND },
        {
            kind: 'text',
            x: 72,
            y: 150,
            text: truncateText(name, NAME_MAX_CHARS.vertical),
            size: 72,
            weight: 700,
            fill: IMAGE_TEXT,
        },
    ];

    const rowHeight =
        colors.length === 0 ? 0 : (height - top - bottom) / colors.length;

    colors.forEach((hex, index) => {
        const y = top + index * rowHeight;

        nodes.push({
            kind: 'rect',
            x: 0,
            y,
            width,
            height: rowHeight,
            fill: hex,
        });
        nodes.push({
            kind: 'text',
            x: width - 56,
            y: y + rowHeight / 2 + Math.min(44, rowHeight * 0.3) / 3,
            text: hex,
            size: Math.min(44, rowHeight * 0.3),
            weight: 700,
            anchor: 'end',
            fill: readableOn(hex),
        });
    });

    nodes.push(watermark(width / 2, height - 40, 'middle'));

    return nodes;
}

function card(name: string, colors: string[]): SvgNode[] {
    const { width, height } = PALETTE_IMAGE_SIZES.card;
    const margin = 64;
    const top = 190;
    const bottom = 130;
    const gap = 16;
    const nodes: SvgNode[] = [
        { kind: 'rect', x: 0, y: 0, width, height, fill: IMAGE_BACKGROUND },
        {
            kind: 'text',
            x: margin,
            y: 130,
            text: truncateText(name, NAME_MAX_CHARS.card),
            size: 64,
            weight: 700,
            fill: IMAGE_TEXT,
        },
    ];

    if (colors.length === 0) {
        nodes.push(watermark(width - margin, height - 40, 'end'));

        return nodes;
    }

    const columns = colors.length <= 6 ? 3 : 4;
    const rows = Math.ceil(colors.length / columns);
    const cellWidth = (width - margin * 2 - gap * (columns - 1)) / columns;
    const cellHeight = (height - top - bottom - gap * (rows - 1)) / rows;
    const labelSize = Math.min(28, cellWidth * 0.12, cellHeight * 0.16);

    colors.forEach((hex, index) => {
        const column = index % columns;
        const row = Math.floor(index / columns);
        const x = margin + column * (cellWidth + gap);
        const y = top + row * (cellHeight + gap);

        nodes.push({
            kind: 'rect',
            x,
            y,
            width: cellWidth,
            height: cellHeight,
            radius: 12,
            fill: hex,
        });
        nodes.push({
            kind: 'text',
            x: x + cellWidth / 2,
            y: y + cellHeight / 2 + labelSize / 3,
            text: hex,
            size: labelSize,
            weight: 700,
            anchor: 'middle',
            fill: readableOn(hex),
        });
    });

    nodes.push(watermark(width - margin, height - 40, 'end'));

    return nodes;
}

/** Documento pronto para o `renderSvg`; o mesmo SVG vira PNG. */
export function buildPaletteImage(
    source: PaletteImageSource,
    layout: PaletteImageLayout,
): PrototypeDocument {
    const colors = imageColors(source);
    const size = PALETTE_IMAGE_SIZES[layout];

    const nodes =
        layout === 'horizontal'
            ? horizontal(source.name, colors)
            : layout === 'vertical'
              ? vertical(source.name, colors)
              : card(source.name, colors);

    return {
        kind: 'image',
        theme: 'light',
        width: size.width,
        height: size.height,
        label: truncateText(source.name, NAME_MAX_CHARS[layout]),
        nodes,
    };
}
