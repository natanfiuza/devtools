import { reactive } from 'vue';
import { show as showLogoRoute } from '@/routes/badgeforge/logos';
import type {
    BadgeForgeCustomLogoSummary,
    BadgeLogo,
    BadgeLogoPath,
} from './types';

/**
 * Cache em memoria dos paths de logos personalizados (ADR-078).
 *
 * As props levam so metadados; os paths sao buscados uma unica vez em
 * `badgeforge.logos.show`, na selecao explicita do logo ou ao aplicar um
 * preset. O mapa e reativo para o preview acompanhar a chegada do SVG sem
 * nova digitacao. Nada aqui persiste entre recarregamentos.
 */
const cache = reactive<Record<string, BadgeLogo>>({});

/** Requisicoes em andamento: selecionar o mesmo logo duas vezes nao repete o GET. */
const pending = new Map<string, Promise<BadgeLogo | null>>();

export function memoizeCustomLogo(logo: BadgeLogo): void {
    cache[logo.slug.toLowerCase()] = logo;
}

export function customLogoAsset(slug: string | null): BadgeLogo | null {
    if (slug === null) {
        return null;
    }

    return cache[slug.toLowerCase().trim()] ?? null;
}

export function forgetCustomLogo(slug: string): void {
    delete cache[slug.toLowerCase().trim()];
}

export function clearCustomLogos(): void {
    for (const slug of Object.keys(cache)) {
        delete cache[slug];
    }

    pending.clear();
}

/**
 * Busca os paths de um logo personalizado sob demanda.
 *
 * E a unica requisicao que o builder faz alem das acoes de conta: o endpoint
 * publico de imagem continua sendo consumido apenas pelo snippet (ADR-079).
 * Falha de rede, 404 e resposta fora do contrato viram `null` - o badge segue
 * sem logo ate o usuario tentar de novo.
 */
export function loadCustomLogo(
    summary: BadgeForgeCustomLogoSummary,
): Promise<BadgeLogo | null> {
    const slug = summary.slug.toLowerCase().trim();
    const cached = cache[slug];

    if (cached !== undefined) {
        return Promise.resolve(cached);
    }

    const running = pending.get(slug);

    if (running !== undefined) {
        return running;
    }

    const request = fetch(showLogoRoute.url({ badgeCustomLogo: summary.id }), {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
    })
        .then(async (response) => {
            if (!response.ok) {
                return null;
            }

            const logo = toLogo(slug, await response.json());

            if (logo !== null) {
                memoizeCustomLogo(logo);
            }

            return logo;
        })
        .catch(() => null)
        .finally(() => {
            pending.delete(slug);
        });

    pending.set(slug, request);

    return request;
}

/** Revalida a resposta do servidor antes de entrar no preview. */
function toLogo(slug: string, payload: unknown): BadgeLogo | null {
    if (typeof payload !== 'object' || payload === null) {
        return null;
    }

    const data = payload as { viewBox?: unknown; paths?: unknown };

    if (
        typeof data.viewBox !== 'string' ||
        !Array.isArray(data.paths) ||
        data.paths.length === 0
    ) {
        return null;
    }

    const paths: BadgeLogoPath[] = [];

    for (const item of data.paths) {
        if (typeof item !== 'object' || item === null) {
            return null;
        }

        const path = item as { d?: unknown; fillRule?: unknown; clipRule?: unknown };

        if (typeof path.d !== 'string' || path.d === '') {
            return null;
        }

        paths.push({
            d: path.d,
            fillRule: rule(path.fillRule),
            clipRule: rule(path.clipRule),
        });
    }

    return { slug, viewBox: data.viewBox, paths, custom: true };
}

function rule(value: unknown): 'nonzero' | 'evenodd' | null {
    return value === 'nonzero' || value === 'evenodd' ? value : null;
}
