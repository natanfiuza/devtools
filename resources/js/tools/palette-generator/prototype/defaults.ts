import type { PaletteRoleColors, PaletteTheme } from '@/types/colors';
import { ogNodes } from './templates/og';
import { mobileNodes } from './templates/mobile';
import { siteNodes } from './templates/site';
import type {
    PrototypeCopy,
    PrototypeDocument,
    PrototypeDocumentKind,
    PrototypeSize,
    PrototypeTemplate,
} from './types';

/**
 * Catalogo dos prototipos e montagem do documento (F4, ADR-126).
 *
 * Os tamanhos sao fixos e publicos: o site e 1200x900, o celular 390x844 (tela
 * de referencia) e a OG 1200x630 (o servidor valida exatamente essa medida).
 */

export const PROTOTYPE_TEMPLATES: readonly PrototypeTemplate[] = [
    'site',
    'mobile',
];

export const PROTOTYPE_SIZES: Record<PrototypeDocumentKind, PrototypeSize> = {
    site: { width: 1200, height: 900 },
    mobile: { width: 390, height: 844 },
    og: { width: 1200, height: 630 },
};

export type PrototypeBuildInput = {
    kind: PrototypeDocumentKind;
    theme: PaletteTheme;
    roles: PaletteRoleColors;
    copy: PrototypeCopy;
    /** Nome acessivel do SVG, ja traduzido por quem chama. */
    label: string;
    /** Cores da paleta para a faixa da OG; ignorado pelos templates. */
    colors?: string[];
};

/** Documento do prototipo: primitivas na ordem em que sao desenhadas. */
export function buildPrototype(input: PrototypeBuildInput): PrototypeDocument {
    const size = PROTOTYPE_SIZES[input.kind];
    const nodes =
        input.kind === 'site'
            ? siteNodes(input.roles, input.copy)
            : input.kind === 'mobile'
              ? mobileNodes(input.roles, input.copy)
              : ogNodes(input.roles, input.copy, input.colors);

    return {
        kind: input.kind,
        theme: input.theme,
        width: size.width,
        height: size.height,
        label: input.label,
        nodes,
    };
}
