import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import SavePaletteDialog from '@/components/colors/SavePaletteDialog.vue';
import { buildPaletteScale, parseColor, type Rgba } from '@/lib/color';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import type { PaletteSaveSource } from '@/lib/paletteSave';
import type { ExtractedPalette } from '@/tools/palette-generator/lib/extractedPalette';
import type { PaletteRoles, PaletteToolProps } from '@/types/colors';

/**
 * Modal de salvar a paleta (F4, ADR-123).
 *
 * Recebe a escala (ou a extracao com a base resolvida) e so acrescenta os
 * metadados. O corpo do POST e montado aqui e enviado por `router.post`; a
 * cota e a validacao final sao do servidor, e o teste confere que os erros
 * dele voltam para o campo certo.
 */

const { postMock } = vi.hoisted(() => ({ postMock: vi.fn() }));

vi.mock('@inertiajs/vue3', () => ({
    router: { post: postMock },
}));

function colorOf(input: string): Rgba {
    const parsed = parseColor(input);

    if (!parsed.ok) {
        throw new Error(`cor de teste invalida: ${input}`);
    }

    return parsed.color.rgba;
}

const SCALE = buildPaletteScale(colorOf('#6366f1'));

const SCALE_SOURCE: PaletteSaveSource = { kind: 'scale', scale: SCALE };

const EXTRACTED: ExtractedPalette = {
    colors: [
        {
            rgba: colorOf('#ff0000'),
            hex: '#ff0000',
            weight: 3,
            percentage: 75,
        },
        {
            rgba: colorOf('#0000ff'),
            hex: '#0000ff',
            weight: 1,
            percentage: 25,
        },
    ],
    requested: 5,
    algorithmVersion: 1,
    sufficient: false,
    visiblePixels: 4,
    source: { kind: 'file', label: 'foto.png' },
};

const EXTRACTED_SOURCE: PaletteSaveSource = {
    kind: 'extracted',
    palette: EXTRACTED,
    base: '#ff0000',
};

const IMPORTED_ROLES: PaletteRoles = {
    light: {
        primary: '#101010',
        secondary: '#202020',
        accent: '#303030',
        background: '#fefefe',
        surface: '#f8f8f8',
        text: '#050505',
        text_muted: '#606060',
        border: '#dddddd',
    },
    dark: {
        primary: '#a0a0a0',
        secondary: '#909090',
        accent: '#808080',
        background: '#050505',
        surface: '#151515',
        text: '#fafafa',
        text_muted: '#b0b0b0',
        border: '#333333',
    },
};

const PALETTE: PaletteToolProps = {
    nameMaxLength: 80,
    descriptionMaxLength: 500,
    tagsPerPalette: 10,
    tagNameMaxLength: 30,
    sourceLabelMaxLength: 80,
    tags: [{ id: 'tag-web', name: 'web' }],
};

function i18n() {
    const instance = createI18nInstance();

    applyTranslations(instance, {
        locale: 'pt-BR',
        translations: {
            colors: {
                form: {
                    title: 'Salvar paleta',
                    description: 'Escolha um nome.',
                    name_label: 'Nome',
                    name_placeholder: 'Oceano',
                    description_label: 'Descrição',
                    description_placeholder: 'Uma paleta de azuis.',
                    visibility_label: 'Visibilidade',
                    visibility_private: 'Privada',
                    visibility_private_hint: 'Só você pode vê-la.',
                    visibility_public: 'Pública',
                    visibility_public_hint: 'Ela ganha uma página pública.',
                    tags_label: 'Tags',
                    tags_placeholder: 'Adicionar uma tag',
                    tags_hint: '{count}/{max} tags · até {length} caracteres',
                    tags_limit: 'Você atingiu o limite de tags ({max}).',
                    tags_too_long: 'Uma tag pode ter até {length} caracteres.',
                    tags_remove: 'Remover {name}',
                    tags_suggestions: 'Suas tags:',
                    insufficient:
                        'A imagem tinha menos cores que o pedido; a paleta é salva com {found}.',
                    submit: 'Salvar paleta',
                    saving: 'Salvando…',
                    cancel: 'Cancelar',
                    errors: {
                        name_required: 'Dê um nome à paleta.',
                        name_too_long: 'O nome pode ter até {max} caracteres.',
                        description_too_long:
                            'A descrição pode ter até {max} caracteres.',
                    },
                },
            },
        },
    });

    return instance;
}

async function mountDialog(source: PaletteSaveSource | null = SCALE_SOURCE) {
    const wrapper = mount(SavePaletteDialog, {
        props: { open: true, source, palette: PALETTE },
        global: { plugins: [i18n()] },
        attachTo: document.body,
    });

    // O conteudo do Dialog vai por teleport e so entra no DOM no proximo tick.
    await nextTick();

    return wrapper;
}

function query<T extends HTMLElement>(selector: string): T {
    const element = document.querySelector<T>(selector);

    if (element === null) {
        throw new Error(`elemento ausente: ${selector}`);
    }

    return element;
}

async function typeName(value: string): Promise<void> {
    const input = query<HTMLInputElement>('[data-test="save-name"]');

    input.value = value;
    input.dispatchEvent(new Event('input'));
    await nextTick();
}

async function submit(): Promise<void> {
    query('[data-test="palette-save-dialog"] form').dispatchEvent(
        new Event('submit', { bubbles: true, cancelable: true }),
    );
    await nextTick();
}

describe('SavePaletteDialog', () => {
    beforeEach(() => {
        postMock.mockReset();
        document.body.innerHTML = '';
    });

    it('mostra os campos com a visibilidade privada por padrão', async () => {
        await mountDialog();

        expect(
            query('[data-test="palette-save-dialog"]').textContent,
        ).toContain('Salvar paleta');
        expect(
            query('[data-test="save-visibility-private"]').getAttribute(
                'aria-checked',
            ),
        ).toBe('true');
        expect(
            query('[data-test="save-visibility-public"]').getAttribute(
                'aria-checked',
            ),
        ).toBe('false');
    });

    it('não envia sem nome e aponta o erro', async () => {
        await mountDialog();

        await submit();

        expect(postMock).not.toHaveBeenCalled();
        expect(
            query('[data-test="palette-save-dialog"]').textContent,
        ).toContain('Dê um nome à paleta.');
    });

    it('envia a escala com visibilidade pública e tags', async () => {
        await mountDialog();

        await typeName('Oceano');
        query<HTMLButtonElement>(
            '[data-test="save-visibility-public"]',
        ).click();
        await nextTick();

        const tagField = query<HTMLInputElement>(
            '[data-test="tag-input-field"]',
        );

        tagField.value = 'web';
        tagField.dispatchEvent(new Event('input'));
        await nextTick();
        tagField.dispatchEvent(
            new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }),
        );
        await nextTick();

        await submit();

        expect(postMock).toHaveBeenCalledTimes(1);

        const [url, body] = postMock.mock.calls[0] as [
            string,
            Record<string, unknown>,
        ];

        expect(url).toBe('/dashboard/colors/palettes');
        expect(body).toMatchObject({
            name: 'Oceano',
            visibility: 'public',
            kind: 'scale',
            tags: ['web'],
        });
        expect((body.colors as { items: unknown[] }).items).toHaveLength(11);
    });

    it('usa os papéis importados no lugar dos defaults', async () => {
        await mountDialog({
            kind: 'scale',
            scale: SCALE,
            roles: IMPORTED_ROLES,
        });

        await typeName('Oceano');
        await submit();

        expect(postMock).toHaveBeenCalledTimes(1);

        const [, body] = postMock.mock.calls[0] as [
            string,
            Record<string, unknown>,
        ];

        expect(body.roles).toEqual(IMPORTED_ROLES);
    });

    it('salva a extraída insuficiente com aviso', async () => {
        await mountDialog(EXTRACTED_SOURCE);

        expect(query('[data-test="save-insufficient"]').textContent).toContain(
            '2',
        );

        await typeName('Foto');
        await submit();

        expect(postMock).toHaveBeenCalledTimes(1);
        expect(postMock.mock.calls[0]?.[1]).toMatchObject({
            kind: 'extracted',
            colors: { base: '#ff0000' },
            source: { kind: 'file', label: 'foto.png' },
        });
    });

    it('devolve os erros do servidor para o campo', async () => {
        postMock.mockImplementation(
            (
                _url: string,
                _body: unknown,
                options: { onError: (errors: Record<string, string>) => void },
            ) => {
                options.onError({
                    name: 'Nome já usado.',
                    palette: 'Você atingiu o limite de paletas.',
                });
            },
        );

        await mountDialog();

        await typeName('Oceano');
        await submit();

        const html = document.body.innerHTML;

        expect(html).toContain('Nome já usado.');
        expect(html).toContain('Você atingiu o limite de paletas.');
    });

    it('cancelar emite close e limpa o formulário ao reabrir', async () => {
        const wrapper = await mountDialog();

        await typeName('Oceano');

        query<HTMLButtonElement>('[data-test="save-cancel"]').click();
        await nextTick();

        expect(wrapper.emitted('close')).toHaveLength(1);

        await wrapper.setProps({ open: false });
        await wrapper.setProps({ open: true });
        await nextTick();

        expect(query<HTMLInputElement>('[data-test="save-name"]').value).toBe(
            '',
        );
    });

    it('mantém o envio bloqueado sem fonte', async () => {
        await mountDialog(null);

        await typeName('Oceano');
        await submit();

        expect(postMock).not.toHaveBeenCalled();
    });
});
