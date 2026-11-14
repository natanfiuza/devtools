import { shallowMount } from '@vue/test-utils';
import { nextTick } from 'vue';
import { describe, expect, it, vi } from 'vitest';
import {
    PALETTE_TONES,
    parseColor,
    type PaletteScale,
    type Rgba,
} from '@/lib/color';
import { buildFrozenScale } from '@/lib/frozenScale';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import { PALETTE_ROLES } from '@/lib/palettePayload';
import { paletteSignature } from '@/lib/paletteSave';
import type { PaletteDraft } from '@/lib/saveDraft';
import GeneratorWorkspace from '@/tools/palette-generator/GeneratorWorkspace.vue';
import type { ExtractedColor } from '@/tools/palette-generator/lib/extractContract';
import type { ExtractedPalette } from '@/tools/palette-generator/lib/extractedPalette';
import type { ImportedPalette } from '@/tools/palette-generator/lib/tokenImport';
import type { PaletteRoleColors, PaletteRoles } from '@/types/colors';

/**
 * Workspace recebendo uma importacao de tokens (F5, Etapa 6).
 *
 * O parser e os formatos tem teste proprio; aqui o que importa e o efeito no
 * estado: a escala importada entra congelada e sobrevive ao watcher da base, a
 * extraida entra na aba certa, trocar a base descongela, e os papeis
 * importados so continuam enquanto a assinatura do snapshot bater.
 */

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({
        url: '/pt-br/tools/colors/palette-generator',
        props: { auth: { user: null, isAdmin: false } },
    }),
    router: { visit: vi.fn(), post: vi.fn() },
}));

const IMAGE_LIMITS = {
    maxBytes: 10 * 1024 * 1024,
    maxSide: 4096,
    maxPixels: 16_000_000,
    analysisSide: 512,
    fetchTimeoutMs: 8000,
};

function colorOf(input: string): Rgba {
    const parsed = parseColor(input);

    if (!parsed.ok) {
        throw new Error(`cor de teste invalida: ${input}`);
    }

    return parsed.color.rgba;
}

function roleColors(hex: string): PaletteRoleColors {
    return Object.fromEntries(
        PALETTE_ROLES.map((role) => [role, hex]),
    ) as PaletteRoleColors;
}

const ROLES: PaletteRoles = {
    light: roleColors('#101010'),
    dark: roleColors('#f0f0f0'),
};

/**
 * Escala impossivel de ser gerada pelo algoritmo (todos os tons iguais e
 * versao 7): qualquer recalculada seria diferente, o que prova o congelamento.
 */
const IMPORTED_SCALE: PaletteScale = buildFrozenScale({
    base: '#aa1122',
    items: PALETTE_TONES.map((tone) => ({ tone, hex: '#123456' })),
    algorithmVersion: 7,
    clippedTones: [50, 950],
});

const IMPORTED_SCALE_PALETTE: ImportedPalette = {
    format: 'native',
    kind: 'scale',
    base: '#aa1122',
    scale: IMPORTED_SCALE,
    extracted: null,
    roles: ROLES,
    tokenCount: 11,
    name: 'Aurora',
};

function extractedColor(hex: string, weight: number): ExtractedColor {
    return {
        rgba: colorOf(hex),
        hex,
        weight,
        percentage: weight * 10,
    };
}

const IMPORTED_EXTRACTED: ExtractedPalette = {
    colors: [
        extractedColor('#ff0000', 3),
        extractedColor('#00ff00', 2),
        extractedColor('#0000ff', 1),
        extractedColor('#ffff00', 0),
        extractedColor('#ff00ff', 0),
    ],
    requested: 5,
    algorithmVersion: 2,
    sufficient: true,
    visiblePixels: 0,
    source: { kind: 'file', label: 'tokens.json' },
};

const IMPORTED_EXTRACTED_PALETTE: ImportedPalette = {
    format: 'dtcg',
    kind: 'extracted',
    base: '#ff0000',
    scale: null,
    extracted: IMPORTED_EXTRACTED,
    roles: ROLES,
    tokenCount: 5,
    name: 'Foto',
};

function i18n() {
    const instance = createI18nInstance();

    applyTranslations(instance, {
        locale: 'pt-BR',
        translations: {
            tools: {
                'palette-generator': {
                    base: { title: 'Cor base' },
                    tabs: {
                        scale: 'Escala',
                        harmonies: 'Harmonias',
                        extraction: 'Extração',
                        library: 'Biblioteca',
                    },
                },
            },
        },
    });

    return instance;
}

function mountWorkspace() {
    return shallowMount(GeneratorWorkspace, {
        props: { image: IMAGE_LIMITS },
        global: {
            plugins: [i18n()],
            // Os filhos tem testes proprios; aqui so o corpo do workspace.
            renderStubDefaultSlot: false,
        },
    });
}

type WorkspaceVm = {
    applyImported: (palette: ImportedPalette) => void;
    applyBaseHex: (hex: string) => boolean;
    restoreDraft: (draft: PaletteDraft) => void;
    draft: () => {
        kind: 'scale' | 'extracted';
        base: string;
        scale: { items: { tone: number; hex: string }[] } | null;
        roles: PaletteRoles | null;
    };
    snapshot: () => {
        kind: 'scale' | 'extracted';
        base: string;
        scale: PaletteScale;
    };
    scale: PaletteScale;
    activeTab: string;
    roles: PaletteRoles | undefined;
    baseHex: () => string;
};

function vmOf(
    wrapper: ReturnType<typeof mountWorkspace>,
): WorkspaceVm {
    return wrapper.vm as unknown as WorkspaceVm;
}

describe('GeneratorWorkspace · importar tokens', () => {
    it('congela a escala importada e muda a base sem recalcular', async () => {
        const wrapper = mountWorkspace();
        const vm = vmOf(wrapper);

        vm.applyImported(IMPORTED_SCALE_PALETTE);
        await nextTick();

        expect(vm.baseHex()).toBe('#aa1122');
        expect(vm.scale.algorithmVersion).toBe(7);
        expect(vm.scale.tones.map((tone) => tone.hex)).toEqual(
            IMPORTED_SCALE.tones.map((tone) => tone.hex),
        );
        expect(vm.scale.clippedTones).toEqual([50, 950]);
        expect(vm.snapshot().kind).toBe('scale');
        expect(vm.snapshot().base.toLowerCase()).toBe('#aa1122');
    });

    it('mantém os papéis importados enquanto a assinatura não muda', async () => {
        const wrapper = mountWorkspace();
        const vm = vmOf(wrapper);

        vm.applyImported(IMPORTED_SCALE_PALETTE);
        await nextTick();

        expect(vm.roles).toEqual(ROLES);
    });

    it('aplica a extraída na aba de extração com a base entre as cores', async () => {
        const wrapper = mountWorkspace();
        const vm = vmOf(wrapper);

        vm.applyImported(IMPORTED_EXTRACTED_PALETTE);
        await nextTick();

        expect(vm.activeTab).toBe('extraction');
        expect(vm.snapshot().kind).toBe('extracted');
        expect(vm.snapshot().base).toBe('#ff0000');
        expect(vm.roles).toEqual(ROLES);
    });

    it('trocar a base descongela a escala e descarta os papéis importados', async () => {
        const wrapper = mountWorkspace();
        const vm = vmOf(wrapper);

        vm.applyImported(IMPORTED_SCALE_PALETTE);
        await nextTick();

        vm.applyBaseHex('#00ff00');
        await nextTick();

        expect(vm.baseHex()).toBe('#00ff00');
        expect(vm.scale.algorithmVersion).not.toBe(7);
        expect(vm.scale.base).toEqual(colorOf('#00ff00'));
        expect(vm.roles).toBeUndefined();
    });

    it('sair da extração importada também invalida os papéis', async () => {
        const wrapper = mountWorkspace();
        const vm = vmOf(wrapper);

        vm.applyImported(IMPORTED_EXTRACTED_PALETTE);
        await nextTick();

        vm.activeTab = 'scale';
        await nextTick();

        expect(vm.roles).toBeUndefined();
    });

    it('o rascunho leva a escala congelada e os papéis', async () => {
        const wrapper = mountWorkspace();
        const vm = vmOf(wrapper);

        vm.applyImported(IMPORTED_SCALE_PALETTE);
        await nextTick();

        const draft = vm.draft();

        expect(draft.kind).toBe('scale');
        expect(draft.base).toBe('#aa1122');
        expect(draft.scale?.items).toHaveLength(11);
        expect(draft.roles).toEqual(ROLES);
    });

    it('restaurar o rascunho reconstrói a escala congelada e os papéis', async () => {
        const wrapper = mountWorkspace();
        const vm = vmOf(wrapper);

        vm.restoreDraft({
            version: 1,
            savedAt: 1_800_000_000_000,
            kind: 'scale',
            base: '#aa1122',
            extracted: null,
            tab: 'scale',
            scale: {
                items: IMPORTED_SCALE.tones.map((tone) => ({
                    tone: tone.tone,
                    hex: tone.hex,
                })),
                algorithmVersion: 7,
                clippedTones: [50, 950],
            },
            roles: ROLES,
        });
        await nextTick();

        expect(vm.baseHex()).toBe('#aa1122');
        expect(vm.scale.algorithmVersion).toBe(7);
        expect(vm.scale.tones.map((tone) => tone.hex)).toEqual(
            IMPORTED_SCALE.tones.map((tone) => tone.hex),
        );
        expect(vm.scale.clippedTones).toEqual([50, 950]);
        expect(vm.roles).toEqual(ROLES);
        expect(
            paletteSignature({
                kind: 'scale',
                base: vm.snapshot().base,
                scale: vm.scale,
            }),
        ).toBe(
            paletteSignature({
                kind: 'scale',
                base: '#aa1122',
                scale: IMPORTED_SCALE,
            }),
        );
    });

    it('ignora uma escala importada incompleta sem tocar no estado', async () => {
        const wrapper = mountWorkspace();
        const vm = vmOf(wrapper);
        const before = vm.scale;

        vm.applyImported({ ...IMPORTED_SCALE_PALETTE, scale: null });
        await nextTick();

        expect(vm.scale).toEqual(before);
        expect(vm.baseHex()).toBe('#6366f1');
    });
});
