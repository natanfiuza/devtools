import {
    isCustomLogoSlug,
    normalizeBadge,
    NO_LOGO,
    type BadgeFormState,
} from '../builder';
import type {
    BadgeForgeProvider,
    BadgeForgeProviderDefinition,
    BadgeForgeToolProps,
} from '../types';
import { DEFAULT_FONT } from './fonts';
import { cosmeticParams, type BadgeFormat } from './url';

/**
 * URL do endpoint de provider (F4, ADR-080).
 *
 * O servidor e a autoridade final, mas o construtor valida os mesmos limites
 * antes de montar a URL: um path invalido nunca vira snippet. O modo provider
 * nunca envia `cacheSeconds` - o TTL e do servidor.
 */

export interface ProviderCosmetics {
    style: string;
    font: string;
    color: string;
    labelColor: string;
    logo: string;
    logoColor: string;
    logoSize: string | number;
    logoPosition: 'left' | 'right';
    link: string;
}

export interface ProviderFormState extends ProviderCosmetics {
    provider: string;
    definition: string;
    params: Record<string, string>;
}

export interface BuildProviderUrlOptions {
    format?: BadgeFormat;
    download?: boolean;
}

export function findProvider(
    props: BadgeForgeToolProps,
    slug: string,
): BadgeForgeProvider | undefined {
    return props.providers.find((provider) => provider.slug === slug);
}

export function findDefinition(
    provider: BadgeForgeProvider | undefined,
    slug: string,
): BadgeForgeProviderDefinition | undefined {
    return provider?.definitions.find((definition) => definition.slug === slug);
}

/**
 * Primeiro parametro preenchido com o exemplo do catalogo
 * (`natanfiuza/devtools` -> owner=natanfiuza, repo=devtools).
 */
export function exampleParams(
    definition: BadgeForgeProviderDefinition,
): Record<string, string> {
    const parts = definition.examplePath.split('/');
    const params: Record<string, string> = {};

    definition.params.forEach((param, index) => {
        params[param.name] = param.allowSlash
            ? parts.slice(index).join('/')
            : (parts[index] ?? '');
    });

    return params;
}

export function defaultProviderForm(
    props: BadgeForgeToolProps,
): ProviderFormState {
    const provider = props.providers[0];
    const definition = provider?.definitions[0];

    return {
        provider: provider?.slug ?? '',
        definition: definition?.slug ?? '',
        params: definition === undefined ? {} : exampleParams(definition),
        style: props.defaults.style,
        font: DEFAULT_FONT,
        color: props.defaults.color,
        labelColor: props.defaults.labelColor,
        logo: NO_LOGO,
        logoColor: props.defaults.logoColor,
        logoSize: '',
        logoPosition: props.defaults.logoPosition,
        link: '',
    };
}

/**
 * Valida os params contra o `pattern` da definicao (espelho do
 * `ProviderPathParser`). Devolve o nome do primeiro invalido ou `null`.
 */
export function invalidProviderParam(
    definition: BadgeForgeProviderDefinition,
    params: Record<string, string>,
    maxParamLength: number,
): string | null {
    for (const param of definition.params) {
        const value = (params[param.name] ?? '').trim();

        if (value === '' || Array.from(value).length > maxParamLength) {
            return param.name;
        }

        if (!matchesPattern(param.pattern, value, param.allowSlash)) {
            return param.name;
        }
    }

    return null;
}

export function buildProviderBadgeUrl(
    origin: string,
    endpoint: string,
    provider: BadgeForgeProvider,
    definition: BadgeForgeProviderDefinition,
    params: Record<string, string>,
    cosmetics: ProviderCosmetics,
    props: BadgeForgeToolProps,
    options: BuildProviderUrlOptions = {},
): string {
    const maxParamLength = props.limits.providers.maxParamLength;

    if (invalidProviderParam(definition, params, maxParamLength) !== null) {
        return '';
    }

    const base = endpoint.replace(/\/+$/, '');
    const format = options.format ?? 'svg';
    const segments = [provider.slug, definition.slug];

    if (definition.params.length === 0) {
        // Definicao sem coordenadas: o path da URL e o literal do catalogo
        // (`latest`). Um literal fora do padrao nunca vira snippet (F6).
        const literal = definition.pathPattern.trim();

        if (
            !/^[A-Za-z0-9._-]+$/.test(literal) ||
            literal === '.' ||
            literal === '..'
        ) {
            return '';
        }

        segments.push(literal);
    }

    for (const param of definition.params) {
        const encoded = encodeParam(
            (params[param.name] ?? '').trim(),
            param.allowSlash,
        );

        if (encoded === null) {
            return '';
        }

        segments.push(encoded);
    }

    const spec = normalizeBadge(
        {
            ...cosmetics,
            label: '',
            message: '',
            cacheSeconds: '',
        } satisfies BadgeFormState,
        props,
    );

    const logoSlug = isCustomLogoSlug(cosmetics.logo)
        ? cosmetics.logo.trim().toLowerCase()
        : undefined;

    const query = cosmeticParams(spec, props.defaults, { logoSlug });

    // `?refresh=1` nao existe aqui: a imagem publica e cacheada pelo servidor
    // e o dono atualiza a badge pessoal pelo POST autenticado.
    if (options.download === true) {
        query.push('download=1');
    }

    const path = `${base}/${segments.join('/')}.${format}`;
    const suffix = query.length > 0 ? `?${query.join('&')}` : '';

    return `${origin.replace(/\/+$/, '')}${path}${suffix}`;
}

function encodeParam(value: string, allowSlash: boolean): string | null {
    const segments = allowSlash ? value.split('/') : [value];

    if (
        segments.some(
            (segment) => segment === '' || segment === '.' || segment === '..',
        )
    ) {
        return null;
    }

    return segments.map((segment) => encodeURIComponent(segment)).join('/');
}

/**
 * `pattern` vem do banco e e usado com `^(?:...)$`. Um pattern invalido
 * nunca aceita o valor (o servidor tambem recusaria).
 */
function matchesPattern(
    pattern: string,
    value: string,
    allowSlash: boolean,
): boolean {
    const segments = allowSlash ? value.split('/') : [value];

    let expression: RegExp;

    try {
        expression = new RegExp(`^(?:${pattern})$`);
    } catch {
        return false;
    }

    return segments.every(
        (segment) =>
            segment !== '' &&
            segment !== '.' &&
            segment !== '..' &&
            expression.test(segment),
    );
}
