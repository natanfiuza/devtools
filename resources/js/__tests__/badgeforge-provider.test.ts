import { describe, expect, it } from 'vitest';
import toolProps from '../../../tests/Fixtures/badgeforge/tool-props.json';
import {
    buildProviderBadgeUrl,
    defaultProviderForm,
    exampleParams,
    findDefinition,
    findProvider,
    invalidProviderParam,
    type ProviderCosmetics,
} from '@/tools/badgeforge/lib/providerUrl';
import { imageSnippet } from '@/tools/badgeforge/lib/snippets';
import type {
    BadgeForgeProvider,
    BadgeForgeProviderDefinition,
    BadgeForgeToolProps,
} from '@/tools/badgeforge/types';

/**
 * URL do endpoint de provider (F4, ADR-080).
 *
 * O construtor monta a mesma URL que o servidor resolve: provider, definicao e
 * os params em segmentos codificados, com os cosmeticos na query. Nada de
 * `cacheSeconds` (o TTL e do servidor) e nada de `refresh` (a imagem publica e
 * cacheada); a previa e uma `<img>` do servidor, nunca um fetch manual.
 */

const PROPS = toolProps as BadgeForgeToolProps;
const ORIGIN = 'https://devtools.test';
const ENDPOINT = PROPS.providerEndpoint;

function cosmetics(
    overrides: Partial<ProviderCosmetics> = {},
): ProviderCosmetics {
    const base = defaultProviderForm(PROPS);

    return {
        style: base.style,
        font: base.font,
        color: base.color,
        labelColor: base.labelColor,
        logo: base.logo,
        logoColor: base.logoColor,
        logoSize: base.logoSize,
        logoPosition: base.logoPosition,
        link: base.link,
        ...overrides,
    };
}

function pair(
    providerSlug: string,
    definitionSlug: string,
): {
    provider: BadgeForgeProvider;
    definition: BadgeForgeProviderDefinition;
} {
    const provider = findProvider(PROPS, providerSlug);
    const definition = findDefinition(provider, definitionSlug);

    if (provider === undefined || definition === undefined) {
        throw new Error(`Metrica ausente no fixture: ${providerSlug}/${definitionSlug}`);
    }

    return { provider, definition };
}

function urlFor(
    providerSlug: string,
    definitionSlug: string,
    params: Record<string, string>,
    overrides: Partial<ProviderCosmetics> = {},
    options: { format?: 'svg' | 'png'; download?: boolean } = {},
): string {
    const { provider, definition } = pair(providerSlug, definitionSlug);

    return buildProviderBadgeUrl(
        ORIGIN,
        ENDPOINT,
        provider,
        definition,
        params,
        cosmetics(overrides),
        PROPS,
        options,
    );
}

describe('buildProviderBadgeUrl', () => {
    it('gera a URL das nove metricas com o exemplo do catalogo', () => {
        const cases: Array<{
            provider: string;
            definition: string;
            path: string;
        }> = [
            { provider: 'github', definition: 'stars', path: 'github/stars/natanfiuza/devtools.svg' },
            { provider: 'github', definition: 'forks', path: 'github/forks/natanfiuza/devtools.svg' },
            { provider: 'github', definition: 'issues', path: 'github/issues/natanfiuza/devtools.svg' },
            { provider: 'github', definition: 'license', path: 'github/license/natanfiuza/devtools.svg' },
            { provider: 'npm', definition: 'v', path: 'npm/v/vue.svg' },
            { provider: 'npm', definition: 'dm', path: 'npm/dm/vue.svg' },
            { provider: 'packagist', definition: 'dt', path: 'packagist/dt/laravel/framework.svg' },
            { provider: 'packagist', definition: 'v', path: 'packagist/v/laravel/framework.svg' },
            { provider: 'docker', definition: 'pulls', path: 'docker/pulls/library/postgres.svg' },
        ];

        for (const item of cases) {
            const { definition } = pair(item.provider, item.definition);
            const url = urlFor(
                item.provider,
                item.definition,
                exampleParams(definition),
            );

            expect(url).toBe(`${ORIGIN}${ENDPOINT}/${item.path}`);
            expect(url).not.toContain('cacheSeconds');
            expect(url).not.toContain('refresh');
            expect(url).not.toContain('token');
        }
    });

    it('preserva a barra do pacote npm com escopo', () => {
        expect(urlFor('npm', 'v', { package: '@vue/shared' })).toBe(
            `${ORIGIN}${ENDPOINT}/npm/v/%40vue/shared.svg`,
        );
    });

    it('manda os cosmeticos na mesma ordem do endpoint estatico', () => {
        const url = urlFor(
            'github',
            'stars',
            { owner: 'natanfiuza', repo: 'devtools' },
            {
                style: 'flat-square',
                font: 'fira-code',
                color: '#ff0000',
                labelColor: '#123456',
                logo: 'github',
                logoColor: '#000000',
                logoSize: '12',
                logoPosition: 'right',
                link: 'https://exemplo.com',
            },
        );

        expect(url).toBe(
            `${ORIGIN}${ENDPOINT}/github/stars/natanfiuza/devtools.svg` +
                '?style=flat-square&font=fira-code&color=%23ff0000' +
                '&labelColor=%23123456&logo=github&logoColor=%23000000' +
                '&logoSize=12&logoPosition=right&link=https%3A%2F%2Fexemplo.com',
        );
        expect(url).not.toContain('cacheSeconds');
    });

    it('aceita o slug do logo personalizado sem buscar os paths', () => {
        const url = urlFor(
            'npm',
            'v',
            { package: 'vue' },
            { logo: 'u_01K0CUSTOMLOGO0000000000001' },
        );

        expect(url).toContain('logo=u_01k0customlogo0000000000001');
    });

    it('troca a extensao e acrescenta download a pedido', () => {
        const url = urlFor(
            'packagist',
            'dt',
            { vendor: 'laravel', package: 'framework' },
            {},
            { format: 'png', download: true },
        );

        expect(url).toBe(
            `${ORIGIN}${ENDPOINT}/packagist/dt/laravel/framework.png?download=1`,
        );
    });

    it('remover a barra final do endpoint evita barra dupla', () => {
        const { provider, definition } = pair('npm', 'v');

        expect(
            buildProviderBadgeUrl(
                `${ORIGIN}/`,
                `${ENDPOINT}/`,
                provider,
                definition,
                { package: 'vue' },
                cosmetics(),
                PROPS,
            ),
        ).toBe(`${ORIGIN}${ENDPOINT}/npm/v/vue.svg`);
    });

    it('nao monta URL com param ausente ou invalido', () => {
        expect(urlFor('github', 'stars', { owner: 'natanfiuza' })).toBe('');
        expect(
            urlFor('github', 'stars', { owner: 'natanfiuza', repo: '../etc' }),
        ).toBe('');
        expect(urlFor('npm', 'v', { package: '@vue/../shared' })).toBe('');
        expect(urlFor('docker', 'pulls', { namespace: 'Library', repository: 'x' })).toBe(
            '',
        );
    });

    it('recusa param acima do limite de comprimento', () => {
        const tooLong = 'a'.repeat(PROPS.limits.providers.maxParamLength + 1);

        expect(urlFor('npm', 'v', { package: tooLong })).toBe('');
    });
});

describe('invalidProviderParam', () => {
    it('aponta o primeiro param invalido', () => {
        const { definition } = pair('packagist', 'dt');

        expect(
            invalidProviderParam(
                definition,
                { vendor: 'laravel', package: 'framework' },
                PROPS.limits.providers.maxParamLength,
            ),
        ).toBeNull();
        expect(
            invalidProviderParam(
                definition,
                { vendor: '', package: 'framework' },
                PROPS.limits.providers.maxParamLength,
            ),
        ).toBe('vendor');
        expect(
            invalidProviderParam(
                definition,
                { vendor: 'laravel', package: 'a/b' },
                PROPS.limits.providers.maxParamLength,
            ),
        ).toBe('package');
    });

    it('rejeita traversal mesmo quando o pattern aceita pontos', () => {
        const { definition } = pair('npm', 'v');

        expect(
            invalidProviderParam(
                definition,
                { package: '..' },
                PROPS.limits.providers.maxParamLength,
            ),
        ).toBe('package');
        expect(
            invalidProviderParam(
                definition,
                { package: '@scope/..' },
                PROPS.limits.providers.maxParamLength,
            ),
        ).toBe('package');
    });
});

describe('exampleParams e defaultProviderForm', () => {
    it('separa owner/repo e vendor/package pelo examplePath', () => {
        expect(exampleParams(pair('github', 'stars').definition)).toEqual({
            owner: 'natanfiuza',
            repo: 'devtools',
        });
        expect(exampleParams(pair('packagist', 'dt').definition)).toEqual({
            vendor: 'laravel',
            package: 'framework',
        });
        expect(exampleParams(pair('npm', 'v').definition)).toEqual({
            package: 'vue',
        });
    });

    it('parte do primeiro provider habilitado do catalogo', () => {
        const form = defaultProviderForm(PROPS);

        expect(form.provider).toBe('github');
        expect(form.definition).toBe('stars');
        expect(form.params).toEqual({
            owner: 'natanfiuza',
            repo: 'devtools',
        });
        expect(form.font).toBe('default');
        expect(form.logo).toBe('none');
    });
});

describe('previa de provider', () => {
    it('usa <img> sem dimensoes fixas, porque o tamanho vem do servidor', () => {
        const html = imageSnippet(
            `${ORIGIN}${ENDPOINT}/github/stars/natanfiuza/devtools.svg`,
            'GitHub stars',
        );

        expect(html).toBe(
            `<img src="${ORIGIN}${ENDPOINT}/github/stars/natanfiuza/devtools.svg" alt="GitHub stars" />`,
        );
        expect(html).not.toContain('width=');
        expect(html).not.toContain('height=');
    });
});
