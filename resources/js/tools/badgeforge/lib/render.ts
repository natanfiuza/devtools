import { fillAttributes } from './color';
import { textWidth } from './metrics';
import type { BadgeSpec, BadgeStyle } from '../types';

/**
 * Espelho de `App\Support\BadgeForge\StaticBadgeRenderer` (ADR-075).
 *
 * Documento unico, uma linha, mesma ordem de atributos do PHP: as fixtures de
 * `tests/Fixtures/badgeforge` sao comparadas byte a byte nos dois lados.
 * Qualquer mudanca aqui exige mudar o renderer PHP e regravar as fixtures.
 */

export function renderBadge(spec: BadgeSpec): string {
    const style = spec.style;
    const gap = style.gap;
    const logoWidth = spec.logo !== null ? spec.logoSize + gap : 0;
    const fontFamily = spec.font?.stack ?? style.fontFamily;
    const metricsFamily = spec.font?.slug ?? style.metricsFamily;

    const labelAdvance = textWidth(
        spec.label,
        metricsFamily,
        style.fontSize,
        style.fontWeight,
        style.letterSpacing,
    );

    const messageAdvance = textWidth(
        spec.message,
        metricsFamily,
        style.fontSize,
        style.fontWeight,
        style.letterSpacing,
    );

    const logoOnLabel = spec.logo !== null && spec.logoPosition === 'left';
    const logoOnMessage = spec.logo !== null && spec.logoPosition === 'right';

    const labelWidth =
        labelAdvance + 2 * style.padding + (logoOnLabel ? logoWidth : 0);
    const messageWidth =
        messageAdvance + 2 * style.padding + (logoOnMessage ? logoWidth : 0);
    const width = labelWidth + messageWidth;
    const height = style.height;

    const labelStart = style.padding + (logoOnLabel ? logoWidth : 0);
    const labelEnd = labelWidth - style.padding;
    const labelX = intdiv(labelStart + labelEnd, 2);

    const messageStart = labelWidth + style.padding;
    const messageEnd = width - style.padding - (logoOnMessage ? logoWidth : 0);
    const messageX = intdiv(messageStart + messageEnd, 2);

    const logoX =
        spec.logoPosition === 'right'
            ? width - style.padding - spec.logoSize
            : style.padding;
    const logoY = intdiv(height - spec.logoSize, 2);

    const stops = style.gradientStops;
    const accessibleLabel = escapeXml(`${spec.label}: ${spec.message}`);

    let defs = '';

    if (stops.length > 0) {
        defs += '<linearGradient id="s" x2="0" y2="100%">';

        for (const stop of stops) {
            defs += `<stop offset="${stop.offset}" stop-color="${stop.color}" stop-opacity="${stop.opacity}"/>`;
        }

        defs += '</linearGradient>';
    }

    if (style.radius > 0) {
        defs += `<clipPath id="r"><rect width="${width}" height="${height}" rx="${style.radius}"/></clipPath>`;
    }

    defs = defs !== '' ? `<defs>${defs}</defs>` : '';

    let group = `<g${style.radius > 0 ? ' clip-path="url(#r)"' : ''}>`;
    group += `<rect width="${labelWidth}" height="${height}" ${fillAttributes(spec.labelColor)}/>`;
    group += `<rect x="${labelWidth}" width="${messageWidth}" height="${height}" ${fillAttributes(spec.messageColor)}/>`;

    if (stops.length > 0) {
        group += `<rect width="${width}" height="${height}" fill="url(#s)"/>`;
    }

    if (style.bottomShadow !== null) {
        const shadowHeight = style.bottomShadow.height;
        group += `<rect y="${height - shadowHeight}" width="${width}" height="${shadowHeight}" fill="${style.bottomShadow.color}" fill-opacity="${style.bottomShadow.opacity}"/>`;
    }

    if (spec.logo !== null) {
        group += `<svg x="${logoX}" y="${logoY}" width="${spec.logoSize}" height="${spec.logoSize}" viewBox="${escapeXml(spec.logo.viewBox)}" aria-hidden="true" focusable="false">`;

        for (const logoPath of spec.logo.paths) {
            group += `<path d="${escapeXml(logoPath.d)}"`;

            if (logoPath.fillRule !== null) {
                group += ` fill-rule="${logoPath.fillRule}"`;
            }

            if (logoPath.clipRule !== null) {
                group += ` clip-rule="${logoPath.clipRule}"`;
            }

            group += ` ${fillAttributes(spec.logoColor)}/>`;
        }

        group += '</svg>';
    }

    group += textElement(labelX, style.baseline, style, fontFamily, spec.label);
    group += textElement(
        messageX,
        style.baseline,
        style,
        fontFamily,
        spec.message,
    );
    group += '</g>';

    let content = defs + group;

    if (spec.link !== null) {
        content = `<a href="${escapeXml(spec.link)}" target="_blank" rel="noopener noreferrer">${content}</a>`;
    }

    return (
        `<svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}" viewBox="0 0 ${width} ${height}" role="img" aria-label="${accessibleLabel}">` +
        `<title>${accessibleLabel}</title>` +
        content +
        '</svg>'
    );
}

/** Largura/altura do documento, uteis para os snippets e para o preview. */
export function badgeDimensions(spec: BadgeSpec): {
    width: number;
    height: number;
} {
    const svg = renderBadge(spec);
    const width = /<svg[^>]*\bwidth="(\d+)"/.exec(svg);
    const height = /<svg[^>]*\bheight="(\d+)"/.exec(svg);

    return {
        width: width === null ? 0 : Number(width[1]),
        height: height === null ? spec.style.height : Number(height[1]),
    };
}

export function escapeXml(value: string): string {
    return value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function textElement(
    x: number,
    y: number,
    style: BadgeStyle,
    fontFamily: string,
    text: string,
): string {
    let attributes =
        `x="${x}" y="${y}" ${fillAttributes(style.textColor)}` +
        ` font-family="${escapeXml(fontFamily)}"` +
        ` font-size="${style.fontSize}"` +
        ` font-weight="${style.fontWeight}"`;

    if (style.letterSpacing > 0) {
        attributes += ` letter-spacing="${style.letterSpacing}"`;
    }

    return `<text ${attributes} text-anchor="middle">${escapeXml(text)}</text>`;
}

/** `intdiv` do PHP: divisao inteira truncando (valores aqui sao positivos). */
function intdiv(a: number, b: number): number {
    return Math.trunc(a / b);
}
