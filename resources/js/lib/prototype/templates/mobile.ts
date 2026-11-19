import type { PaletteRoleColors } from '@/types/colors';
import { readableOn } from '../renderSvg';
import type { PrototypeCopy, SvgNode } from '../types';

/**
 * Template de celular (390x844): barra do app, tabs no topo, conteudo,
 * formulario e rodape (F4, ADR-126).
 *
 * Nao ha bottom nav: a decisao do prompt da F4 e que a navegacao fica no topo,
 * e o teste de templates garante que nenhuma regiao `bottom_nav` apareca.
 */

const MARGIN = 16;
const CARD_WIDTH = 358;

export function mobileNodes(
    roles: PaletteRoleColors,
    copy: PrototypeCopy,
): SvgNode[] {
    const tabX = [65, 195, 325];
    const nodes: SvgNode[] = [
        {
            kind: 'rect',
            x: 0,
            y: 0,
            width: 390,
            height: 844,
            fill: roles.background,
            role: 'background',
            section: 'canvas',
        },

        // Barra do app
        {
            kind: 'rect',
            x: 0,
            y: 0,
            width: 390,
            height: 56,
            fill: roles.surface,
            role: 'surface',
            section: 'appbar',
        },
        {
            kind: 'rect',
            x: 0,
            y: 55,
            width: 390,
            height: 1,
            fill: roles.border,
            role: 'border',
            section: 'appbar',
        },
        {
            kind: 'circle',
            cx: 30,
            cy: 28,
            radius: 8,
            fill: roles.primary,
            role: 'primary',
            section: 'appbar',
        },
        {
            kind: 'text',
            x: 46,
            y: 33,
            text: copy.brand,
            size: 15,
            weight: 700,
            fill: roles.text,
            role: 'text',
            section: 'appbar',
        },
        {
            kind: 'circle',
            cx: 362,
            cy: 28,
            radius: 13,
            fill: roles.accent,
            role: 'accent',
            section: 'appbar',
        },

        // Tabs no topo
        {
            kind: 'rect',
            x: 0,
            y: 56,
            width: 390,
            height: 48,
            fill: roles.background,
            role: 'background',
            section: 'tabs',
        },
        {
            kind: 'rect',
            x: 0,
            y: 103,
            width: 390,
            height: 1,
            fill: roles.border,
            role: 'border',
            section: 'tabs',
        },
        {
            kind: 'rect',
            x: 34,
            y: 100,
            width: 62,
            height: 3,
            radius: 1.5,
            fill: roles.accent,
            role: 'accent',
            section: 'tabs',
        },
    ];

    for (const [index, label] of copy.tabs.entries()) {
        const active = index === 0;

        nodes.push({
            kind: 'text',
            x: tabX[index] as number,
            y: 86,
            text: label,
            size: 14,
            weight: active ? 700 : 400,
            fill: active ? roles.text : roles.text_muted,
            role: active ? 'text' : 'text_muted',
            section: 'tabs',
            anchor: 'middle',
        });
    }

    nodes.push(
        // Hero
        {
            kind: 'rect',
            x: MARGIN,
            y: 128,
            width: CARD_WIDTH,
            height: 180,
            radius: 16,
            fill: roles.surface,
            stroke: roles.border,
            strokeWidth: 1,
            role: 'surface',
            section: 'hero',
        },
        {
            kind: 'text',
            x: MARGIN + 20,
            y: 178,
            text: copy.heading,
            size: 20,
            weight: 700,
            fill: roles.text,
            role: 'text',
            section: 'hero',
        },
        {
            kind: 'text',
            x: MARGIN + 20,
            y: 206,
            text: copy.body,
            size: 13,
            weight: 400,
            fill: roles.text_muted,
            role: 'text_muted',
            section: 'hero',
        },
        {
            kind: 'rect',
            x: MARGIN + 20,
            y: 240,
            width: 152,
            height: 42,
            radius: 10,
            fill: roles.primary,
            role: 'primary',
            section: 'buttons',
        },
        {
            kind: 'text',
            x: MARGIN + 96,
            y: 266,
            text: copy.primaryAction,
            size: 14,
            weight: 600,
            fill: readableOn(roles.primary),
            role: 'primary',
            section: 'buttons',
            anchor: 'middle',
        },
    );

    for (const [index, title] of copy.cards.slice(0, 2).entries()) {
        const y = 328 + index * 126;

        nodes.push(
            {
                kind: 'rect',
                x: MARGIN,
                y,
                width: CARD_WIDTH,
                height: 110,
                radius: 16,
                fill: roles.surface,
                stroke: roles.border,
                strokeWidth: 1,
                role: 'surface',
                section: 'content',
            },
            {
                kind: 'text',
                x: MARGIN + 20,
                y: y + 38,
                text: title,
                size: 16,
                weight: 600,
                fill: roles.text,
                role: 'text',
                section: 'content',
            },
            {
                kind: 'text',
                x: MARGIN + 20,
                y: y + 62,
                text: copy.cardBody,
                size: 13,
                weight: 400,
                fill: roles.text_muted,
                role: 'text_muted',
                section: 'content',
            },
            {
                kind: 'text',
                x: MARGIN + 20,
                y: y + 86,
                text: copy.cardLink,
                size: 13,
                weight: 600,
                fill: roles.accent,
                role: 'accent',
                section: 'content',
            },
        );
    }

    nodes.push(
        // Formulario
        {
            kind: 'rect',
            x: MARGIN,
            y: 580,
            width: CARD_WIDTH,
            height: 178,
            radius: 16,
            fill: roles.surface,
            stroke: roles.border,
            strokeWidth: 1,
            role: 'surface',
            section: 'form',
        },
        {
            kind: 'text',
            x: MARGIN + 20,
            y: 616,
            text: copy.formTitle,
            size: 16,
            weight: 600,
            fill: roles.text,
            role: 'text',
            section: 'form',
        },
        {
            kind: 'text',
            x: MARGIN + 20,
            y: 642,
            text: copy.formLabel,
            size: 12,
            weight: 400,
            fill: roles.text_muted,
            role: 'text_muted',
            section: 'form',
        },
        {
            kind: 'rect',
            x: MARGIN + 20,
            y: 652,
            width: 318,
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
            x: MARGIN + 34,
            y: 677,
            text: copy.formPlaceholder,
            size: 13,
            weight: 400,
            fill: roles.text_muted,
            role: 'text_muted',
            section: 'form',
        },
        {
            kind: 'rect',
            x: MARGIN + 20,
            y: 706,
            width: 150,
            height: 38,
            radius: 8,
            fill: roles.primary,
            role: 'primary',
            section: 'form',
        },
        {
            kind: 'text',
            x: MARGIN + 95,
            y: 730,
            text: copy.formAction,
            size: 13,
            weight: 600,
            fill: readableOn(roles.primary),
            role: 'primary',
            section: 'form',
            anchor: 'middle',
        },

        // Rodape (sem navegacao)
        {
            kind: 'rect',
            x: 0,
            y: 780,
            width: 390,
            height: 64,
            fill: roles.surface,
            role: 'surface',
            section: 'footer',
        },
        {
            kind: 'rect',
            x: 0,
            y: 780,
            width: 390,
            height: 1,
            fill: roles.border,
            role: 'border',
            section: 'footer',
        },
        {
            kind: 'text',
            x: 195,
            y: 818,
            text: copy.footer,
            size: 12,
            weight: 400,
            fill: roles.text_muted,
            role: 'text_muted',
            section: 'footer',
            anchor: 'middle',
        },
    );

    return nodes;
}
