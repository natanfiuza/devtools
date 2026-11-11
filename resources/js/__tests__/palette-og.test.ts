import { mount } from '@vue/test-utils';
import { defineComponent, h } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { usePaletteOg } from '@/composables/usePaletteOg';
import { buildPaletteScale, parseColor, type Rgba } from '@/lib/color';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import {
    OG_NAME_MAX_CHARS,
    OG_TAGLINE_MAX_CHARS,
    paletteOgDocument,
    paletteOgPng,
    paletteOgSourceFromPayload,
    truncateText,
    type PaletteOgSource,
} from '@/lib/paletteOg';
import { rolesFromScaleItems } from '@/lib/palettePayload';
import type { PalettePayload, PaletteScaleItem } from '@/types/colors';

/**
 * Cartao social da paleta (F4, ADR-125).
 *
 * O documento e puro, entao o desenho e conferido direto: tamanho exato do
 * upload, faixa na ordem gravada, nome/descricao com corte e o tema claro.
 * O envio e testado pelo composable, com o roteador do Inertia dublado: o PNG
 * vira multipart e qualquer falha so acende o aviso.
 */

const { routerMock, pageState } = vi.hoisted(() => ({
    routerMock: {
        get: vi.fn(),
        post: vi.fn(),
        patch: vi.fn(),
        delete: vi.fn(),
    },
    pageState: {
        url: '/dashboard/colors/palettes/01KPALETTE00000000000000001/edit',
        props: { name: 'devtools' },
    },
}));

vi.mock('@inertiajs/vue3', () => ({
    router: routerMock,
    usePage: () => pageState,
}));

// A rasterizacao real precisa de canvas; aqui o que importa e o contrato do
// composable. O documento continua sendo o de verdade.
vi.mock('@/lib/paletteOg', async (importOriginal) => {
    const actual = await importOriginal<typeof import('@/lib/paletteOg')>();

    return {
        ...actual,
        paletteOgPng: vi.fn(
            async () => new Blob(['png'], { type: 'image/png' }),
        ),
    };
});

function colorOf(input: string): Rgba {
    const parsed = parseColor(input);

    if (!parsed.ok) {
        throw new Error(`cor de teste invalida: ${input}`);
    }

    return parsed.color.rgba;
}

const SCALE = buildPaletteScale(colorOf('#6366f1'));

const ITEMS: PaletteScaleItem[] = SCALE.tones.map((tone) => ({
    hex: tone.hex,
    tone: tone.tone,
}));

const ROLES = rolesFromScaleItems(ITEMS);

const SOURCE: PaletteOgSource = {
    name: 'Oceano',
    description: 'Azuis calmos para paineis.',
    colors: { base: '#6366f1', items: ITEMS },
    roles: ROLES,
};

const TEXTS = {
    brand: 'devtools',
    tagline: '11 cores',
    footer: 'Feito com devtools',
};

function textsOf(doc: ReturnType<typeof paletteOgDocument>): string[] {
    return doc.nodes
        .filter((node) => node.kind === 'text')
        .map((node) => (node.kind === 'text' ? node.text : ''));
}

describe('Cartao social: documento', () => {
    it('desenha 1200x630 com a faixa na ordem gravada', () => {
        const doc = paletteOgDocument(SOURCE, TEXTS);

        expect(doc.kind).toBe('og');
        expect(doc.theme).toBe('light');
        expect(doc.width).toBe(1200);
        expect(doc.height).toBe(630);

        const strip = doc.nodes.filter((node) => node.section === 'palette');

        expect(strip).toHaveLength(11);
        expect(strip.map((node) => node.fill)).toEqual(
            ITEMS.map((item) => item.hex),
        );

        const canvas = doc.nodes[0];

        expect(canvas?.kind).toBe('rect');

        if (canvas?.kind === 'rect') {
            expect(canvas.fill).toBe(ROLES.light.background);
        }
    });

    it('usa nome e descricao da paleta', () => {
        const texts = textsOf(paletteOgDocument(SOURCE, TEXTS));

        expect(texts).toContain('Oceano');
        expect(texts).toContain('Azuis calmos para paineis.');
        expect(texts).toContain('devtools');
        expect(texts).toContain('Feito com devtools');
    });

    it('cai no texto traduzido quando a paleta nao tem descricao', () => {
        const texts = textsOf(
            paletteOgDocument({ ...SOURCE, description: null }, TEXTS),
        );

        expect(texts).toContain('11 cores');
    });

    it('corta nome e descricao para caber na margem', () => {
        const texts = textsOf(
            paletteOgDocument(
                {
                    ...SOURCE,
                    name: 'Uma paleta de nome muito comprido que nao cabe',
                    description: 'x'.repeat(200),
                },
                TEXTS,
            ),
        );

        const heading = texts.find((text) =>
            text.startsWith('Uma paleta de nome'),
        );
        const tagline = texts.find((text) => text.startsWith('xxx'));

        expect(heading?.length).toBe(OG_NAME_MAX_CHARS);
        expect(heading?.endsWith('…')).toBe(true);
        expect(tagline?.length).toBe(OG_TAGLINE_MAX_CHARS);
        expect(tagline?.endsWith('…')).toBe(true);
    });

    it('monta a fonte a partir do payload salvo', () => {
        const payload: PalettePayload = {
            name: 'Oceano Novo',
            description: null,
            visibility: 'public',
            kind: 'scale',
            algorithm_version: 1,
            colors: { base: '#6366f1', items: ITEMS },
            roles: ROLES,
            source: null,
            tags: [],
        };

        expect(paletteOgSourceFromPayload(payload)).toEqual({
            name: 'Oceano Novo',
            description: null,
            colors: payload.colors,
            roles: ROLES,
        });
    });
});

describe('Cartao social: truncamento', () => {
    it('mantem texto curto e apara espacos', () => {
        expect(truncateText('  Oceano  ', 30)).toBe('Oceano');
    });

    it('corta com reticencias sem passar do limite', () => {
        const value = truncateText('a'.repeat(50), 12);

        expect(value).toHaveLength(12);
        expect(value.endsWith('…')).toBe(true);
        expect(value.startsWith('aaaaaaaaaa')).toBe(true);
    });
});

const TRADUCOES = {
    colors: {
        og: {
            tagline: '{count} cores',
            footer: 'Feito com {site}',
        },
    },
};

function i18n() {
    const instance = createI18nInstance();

    applyTranslations(instance, { locale: 'pt-BR', translations: TRADUCOES });

    return instance;
}

type OgState = ReturnType<typeof usePaletteOg>;

function mountOg(source: PaletteOgSource | null = SOURCE): OgState {
    const holder = { state: null as unknown as OgState };
    const Host = defineComponent({
        setup() {
            holder.state = usePaletteOg(() => 'PALETTE1', () => source);

            return () => h('div');
        },
    });

    mount(Host, { global: { plugins: [i18n()] } });

    return holder.state;
}

describe('Cartao social: envio', () => {
    beforeEach(() => {
        routerMock.post.mockReset();
        routerMock.post.mockImplementation(
            (
                _url: string,
                _data: unknown,
                options?: { onSuccess?: () => void },
            ) => {
                options?.onSuccess?.();
            },
        );
        vi.mocked(paletteOgPng).mockReset();
        vi.mocked(paletteOgPng).mockImplementation(
            async () => new Blob(['png'], { type: 'image/png' }),
        );
    });

    it('envia o PNG como multipart e marca como enviado', async () => {
        const state = mountOg();

        await expect(state.generate()).resolves.toBe(true);

        expect(routerMock.post).toHaveBeenCalledTimes(1);

        const [url, data] = routerMock.post.mock.calls[0] as [
            string,
            { image: File },
        ];

        expect(url).toBe('/dashboard/colors/palettes/PALETTE1/og');
        expect(data.image).toBeInstanceOf(File);
        expect(data.image.type).toBe('image/png');
        expect(data.image.name).toBe('palette-og-PALETTE1.png');

        expect(state.uploaded.value).toBe(true);
        expect(state.busy.value).toBe(false);
        expect(state.error.value).toBe(false);
    });

    it('usa os textos traduzidos na geracao', async () => {
        const state = mountOg();

        await state.generate();

        expect(paletteOgPng).toHaveBeenCalledWith(SOURCE, {
            brand: 'devtools',
            tagline: '11 cores',
            footer: 'Feito com devtools',
        });
    });

    it('aceita a fonte do payload recem-salvo', async () => {
        const state = mountOg();
        const override: PaletteOgSource = {
            ...SOURCE,
            name: 'Nova base',
        };

        await state.generate(override);

        expect(paletteOgPng).toHaveBeenCalledWith(
            override,
            expect.objectContaining({ tagline: '11 cores' }),
        );
    });

    it('acende o aviso sem derrubar a pagina quando o upload falha', async () => {
        routerMock.post.mockImplementationOnce(
            (
                _url: string,
                _data: unknown,
                options?: { onError?: () => void },
            ) => {
                options?.onError?.();
            },
        );

        const state = mountOg();

        await expect(state.generate()).resolves.toBe(false);

        expect(state.error.value).toBe(true);
        expect(state.uploaded.value).toBe(false);
        expect(state.busy.value).toBe(false);
    });

    it('acende o aviso quando o PNG nem chega a ser gerado', async () => {
        vi.mocked(paletteOgPng).mockRejectedValueOnce(
            new Error('canvas indisponivel'),
        );

        const state = mountOg();

        await expect(state.generate()).resolves.toBe(false);

        expect(state.error.value).toBe(true);
        expect(routerMock.post).not.toHaveBeenCalled();
    });

    it('nao envia duas vezes a mesma paleta ao mesmo tempo', async () => {
        const state = mountOg();

        const [first, second] = await Promise.all([
            state.generate(),
            state.generate(),
        ]);

        expect(first).toBe(true);
        expect(second).toBe(false);
        expect(routerMock.post).toHaveBeenCalledTimes(1);
    });
});
