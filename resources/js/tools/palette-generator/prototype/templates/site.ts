import type { PaletteRoleColors } from '@/types/colors';
import { readableOn } from '../renderSvg';
import type { PrototypeCopy, SvgNode } from '../types';

/**
 * Template de site (1200x900): cabecalho, hero, botoes, links, cards,
 * formulario e rodape (F4, ADR-126).
 *
 * Cada elemento carrega o papel de cor que o pintou (`data-role`) e a regiao a
 * que pertence (`data-section`); o teste de templates confere as duas coisas.
 * Coordenadas sao fixas de proposito: o desenho nao se adapta ao conteudo, ele
 * mostra a paleta.
 */

const MARGIN = 64;
const CARD_WIDTH = 336;
const CARD_GAP = 32;

export function siteNodes(
    roles: PaletteRoleColors,
    copy: PrototypeCopy,
): SvgNode[] {
    const cardX = [MARGIN, MARGIN + CARD_WIDTH + CARD_GAP, MARGIN + (CARD_WIDTH + CARD_GAP) * 2];
    const navX = [640, 776, 904];
    const nodes: SvgNode[] = [
        {
            kind: 'rect',
            x: 0,
            y: 0,
            width: 1200,
            height: 900,
            fill: roles.background,
            role: 'background',
            section: 'canvas',
        },

        // Cabecalho
        {
            kind: 'rect',
            x: 0,
            y: 0,
            width: 1200,
            height: 88,
            fill: roles.surface,
            role: 'surface',
            section: 'header',
        },
        {
            kind: 'rect',
            x: 0,
            y: 87,
            width: 1200,
            height: 1,
            fill: roles.border,
            role: 'border',
            section: 'header',
        },
        {
            kind: 'circle',
            cx: 76,
            cy: 44,
            radius: 10,
            fill: roles.primary,
            role: 'primary',
            section: 'header',
        },
        {
            kind: 'text',
            x: 96,
            y: 51,
            text: copy.brand,
            size: 20,
            weight: 700,
            fill: roles.text,
            role: 'text',
            section: 'header',
        },
    ];

    for (const [index, label] of copy.nav.entries()) {
        nodes.push({
            kind: 'text',
            x: navX[index] as number,
            y: 51,
            text: label,
            size: 14,
            weight: 400,
            fill: roles.text_muted,
            role: 'text_muted',
            section: 'header',
        });
    }

    nodes.push(
        {
            kind: 'rect',
            x: 1000,
            y: 24,
            width: 136,
            height: 40,
            radius: 10,
            fill: roles.primary,
            role: 'primary',
            section: 'header',
        },
        {
            kind: 'text',
            x: 1068,
            y: 49,
            text: copy.primaryAction,
            size: 14,
            weight: 600,
            fill: readableOn(roles.primary),
            role: 'primary',
            section: 'header',
            anchor: 'middle',
        },

        // Hero
        {
            kind: 'text',
            x: MARGIN,
            y: 185,
            text: copy.heading,
            size: 44,
            weight: 700,
            fill: roles.text,
            role: 'text',
            section: 'hero',
        },
        {
            kind: 'text',
            x: MARGIN,
            y: 227,
            text: copy.body,
            size: 18,
            weight: 400,
            fill: roles.text_muted,
            role: 'text_muted',
            section: 'hero',
        },

        // Botoes
        {
            kind: 'rect',
            x: MARGIN,
            y: 268,
            width: 176,
            height: 52,
            radius: 12,
            fill: roles.primary,
            role: 'primary',
            section: 'buttons',
        },
        {
            kind: 'text',
            x: MARGIN + 88,
            y: 300,
            text: copy.primaryAction,
            size: 16,
            weight: 600,
            fill: readableOn(roles.primary),
            role: 'primary',
            section: 'buttons',
            anchor: 'middle',
        },
        {
            kind: 'rect',
            x: 256,
            y: 268,
            width: 184,
            height: 52,
            radius: 12,
            fill: roles.surface,
            stroke: roles.border,
            strokeWidth: 1,
            role: 'secondary',
            section: 'buttons',
        },
        {
            kind: 'text',
            x: 348,
            y: 300,
            text: copy.secondaryAction,
            size: 16,
            weight: 600,
            fill: roles.text,
            role: 'secondary',
            section: 'buttons',
            anchor: 'middle',
        },

        // Links
        ...copy.links.map(
            (label, index): SvgNode => ({
                kind: 'text',
                x: MARGIN + index * 220,
                y: 372,
                text: label,
                size: 15,
                weight: 600,
                fill: roles.accent,
                role: 'accent',
                section: 'links',
            }),
        ),

        // Cards
        {
            kind: 'text',
            x: MARGIN,
            y: 432,
            text: copy.sectionTitle,
            size: 26,
            weight: 700,
            fill: roles.text,
            role: 'text',
            section: 'cards',
        },
    );

    for (const [index, title] of copy.cards.entries()) {
        const x = cardX[index] as number;

        nodes.push(
            {
                kind: 'rect',
                x,
                y: 456,
                width: CARD_WIDTH,
                height: 204,
                radius: 16,
                fill: roles.surface,
                stroke: roles.border,
                strokeWidth: 1,
                role: 'surface',
                section: 'cards',
            },
            {
                kind: 'rect',
                x: x + 20,
                y: 476,
                width: 40,
                height: 40,
                radius: 10,
                fill: roles.accent,
                role: 'accent',
                section: 'cards',
            },
            {
                kind: 'text',
                x: x + 20,
                y: 552,
                text: title,
                size: 18,
                weight: 600,
                fill: roles.text,
                role: 'text',
                section: 'cards',
            },
            {
                kind: 'text',
                x: x + 20,
                y: 580,
                text: copy.cardBody,
                size: 14,
                weight: 400,
                fill: roles.text_muted,
                role: 'text_muted',
                section: 'cards',
            },
            {
                kind: 'text',
                x: x + 20,
                y: 636,
                text: copy.cardLink,
                size: 14,
                weight: 600,
                fill: roles.accent,
                role: 'accent',
                section: 'cards',
            },
        );
    }

    // Formulario
    nodes.push(
        {
            kind: 'rect',
            x: MARGIN,
            y: 694,
            width: 1072,
            height: 128,
            radius: 16,
            fill: roles.surface,
            stroke: roles.border,
            strokeWidth: 1,
            role: 'surface',
            section: 'form',
        },
        {
            kind: 'text',
            x: MARGIN + 24,
            y: 736,
            text: copy.formTitle,
            size: 20,
            weight: 600,
            fill: roles.text,
            role: 'text',
            section: 'form',
        },
        {
            kind: 'text',
            x: MARGIN + 24,
            y: 762,
            text: copy.formLabel,
            size: 13,
            weight: 400,
            fill: roles.text_muted,
            role: 'text_muted',
            section: 'form',
        },
        {
            kind: 'rect',
            x: MARGIN + 24,
            y: 774,
            width: 372,
            height: 40,
            radius: 8,
            fill: roles.background,
            stroke: roles.border,
            strokeWidth: 1,
            role: 'border',
            section: 'form',
        },
        {
            kind: 'text',
            x: MARGIN + 40,
            y: 799,
            text: copy.formPlaceholder,
            size: 14,
            weight: 400,
            fill: roles.text_muted,
            role: 'text_muted',
            section: 'form',
        },
        {
            kind: 'rect',
            x: MARGIN + 416,
            y: 774,
            width: 160,
            height: 40,
            radius: 8,
            fill: roles.primary,
            role: 'primary',
            section: 'form',
        },
        {
            kind: 'text',
            x: MARGIN + 496,
            y: 799,
            text: copy.formAction,
            size: 14,
            weight: 600,
            fill: readableOn(roles.primary),
            role: 'primary',
            section: 'form',
            anchor: 'middle',
        },

        // Rodape
        {
            kind: 'rect',
            x: 0,
            y: 846,
            width: 1200,
            height: 54,
            fill: roles.surface,
            role: 'surface',
            section: 'footer',
        },
        {
            kind: 'rect',
            x: 0,
            y: 846,
            width: 1200,
            height: 1,
            fill: roles.border,
            role: 'border',
            section: 'footer',
        },
        {
            kind: 'text',
            x: MARGIN,
            y: 879,
            text: copy.footer,
            size: 13,
            weight: 400,
            fill: roles.text_muted,
            role: 'text_muted',
            section: 'footer',
        },
        {
            kind: 'text',
            x: 1136,
            y: 879,
            text: copy.links[0],
            size: 13,
            weight: 600,
            fill: roles.accent,
            role: 'accent',
            section: 'footer',
            anchor: 'end',
        },
    );

    return nodes;
}
