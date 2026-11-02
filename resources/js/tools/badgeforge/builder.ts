import { parseColorOr } from './lib/color';
import { DEFAULT_FONT, fontAsset } from './lib/fonts';
import { styleById } from './lib/styles';
import { logoAsset } from './logos';
import type { BadgeForgeToolProps, BadgeSpec, BadgeStyle } from './types';

/**
 * Estado do formulario e normalizacao (espelho de `BadgeRequest`).
 *
 * Os campos numericos nascem como texto (vazio = default do estilo/config),
 * mas `v-model` em `<input type="number">` entrega `number`; por isso o estado
 * e `string | number` e a normalizacao aceita os dois. A validacao e tolerante
 * como a do servidor - entrada invalida cai no padrao, nunca quebra.
 */

export interface BadgeFormState {
    label: string;
    message: string;
    style: string;
    font: string;
    color: string;
    labelColor: string;
    logo: string;
    logoColor: string;
    logoSize: string | number;
    logoPosition: 'left' | 'right';
    link: string;
    cacheSeconds: string | number;
}

/** Valor do select quando nao ha logo (reka-ui recusa item de valor vazio). */
export const NO_LOGO = 'none';

export function defaultForm(props: BadgeForgeToolProps): BadgeFormState {
    return {
        label: '',
        message: '',
        style: props.defaults.style,
        font: DEFAULT_FONT,
        color: props.defaults.color,
        labelColor: props.defaults.labelColor,
        logo: NO_LOGO,
        logoColor: props.defaults.logoColor,
        logoSize: '',
        logoPosition: props.defaults.logoPosition,
        link: '',
        cacheSeconds: '',
    };
}

/** Exemplo pronto do botao "Exemplo". */
export function sampleForm(props: BadgeForgeToolProps): BadgeFormState {
    return {
        ...defaultForm(props),
        label: 'build',
        message: 'passing',
        logo: 'github',
    };
}

export function normalizeBadge(
    form: BadgeFormState,
    props: BadgeForgeToolProps,
): BadgeSpec {
    const style = styleById(form.style);

    const label = truncate(
        style.uppercase ? form.label.toUpperCase() : form.label,
        props.limits.maxTextLength,
    );

    const message = truncate(
        style.uppercase ? form.message.toUpperCase() : form.message,
        props.limits.maxTextLength,
    );

    return {
        label,
        message,
        style,
        font: fontAsset(form.font, props),
        labelColor:
            style.labelColor ??
            parseColorOr(form.labelColor, props.defaults.labelColor),
        messageColor:
            style.messageColor ??
            parseColorOr(form.color, props.defaults.color),
        logo:
            form.logo === '' || form.logo === NO_LOGO
                ? null
                : logoAsset(form.logo),
        logoColor: parseColorOr(form.logoColor, props.defaults.logoColor),
        logoSize: integer(
            form.logoSize,
            style.logoSize,
            props.limits.logoSize.min,
            props.limits.logoSize.max,
        ),
        logoPosition: form.logoPosition === 'right' ? 'right' : 'left',
        link: normalizeLink(form.link, props.limits.linkMaxLength),
        cacheSeconds: integer(
            form.cacheSeconds,
            props.limits.cacheSeconds.default,
            props.limits.cacheSeconds.min,
            props.limits.cacheSeconds.max,
        ),
    };
}

/** Trunca por code point, com reticencias - como `BadgeRequest::truncate`. */
export function truncate(text: string, limit: number): string {
    if (limit <= 0) {
        return '';
    }

    const characters = Array.from(text);

    if (characters.length <= limit) {
        return text;
    }

    return `${characters.slice(0, limit - 1).join('')}…`;
}

/** Estilo que ignora `color`/`labelColor` (hoje so o social). */
export function locksColors(style: BadgeStyle): boolean {
    return style.labelColor !== null || style.messageColor !== null;
}

/** `integer()` do `BadgeRequest`: so digitos; invalido cai no default; clamp. */
function integer(
    raw: string | number,
    fallback: number,
    min: number,
    max: number,
): number {
    const text = typeof raw === 'number' ? String(raw) : raw.trim();
    const value = /^\d{1,9}$/.test(text) ? Number(text) : fallback;

    return Math.max(min, Math.min(max, value));
}

/** `link()` do `BadgeRequest`: so http(s) com host, dentro do limite. */
export function normalizeLink(raw: string, maxLength: number): string | null {
    const value = raw.trim();

    if (value === '' || Array.from(value).length > maxLength) {
        return null;
    }

    if (!/^https?:\/\/[^\s"'<>]+$/i.test(value)) {
        return null;
    }

    try {
        return new URL(value).hostname === '' ? null : value;
    } catch {
        return null;
    }
}
