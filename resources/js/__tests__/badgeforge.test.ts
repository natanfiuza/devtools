import { describe, expect, it } from 'vitest';
import {
    defaultForm,
    normalizeBadge,
    normalizeLink,
    sampleForm,
    truncate,
    type BadgeFormState,
} from '@/tools/badgeforge/builder';
import { parseColor, parseColorOr } from '@/tools/badgeforge/lib/color';
import { textWidth } from '@/tools/badgeforge/lib/metrics';
import { renderBadge } from '@/tools/badgeforge/lib/render';
import {
    htmlSnippet,
    markdownSnippet,
    rstSnippet,
} from '@/tools/badgeforge/lib/snippets';
import { buildBadgeUrl, encodeSegment } from '@/tools/badgeforge/lib/url';
import toolProps from '../../../tests/Fixtures/badgeforge/tool-props.json';
import type { BadgeForgeToolProps } from '@/tools/badgeforge/types';

const PROPS = toolProps as BadgeForgeToolProps;

function form(overrides: Partial<BadgeFormState> = {}): BadgeFormState {
    return {
        ...defaultForm(PROPS),
        label: 'build',
        message: 'passing',
        ...overrides,
    };
}

describe('parseColor', () => {
    it('resolve nomes, aliases, hex, rgb e hsl', () => {
        expect(parseColor('brightgreen')).toEqual({
            hex: '#44cc11',
            alpha: 1,
        });
        expect(parseColor('#fff')).toEqual({ hex: '#ffffff', alpha: 1 });
        expect(parseColor('rgba(0, 126, 198, 0.6)')).toEqual({
            hex: '#007ec6',
            alpha: 0.6,
        });
        expect(parseColor('hsl(0, 100%, 50%)')).toEqual({
            hex: '#ff0000',
            alpha: 1,
        });
        expect(parseColor('transparent')).toEqual({
            hex: '#000000',
            alpha: 0,
        });
    });

    it('devolve null para entrada invalida', () => {
        expect(parseColor('nope')).toBeNull();
        expect(parseColor('rgb(300, 0, 0)')).toBeNull();
        expect(parseColor(42)).toBeNull();
        expect(parseColorOr('nope', '#123456')).toEqual({
            hex: '#123456',
            alpha: 1,
        });
    });
});

describe('textWidth', () => {
    it('conta por code point e ignora glifos de largura zero', () => {
        const plain = textWidth('build', 'sans', 11, 600, 0);
        const withZeroWidth = textWidth('bu​ild', 'sans', 11, 600, 0);

        expect(plain).toBeGreaterThan(0);
        // O ZWSP conta um code point, mas nao soma avanco.
        expect(withZeroWidth).toBe(plain);
    });

    it('soma o tracking por caractere', () => {
        expect(textWidth('ab', 'sans', 10, 700, 1)).toBe(
            textWidth('ab', 'sans', 10, 700, 0) + 2,
        );
    });
});

describe('builder', () => {
    it('parte dos defaults que vem do servidor', () => {
        const state = defaultForm(PROPS);

        expect(state.style).toBe('flat');
        expect(state.color).toBe('#9f9f9f');
        expect(state.labelColor).toBe('#555555');
        expect(state.logoColor).toBe('#ffffff');
        expect(state.logoPosition).toBe('left');
    });

    it('aplica o exemplo pronto', () => {
        const state = sampleForm(PROPS);

        expect(state.label).toBe('build');
        expect(state.message).toBe('passing');
        expect(state.logo).toBe('github');
    });

    it('faz clamp do logoSize e do cacheSeconds', () => {
        expect(
            normalizeBadge(form({ logo: 'github', logoSize: '4' }), PROPS)
                .logoSize,
        ).toBe(8);
        expect(
            normalizeBadge(form({ logo: 'github', logoSize: '100' }), PROPS)
                .logoSize,
        ).toBe(20);
        expect(
            normalizeBadge(form({ logo: 'github', logoSize: 'abc' }), PROPS)
                .logoSize,
        ).toBe(10);
        expect(
            normalizeBadge(
                form({ style: 'for-the-badge', logo: 'github' }),
                PROPS,
            ).logoSize,
        ).toBe(14);
        expect(
            normalizeBadge(form({ cacheSeconds: '100' }), PROPS).cacheSeconds,
        ).toBe(300);
        expect(
            normalizeBadge(form({ cacheSeconds: '999999' }), PROPS)
                .cacheSeconds,
        ).toBe(86400);
        expect(
            normalizeBadge(form({ cacheSeconds: '' }), PROPS).cacheSeconds,
        ).toBe(86400);
    });

    it('aceita numero cru do input type=number e trata NaN/Infinity como vazio', () => {
        expect(
            normalizeBadge(form({ logo: 'github', logoSize: 12 }), PROPS)
                .logoSize,
        ).toBe(12);
        expect(
            normalizeBadge(form({ logo: 'github', logoSize: 4 }), PROPS)
                .logoSize,
        ).toBe(8);
        expect(
            normalizeBadge(form({ logo: 'github', logoSize: 100 }), PROPS)
                .logoSize,
        ).toBe(20);
        expect(
            normalizeBadge(form({ logo: 'github', logoSize: Number.NaN }), PROPS)
                .logoSize,
        ).toBe(10);
        expect(
            normalizeBadge(
                form({ logo: 'github', logoSize: Number.POSITIVE_INFINITY }),
                PROPS,
            ).logoSize,
        ).toBe(10);
        expect(
            normalizeBadge(form({ cacheSeconds: 600 }), PROPS).cacheSeconds,
        ).toBe(600);
        expect(
            normalizeBadge(form({ cacheSeconds: Number.NaN }), PROPS)
                .cacheSeconds,
        ).toBe(86400);
    });

    it('usa caixa alta no for-the-badge', () => {
        const spec = normalizeBadge(form({ style: 'for-the-badge' }), PROPS);

        expect(spec.label).toBe('BUILD');
        expect(spec.message).toBe('PASSING');
    });

    it('trunca por code point com reticencias', () => {
        expect(Array.from(truncate('😀'.repeat(101), 100))).toHaveLength(100);
        expect(truncate('abc', 100)).toBe('abc');
        expect(truncate('x'.repeat(101), 100).endsWith('…')).toBe(true);
    });

    it('cai no padrao para cor invalida e ignora logo desconhecido', () => {
        const spec = normalizeBadge(
            form({ color: 'nope', labelColor: 'nope', logo: 'nope' }),
            PROPS,
        );

        expect(spec.messageColor.hex).toBe('#9f9f9f');
        expect(spec.labelColor.hex).toBe('#555555');
        expect(spec.logo).toBeNull();
    });

    it('forca as cores do social', () => {
        const spec = normalizeBadge(
            form({ style: 'social', color: '#ff0000', labelColor: '#00ff00' }),
            PROPS,
        );

        expect(spec.messageColor.hex).toBe('#007ec6');
        expect(spec.labelColor.hex).toBe('#555555');
    });

    it('so aceita link http(s) com host', () => {
        expect(normalizeLink('https://example.com/x?a=1', 2048)).toBe(
            'https://example.com/x?a=1',
        );
        expect(normalizeLink('javascript:alert(1)', 2048)).toBeNull();
        expect(normalizeLink('ftp://example.com', 2048)).toBeNull();
        expect(
            normalizeLink('https://example.com/' + 'a'.repeat(2048), 2048),
        ).toBeNull();
    });
});

describe('encodeSegment', () => {
    it('escapa underscore, espaco e barra', () => {
        expect(encodeSegment('hello_world')).toBe('hello__world');
        expect(encodeSegment('hello world')).toBe('hello_world');
        expect(encodeSegment('ci/cd')).toBe('ci%252Fcd');
        expect(encodeSegment('1.0.0')).toBe('1.0.0');
        expect(encodeSegment('98%')).toBe('98%25');
    });
});

describe('buildBadgeUrl', () => {
    const origin = 'https://devtools.test';
    const endpoint = PROPS.endpoint;

    it('monta a URL minima quando tudo esta no padrao', () => {
        const spec = normalizeBadge(form(), PROPS);

        expect(buildBadgeUrl(origin, endpoint, spec, PROPS)).toBe(
            'https://devtools.test/api/badges/static/build/passing.svg',
        );
    });

    it('leva so os parametros que diferem do padrao', () => {
        const spec = normalizeBadge(
            form({
                style: 'plastic',
                color: 'brightgreen',
                logo: 'github',
                logoPosition: 'right',
                link: 'https://example.com',
                cacheSeconds: '600',
            }),
            PROPS,
        );

        const url = buildBadgeUrl(origin, endpoint, spec, PROPS);

        expect(url).toBe(
            'https://devtools.test/api/badges/static/build/passing.svg' +
                '?style=plastic&color=%2344cc11&logo=github&logoPosition=right' +
                '&link=https%3A%2F%2Fexample.com&cacheSeconds=600',
        );
    });

    it('omite as cores que o estilo social fixa', () => {
        const spec = normalizeBadge(form({ style: 'social' }), PROPS);

        expect(buildBadgeUrl(origin, endpoint, spec, PROPS)).toBe(
            'https://devtools.test/api/badges/static/build/passing.svg?style=social',
        );
    });

    it('codifica a barra e o espaco no texto', () => {
        const spec = normalizeBadge(
            form({ label: 'ci/cd', message: 'deploy now' }),
            PROPS,
        );

        expect(buildBadgeUrl(origin, endpoint, spec, PROPS)).toContain(
            '/ci%252Fcd/deploy_now.svg',
        );
    });
});

describe('snippets', () => {
    const url = 'https://devtools.test/api/badges/static/build/passing.svg';

    it('monta markdown com o alt escapado', () => {
        expect(markdownSnippet(url, 'build: [ok]\\x')).toBe(
            `![build: \\[ok\\]\\\\x](${url})`,
        );
    });

    it('monta HTML com atributos escapados', () => {
        expect(htmlSnippet(url + '?a=1&b=2', 'a<b>"c"', 98, 20)).toBe(
            `<img src="https://devtools.test/api/badges/static/build/passing.svg?a=1&amp;b=2" alt="a&lt;b&gt;&quot;c&quot;" width="98" height="20" />`,
        );
    });

    it('monta reStructuredText', () => {
        expect(rstSnippet(url, 'build: passing')).toBe(
            `.. image:: ${url}\n   :alt: build: passing`,
        );
    });
});

describe('preview', () => {
    it('renderiza o mesmo spec usado na URL', () => {
        const spec = normalizeBadge(form({ logo: 'github' }), PROPS);
        const svg = renderBadge(spec);

        expect(svg).toContain('<title>build: passing</title>');
        expect(svg).toContain('<path');
    });
});
