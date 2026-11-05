import {
    isCustomLogoSlug,
    NO_LOGO,
    normalizeBadge,
    type BadgeFormState,
} from '../builder';
import type {
    BadgeForgeCustomProvider,
    BadgeForgeCustomProviderDefinition,
    BadgeForgeToolProps,
} from '../types';
import { DEFAULT_FONT } from './fonts';
import { cosmeticParams, type BadgeFormat } from './url';

/**
 * URL do endpoint de provider customizado (F7, ADR-087).
 *
 * Espelha `CustomPathParams` + a montagem do controller: o path precisa ter
 * exatamente um segmento por `pathParam`, com os mesmos caracteres aceitos
 * pelo servidor. A origem fica no servidor; aqui so se monta a URL publica
 * opaca (`/api/badges/custom/{ulid}/{metrica}/{path}.{svg|png}`). O TTL e do
 * servidor, entao `cacheSeconds` nunca entra.
 */

export interface CustomProviderCosmetics {
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

export interface CustomProviderFormState extends CustomProviderCosmetics {
    provider: string;
    definition: string;
    params: Record<string, string>;
}

export interface BuildCustomProviderUrlOptions {
    format?: BadgeFormat;
    download?: boolean;
}

const SEGMENT_PATTERN = /^[A-Za-z0-9._~-]+$/;

export function findCustomProvider(
    props: BadgeForgeToolProps,
    id: string,
): BadgeForgeCustomProvider | undefined {
    return props.customProviders.find((provider) => provider.id === id);
}

export function findCustomDefinition(
    provider: BadgeForgeCustomProvider | undefined,
    slug: string,
): BadgeForgeCustomProviderDefinition | undefined {
    return provider?.definitions.find((definition) => definition.slug === slug);
}

/**
 * Exemplo do dono (`natanfiuza/devtools`) quebrado na ordem dos params. O
 * cadastro garante um exemplo com exatamente N segmentos.
 */
export function exampleCustomParams(
    provider: BadgeForgeCustomProvider,
): Record<string, string> {
    const parts = provider.examplePath.split('/');
    const params: Record<string, string> = {};

    provider.pathParams.forEach((name, index) => {
        params[name] = parts[index] ?? '';
    });

    return params;
}

export function defaultCustomProviderForm(
    props: BadgeForgeToolProps,
): CustomProviderFormState {
    const provider = props.customProviders[0];
    const definition = provider?.definitions[0];

    return {
        provider: provider?.id ?? '',
        definition: definition?.slug ?? '',
        params: provider === undefined ? {} : exampleCustomParams(provider),
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
 * Valida os params contra o contrato de path (espelho de `CustomPathParams`).
 * Devolve o nome do primeiro invalido ou `null`.
 */
export function invalidCustomParam(
    provider: BadgeForgeCustomProvider,
    params: Record<string, string>,
    maxLength: number,
): string | null {
    for (const name of provider.pathParams) {
        const value = (params[name] ?? '').trim();

        if (
            value === '' ||
            value === '.' ||
            value === '..' ||
            Array.from(value).length > maxLength ||
            !SEGMENT_PATTERN.test(value)
        ) {
            return name;
        }
    }

    return null;
}

export function buildCustomBadgeUrl(
    origin: string,
    endpoint: string,
    provider: BadgeForgeCustomProvider,
    definition: BadgeForgeCustomProviderDefinition,
    params: Record<string, string>,
    cosmetics: CustomProviderCosmetics,
    props: BadgeForgeToolProps,
    options: BuildCustomProviderUrlOptions = {},
): string {
    if (
        invalidCustomParam(
            provider,
            params,
            props.limits.customProviders.paramMaxLength,
        ) !== null
    ) {
        return '';
    }

    const segments = provider.pathParams.map((name) =>
        encodeURIComponent((params[name] ?? '').trim()),
    );

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

    if (options.download === true) {
        query.push('download=1');
    }

    const base = endpoint.replace(/\/+$/, '');
    const format = options.format ?? 'svg';
    const path = [
        base,
        provider.id,
        definition.slug,
        segments.join('/'),
    ].join('/');

    const suffix = query.length > 0 ? `?${query.join('&')}` : '';

    return `${origin.replace(/\/+$/, '')}${path}.${format}${suffix}`;
}
