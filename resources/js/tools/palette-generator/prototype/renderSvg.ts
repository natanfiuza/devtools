import { parseColor, relativeLuminance } from '@/lib/color';
import type { PaletteRoleColors } from '@/types/colors';
import type {
    PrototypeDocument,
    RenderOptions,
    SvgCircleNode,
    SvgNode,
    SvgRectNode,
    SvgTextNode,
} from './types';

/**
 * Serializador do SVG do prototipo (F4, ADR-126).
 *
 * Puro e deterministico: a mesma entrada gera o mesmo texto, byte a byte, o que
 * torna os snapshots confiaveis e o PNG identico ao SVG exibido. Todo texto e
 * atributo passa por `escapeXml`, e cada elemento carrega `data-role` (o papel
 * que o pintou) e `data-section` (a regiao da interface), o que permite testar
 * a fidelidade papel -> elemento sem olhar coordenadas.
 */

const XML_ESCAPES: Record<string, string> = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&apos;',
};

export function escapeXml(value: string): string {
    return value.replace(/[&<>"']/g, (char) => XML_ESCAPES[char] as string);
}

/**
 * Numero com no maximo duas casas e sem `-0`.
 *
 * Coordenada quebrada (a faixa da OG divide a largura) nao pode sair como
 * `94.54545454545455`, senao o snapshot muda por ruido de ponto flutuante.
 */
function num(value: number): string {
    const rounded = Math.round(value * 100) / 100;

    return Object.is(rounded, -0) ? '0' : String(rounded);
}

function attributes(pairs: Array<[string, string | undefined]>): string {
    return pairs
        .filter((pair): pair is [string, string] => pair[1] !== undefined)
        .map(([name, value]) => ` ${name}="${value}"`)
        .join('');
}

function optionalNum(value: number | undefined): string | undefined {
    return value === undefined ? undefined : num(value);
}

function marking(node: SvgNode): string {
    return attributes([
        ['data-role', node.role],
        ['data-section', node.section],
    ]);
}

function serializeRect(node: SvgRectNode): string {
    return `<rect${attributes([
        ['x', num(node.x)],
        ['y', num(node.y)],
        ['width', num(node.width)],
        ['height', num(node.height)],
        ['rx', optionalNum(node.radius)],
        ['fill', escapeXml(node.fill)],
        ['stroke', node.stroke === undefined ? undefined : escapeXml(node.stroke)],
        ['stroke-width', optionalNum(node.strokeWidth)],
    ])}${marking(node)}/>`;
}

function serializeCircle(node: SvgCircleNode): string {
    return `<circle${attributes([
        ['cx', num(node.cx)],
        ['cy', num(node.cy)],
        ['r', num(node.radius)],
        ['fill', escapeXml(node.fill)],
    ])}${marking(node)}/>`;
}

function serializeText(node: SvgTextNode): string {
    return `<text${attributes([
        ['x', num(node.x)],
        ['y', num(node.y)],
        ['font-size', num(node.size)],
        ['font-weight', String(node.weight)],
        ['fill', escapeXml(node.fill)],
        ['text-anchor', node.anchor],
    ])}${marking(node)}>${escapeXml(node.text)}</text>`;
}

function serialize(node: SvgNode): string {
    switch (node.kind) {
        case 'rect':
            return serializeRect(node);
        case 'circle':
            return serializeCircle(node);
        case 'text':
            return serializeText(node);
    }
}

/**
 * Cor de texto legivel sobre o preenchimento informado.
 *
 * Papeis sao editados pelo usuario, entao o rotulo de um botao nao pode ser
 * branco fixo: escolhe preto ou branco pela luminancia relativa (mesmo limiar
 * de contraste do resto da trilha).
 */
export function readableOn(fill: string): string {
    const parsed = parseColor(fill);

    if (!parsed.ok) {
        return '#ffffff';
    }

    return relativeLuminance(parsed.color.rgba) > 0.179 ? '#111111' : '#ffffff';
}

/** SVG completo do documento, com a fonte embutida quando pedida. */
export function renderSvg(
    document: PrototypeDocument,
    options: RenderOptions = {},
): string {
    const fonts = options.fonts?.trim() ?? '';
    const style = fonts === '' ? '' : `<style>${fonts}</style>`;
    const label = escapeXml(document.label);
    const body = document.nodes.map(serialize).join('');

    return `<svg${attributes([
        ['xmlns', 'http://www.w3.org/2000/svg'],
        ['width', num(document.width)],
        ['height', num(document.height)],
        [
            'viewBox',
            `0 0 ${num(document.width)} ${num(document.height)}`,
        ],
        ['role', 'img'],
        ['aria-label', label],
        ['font-family', 'Libre Franklin, system-ui, sans-serif'],
    ])}><title>${label}</title>${style}${body}</svg>`;
}

/** Cores usadas pela faixa da OG quando a paleta nao manda a escala. */
export function roleStrip(roles: PaletteRoleColors): string[] {
    return [
        roles.primary,
        roles.secondary,
        roles.accent,
        roles.surface,
        roles.background,
        roles.text,
    ];
}
