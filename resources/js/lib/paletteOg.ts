import { buildPrototype } from '@/tools/palette-generator/prototype/defaults';
import { prototypePngFromDocument } from '@/tools/palette-generator/prototype/exportPrototype';
import type {
    PrototypeCopy,
    PrototypeDocument,
} from '@/tools/palette-generator/prototype/types';
import type {
    PaletteColors,
    PalettePayload,
    PaletteRoles,
    PaletteTheme,
} from '@/types/colors';

/**
 * Cartao social (OG) de uma paleta publica (F4, ADR-125).
 *
 * O desenho e o template `og` do renderizador de prototipos, alimentado com o
 * nome, a descricao e as cores efetivas da paleta. O PNG sai em 1200x630, o
 * tamanho que o upload exige no servidor, e a fonte vai embutida para o cartao
 * ser identico fora do app.
 *
 * O tema e sempre o claro: a imagem aparece como previa em fundos dos mais
 * variados, e o cartao claro mantem o contraste da faixa de cores.
 */

export const OG_THEME: PaletteTheme = 'light';

/**
 * O SVG nao quebra linha. O corte por caracteres e uma aproximacao das
 * metricas da Libre Franklin, suficiente para o nome (64 px/700) e a tagline
 * (22 px/400) nao passarem da margem de 80 px dos dois lados.
 */
export const OG_NAME_MAX_CHARS = 30;
export const OG_TAGLINE_MAX_CHARS = 80;
export const OG_BRAND_MAX_CHARS = 24;
export const OG_FOOTER_MAX_CHARS = 60;

export type PaletteOgSource = {
    name: string;
    description: string | null;
    colors: PaletteColors;
    roles: PaletteRoles;
};

/** Textos que nao saem da paleta; quem chama resolve o idioma. */
export type PaletteOgTexts = {
    brand: string;
    tagline: string;
    footer: string;
};

/** Corta por caracteres e marca o corte com reticencias. */
export function truncateText(value: string, max: number): string {
    const trimmed = value.trim();

    if (trimmed.length <= max) {
        return trimmed;
    }

    return `${trimmed.slice(0, Math.max(0, max - 1)).trimEnd()}…`;
}

/**
 * Fonte a partir do payload recem-salvo: depois do PATCH as props do editor
 * ainda mostram o estado anterior, e a OG precisa refletir o que foi gravado.
 */
export function paletteOgSourceFromPayload(
    payload: PalettePayload,
): PaletteOgSource {
    return {
        name: payload.name,
        description: payload.description,
        colors: payload.colors,
        roles: payload.roles,
    };
}

/** As cores efetivas, na ordem gravada (escala inteira ou extracao). */
export function paletteOgColors(source: PaletteOgSource): string[] {
    return source.colors.items.map((item) => item.hex);
}

/**
 * Texto do cartao. So quatro campos do template `og` sao usados; os demais
 * ficam vazios para o renderizador ignorar.
 */
export function paletteOgCopy(
    source: PaletteOgSource,
    texts: PaletteOgTexts,
): PrototypeCopy {
    return {
        brand: truncateText(texts.brand, OG_BRAND_MAX_CHARS),
        nav: ['', '', ''],
        heading: truncateText(source.name, OG_NAME_MAX_CHARS),
        body: '',
        primaryAction: '',
        secondaryAction: '',
        links: ['', '', ''],
        sectionTitle: '',
        cards: ['', '', ''],
        cardBody: '',
        cardLink: '',
        formTitle: '',
        formLabel: '',
        formPlaceholder: '',
        formAction: '',
        footer: truncateText(texts.footer, OG_FOOTER_MAX_CHARS),
        tabs: ['', '', ''],
        tagline: truncateText(
            source.description ?? texts.tagline,
            OG_TAGLINE_MAX_CHARS,
        ),
    };
}

/** O documento 1200x630 que a exportacao PNG rasteriza. */
export function paletteOgDocument(
    source: PaletteOgSource,
    texts: PaletteOgTexts,
): PrototypeDocument {
    return buildPrototype({
        kind: 'og',
        theme: OG_THEME,
        roles: source.roles[OG_THEME],
        copy: paletteOgCopy(source, texts),
        label: truncateText(source.name, OG_NAME_MAX_CHARS),
        colors: paletteOgColors(source),
    });
}

/** PNG em 1x (1200x630) do mesmo documento, com a fonte embutida. */
export async function paletteOgPng(
    source: PaletteOgSource,
    texts: PaletteOgTexts,
): Promise<Blob> {
    return prototypePngFromDocument(paletteOgDocument(source, texts), 1);
}
