import { flushPromises, mount } from '@vue/test-utils';
import { defineComponent, h, nextTick } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { buildPaletteScale, parseColor, type Rgba } from '@/lib/color';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import { paletteOgPng, type PaletteOgSource } from '@/lib/paletteOg';
import { rolesFromExtracted, rolesFromScaleItems } from '@/lib/palettePayload';
import PaletteEditor from '@/pages/dashboard/colors/PaletteEditor.vue';
import type {
    PaletteEditor as PaletteEditorData,
    PaletteEditorProps,
    PaletteScaleItem,
} from '@/types/colors';

/**
 * Editor de uma paleta salva (F4, ADR-123).
 *
 * O `GeneratorWorkspace` real tem teste proprio; aqui ele e trocado por um
 * stub que devolve o snapshot montado a partir das props recebidas. O que se
 * confere e a fiacao da pagina: a escala congelada e a extracao remontada
 * chegam ao workspace, o PATCH leva o payload completo e o arquivamento
 * congela a tela ate restaurar.
 */

const { routerMock, pageState, workspaceProps } = vi.hoisted(() => ({
    routerMock: {
        get: vi.fn(),
        post: vi.fn(),
        patch: vi.fn(),
        delete: vi.fn(),
    },
    pageState: {
        url: '/dashboard/colors/palettes/01KPALETTE00000000000000001/edit',
        props: {
            localeSlug: 'pt-br',
            auth: { user: { id: 1 } },
        },
    },
    workspaceProps: { current: null as Record<string, unknown> | null },
}));

vi.mock('@inertiajs/vue3', () => ({
    router: routerMock,
    usePage: () => pageState,
    Head: defineComponent({
        name: 'InertiaHead',
        props: { title: { type: String, default: '' } },
        render: () => h('div'),
    }),
    Link: defineComponent({
        name: 'InertiaLink',
        props: { href: { type: String, default: '' } },
        setup(props, { slots, attrs }) {
            return () =>
                h(
                    'a',
                    { ...attrs, href: props.href },
                    slots.default ? slots.default() : [],
                );
        },
    }),
}));

/**
 * Stub do workspace: guarda as props que o editor passou e devolve o snapshot
 * com a escala/extraida reconstruida, como o componente real faria.
 */
vi.mock('@/tools/palette-generator/GeneratorWorkspace.vue', async () => {
    const { computed, defineComponent, h } = await import('vue');
    const { Tabs, TabsList } = await import('@/components/ui/tabs');

    interface StubExtracted {
        colors: { hex: string }[];
    }

    return {
        default: defineComponent({
            name: 'GeneratorWorkspaceStub',
            props: {
                image: { type: Object, required: true },
                initialBase: { type: String, default: null },
                initialScale: { type: Object, default: null },
                initialExtracted: { type: Object, default: null },
                initialTab: { type: String, default: '' },
            },
            setup(props, { expose, slots }) {
                workspaceProps.current = props as unknown as Record<
                    string,
                    unknown
                >;

                function snapshot() {
                    const extracted = props.initialExtracted as
                        | StubExtracted
                        | null;

                    if (extracted !== null) {
                        return {
                            kind: 'extracted',
                            tab: 'extraction',
                            isEmpty: false,
                            base: extracted.colors[0]?.hex ?? '#000000',
                            scale: props.initialScale,
                            extracted,
                        };
                    }

                    return {
                        kind: 'scale',
                        tab: props.initialTab,
                        isEmpty: false,
                        base: props.initialBase,
                        scale: props.initialScale,
                        extracted: null,
                    };
                }

                expose({
                    snapshot,
                    isEmpty: computed(() => false),
                    activeTab: computed(() => props.initialTab),
                    scale: computed(() => props.initialScale),
                    extracted: computed(() => props.initialExtracted),
                    rgba: computed(() => null),
                });

                // O workspace real envolve os slots num `Tabs` do reka-ui; o
                // stub reproduz isso para a aba extra (Prototipo) montar.
                return () =>
                    h(
                        Tabs,
                        { modelValue: props.initialTab || 'scale' },
                        {
                            default: () => [
                                h(
                                    TabsList,
                                    {},
                                    {
                                        default: () =>
                                            slots['tabs-extra']?.(),
                                    },
                                ),
                                slots['panels-extra']?.(),
                            ],
                        },
                    );
            },
        }),
    };
});

// A rasterizacao real precisa de canvas; o documento e testado em
// `palette-og.test.ts`. Aqui interessa quando o editor manda gerar e o que
// acontece quando a geracao falha.
vi.mock('@/lib/paletteOg', async (importOriginal) => {
    const actual = await importOriginal<typeof import('@/lib/paletteOg')>();

    return {
        ...actual,
        paletteOgPng: vi.fn(
            async () => new Blob(['png'], { type: 'image/png' }),
        ),
    };
});

const TRADUCOES = {
    colors: {
        list: {
            kind_scale: 'ESCALA',
            kind_extracted: 'DA_IMAGEM',
            badge_public: 'PUBLICA',
            badge_private: 'PRIVADA',
            badge_archived: 'ARQUIVADA',
            preview_alt: 'PREVIA {name}',
        },
        editor: {
            back: 'VOLTAR',
            public_link: 'VER_PAGINA',
            usage: 'USADA {count}',
            usage_zero: 'SEM_USO',
            colors_count: '{count} CORES',
            updated_at: 'ATUALIZADA {date}',
            save: 'SALVAR_ALTERACOES',
            saving: 'SALVANDO',
            archived_title: 'ARQUIVADA_TITULO',
            archived_description: 'ARQUIVADA_DESCRICAO',
            restore: 'RESTAURAR_PALETA',
            error: 'NAO_FOI_POSSIVEL_SALVAR',
        },
        actions: {
            edit: 'EDITAR',
            prototype: 'PROTOTIPO',
            duplicate: 'DUPLICAR',
            share: 'COMPARTILHAR',
            share_unavailable: 'SEM_LINK',
            shared: 'COPIADO',
            archive: 'ARQUIVAR',
            restore: 'RESTAURAR',
            delete: 'EXCLUIR',
        },
        delete: {
            title: 'EXCLUIR_PALETA',
            description: 'SEM_VOLTA',
            label: 'DIGITE_O_NOME',
            confirm: 'CONFIRMAR_EXCLUSAO',
            deleting: 'EXCLUINDO',
            cancel: 'CANCELAR',
            mismatch: 'NAO_CONFERE',
        },
        og: {
            title: 'IMAGEM_DE_PARTILHA',
            generate: 'GERAR_IMAGEM',
            regenerate: 'ATUALIZAR_IMAGEM',
            generating: 'GERANDO_IMAGEM',
            hint: 'DICA_OG',
            error: 'ERRO_OG',
            tagline: '{count} CORES',
            footer: 'FEITO_COM {site}',
        },
        form: {
            name_label: 'NOME',
            name_placeholder: 'NOME_PLACEHOLDER',
            description_label: 'DESCRICAO',
            description_placeholder: 'DESCRICAO_PLACEHOLDER',
            visibility_label: 'VISIBILIDADE',
            visibility_private: 'PRIVADA_F',
            visibility_private_hint: 'DICA_PRIVADA',
            visibility_public: 'PUBLICA_F',
            visibility_public_hint: 'DICA_PUBLICA',
            tags_label: 'TAGS',
            tags_placeholder: 'TAG_PLACEHOLDER',
            tags_hint: '{count}/{max} TAGS ATE {length}',
            tags_limit: 'LIMITE_TAGS {max}',
            tags_too_long: 'TAG_LONGA {length}',
            tags_remove: 'REMOVER {name}',
            tags_suggestions: 'SUGESTOES',
            errors: {
                name_required: 'NOME_OBRIGATORIO',
                name_too_long: 'NOME_LONGO {max}',
                description_too_long: 'DESCRICAO_LONGA {max}',
            },
        },
        prototype: {
            tab: 'PROTOTIPO',
            title: 'PROTOTIPO_TITULO',
            description: 'PROTOTIPO_DESCRICAO',
            template_label: 'TEMPLATE',
            template_site: 'SITE',
            template_mobile: 'CELULAR',
            theme_label: 'TEMA',
            theme_light: 'CLARO',
            theme_dark: 'ESCURO',
            preview_label: 'PREVIA {template}',
            roles_title: 'PAPEIS_TITULO',
            roles_description: 'PAPEIS_DESCRICAO',
            roles_restore: 'RESTAURAR_PADROES',
            role_primary: 'PRIMARY',
            role_secondary: 'SECONDARY',
            role_accent: 'ACCENT',
            role_background: 'BACKGROUND',
            role_surface: 'SURFACE',
            role_text: 'TEXT',
            role_text_muted: 'TEXT_MUTED',
            role_border: 'BORDER',
            download_svg: 'BAIXAR_SVG',
            download_png_1x: 'BAIXAR_PNG_1X',
            download_png_2x: 'BAIXAR_PNG_2X',
            copy_svg: 'COPIAR_SVG',
            copied: 'SVG_COPIADO',
            png_error: 'PNG_ERRO',
            sample: {
                brand: 'Acme',
                nav_1: 'Produto',
                nav_2: 'Recursos',
                nav_3: 'Precos',
                heading: 'Exemplo',
                body: 'Exemplo',
                primary: 'Comecar',
                secondary: 'Ver mais',
                link_1: 'Docs',
                link_2: 'Novidades',
                link_3: 'Suporte',
                section: 'Time',
                card_1: 'Rapido',
                card_2: 'Consistente',
                card_3: 'Acessivel',
                card_body: 'Exemplo',
                card_link: 'Saiba mais',
                form_title: 'Equipe',
                form_label: 'Email',
                form_placeholder: "voce{'@'}empresa.com",
                form_action: 'Convidar',
                footer: 'Rodape',
                tab_1: 'Inicio',
                tab_2: 'Busca',
                tab_3: 'Perfil',
                tagline: 'Exemplo',
            },
        },
    },
};

function i18n() {
    const instance = createI18nInstance();

    applyTranslations(instance, {
        locale: 'pt-BR',
        translations: TRADUCOES,
    });

    return instance;
}

function colorOf(input: string): Rgba {
    const parsed = parseColor(input);

    if (!parsed.ok) {
        throw new Error(`cor de teste invalida: ${input}`);
    }

    return parsed.color.rgba;
}

const SCALE = buildPaletteScale(colorOf('#6366f1'));

const SCALE_ITEMS: PaletteScaleItem[] = SCALE.tones.map((tone) => ({
    hex: tone.hex,
    tone: tone.tone,
}));

const IMAGE_LIMITS = {
    maxBytes: 10 * 1024 * 1024,
    maxSide: 4096,
    maxPixels: 16_000_000,
    analysisSide: 512,
    fetchTimeoutMs: 8000,
};

const EXTRACTED_HEXES = [
    '#ff0000',
    '#ff7f00',
    '#ffff00',
    '#00ff00',
    '#0000ff',
];

const EXTRACTED_ITEMS = EXTRACTED_HEXES.map((hex) => ({ hex }));

function editor(
    overrides: Partial<PaletteEditorData> = {},
): PaletteEditorData {
    return {
        id: '01KPALETTE00000000000000001',
        name: 'Oceano',
        description: 'Azuis profundos',
        visibility: 'private',
        kind: 'scale',
        algorithm_version: 1,
        colors: { base: '#6366f1', items: SCALE_ITEMS },
        roles: rolesFromScaleItems(SCALE_ITEMS),
        source: null,
        colors_count: 11,
        duplications_count: 0,
        archived: false,
        archived_at: null,
        slug: null,
        has_og: false,
        tags: [{ id: 'tag-1', name: 'web' }],
        updated_at: '2026-11-10T12:00:00+00:00',
        ...overrides,
    };
}

function props(overrides: Partial<PaletteEditorData> = {}): PaletteEditorProps {
    return {
        palette: editor(overrides),
        tags: [{ id: 'tag-1', name: 'web' }],
        limits: { limit: 25, used: 3, remaining: 22 },
        form: {
            nameMaxLength: 80,
            descriptionMaxLength: 500,
            tagsPerPalette: 10,
            tagNameMaxLength: 30,
        },
        image: IMAGE_LIMITS,
        library: {
            authenticated: true,
            anonymousHistoryLimit: 20,
            anonymousFavoritesLimit: 50,
            historyPerPage: 100,
            favoritesPerPage: 100,
            importBatch: 200,
        },
    };
}

function mountEditor(overrides: Partial<PaletteEditorData> = {}) {
    return mount(PaletteEditor, {
        props: props(overrides),
        global: { plugins: [i18n()] },
        attachTo: document.body,
    });
}

function query<T extends HTMLElement>(selector: string): T {
    const element = document.querySelector<T>(selector);

    if (element === null) {
        throw new Error(`elemento ausente: ${selector}`);
    }

    return element;
}

async function typeName(value: string): Promise<void> {
    const input = query<HTMLInputElement>('[data-test="palette-meta-name"]');

    input.value = value;
    input.dispatchEvent(new Event('input'));
    await nextTick();
}

async function save(): Promise<void> {
    // O ref do workspace e atribuido no patch de montagem; o atributo
    // `disabled` (que vem de `canSave`) so acompanha no proximo flush.
    await nextTick();

    query<HTMLButtonElement>('[data-test="palette-editor-save"]').click();
    await nextTick();
}

function patchBody(): Record<string, unknown> {
    const call = routerMock.patch.mock.calls[0] as [
        string,
        Record<string, unknown>,
    ];

    return call[1];
}

describe('PaletteEditor', () => {
    beforeEach(() => {
        routerMock.get.mockReset();
        routerMock.post.mockReset();
        routerMock.patch.mockReset();
        routerMock.delete.mockReset();
        // O upload da OG precisa terminar para o dedupe do composable nao
        // vazar entre testes (o `Set` e do modulo).
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
        pageState.url =
            '/dashboard/colors/palettes/01KPALETTE00000000000000001/edit';
        workspaceProps.current = null;
        document.body.innerHTML = '';
    });

    it('entrega a escala congelada ao workspace', async () => {
        mountEditor();

        const received = workspaceProps.current as {
            initialBase: string;
            initialScale: {
                tones: { hex: string; tone: number }[];
            } | null;
            initialExtracted: unknown;
            initialTab: string;
        };

        expect(query('[data-test="palette-editor-name"]').textContent).toContain(
            'Oceano',
        );
        expect(received.initialBase).toBe('#6366f1');
        expect(received.initialExtracted).toBeNull();
        expect(received.initialTab).toBe('scale');
        expect(received.initialScale?.tones).toHaveLength(11);
        expect(received.initialScale?.tones[0]?.hex).toBe(
            SCALE_ITEMS[0]?.hex,
        );
        expect(received.initialScale?.tones[10]?.hex).toBe(
            SCALE_ITEMS[10]?.hex,
        );
    });

    it('salva a escala com os HEX persistidos e os metadados editados', async () => {
        mountEditor();

        await typeName('Oceano Novo');
        await save();

        expect(routerMock.patch).toHaveBeenCalledTimes(1);
        expect(routerMock.patch.mock.calls[0]?.[0]).toBe(
            '/dashboard/colors/palettes/01KPALETTE00000000000000001',
        );

        const body = patchBody();

        expect(body).toMatchObject({
            name: 'Oceano Novo',
            description: 'Azuis profundos',
            visibility: 'private',
            kind: 'scale',
            algorithm_version: 1,
            source: null,
            tags: ['web'],
        });

        const colors = body.colors as {
            base: string;
            items: PaletteScaleItem[];
        };

        expect(colors.base).toBe('#6366f1');
        expect(colors.items).toEqual(SCALE_ITEMS);

        const roles = body.roles as {
            light: Record<string, string>;
            dark: Record<string, string>;
        };
        const tone600 = SCALE_ITEMS.find((item) => item.tone === 600);

        expect(roles.light.primary).toBe(tone600?.hex);
        expect(Object.keys(roles.light)).toHaveLength(8);
        expect(Object.keys(roles.dark)).toHaveLength(8);
    });

    it('abre a extraída na aba de extração e salva as cores gravadas', async () => {
        mountEditor({
            kind: 'extracted',
            colors: { base: '#ff0000', items: EXTRACTED_ITEMS },
            roles: rolesFromExtracted('#ff0000', EXTRACTED_HEXES),
            source: { kind: 'file', label: 'foto.png' },
            colors_count: 5,
        });

        const received = workspaceProps.current as {
            initialScale: unknown;
            initialTab: string;
            initialExtracted: {
                colors: { hex: string; weight: number; percentage: number }[];
                requested: number;
                source: { kind: string; label?: string };
            } | null;
        };

        expect(received.initialScale).toBeNull();
        expect(received.initialTab).toBe('extraction');
        expect(received.initialExtracted?.colors.map((color) => color.hex)).toEqual(
            EXTRACTED_HEXES,
        );
        expect(received.initialExtracted?.colors[0]?.percentage).toBe(0);
        expect(received.initialExtracted?.requested).toBe(5);
        expect(received.initialExtracted?.source).toEqual({
            kind: 'file',
            label: 'foto.png',
        });

        await save();

        expect(patchBody()).toMatchObject({
            kind: 'extracted',
            algorithm_version: 1,
            source: { kind: 'file', label: 'foto.png' },
            colors: { base: '#ff0000', items: EXTRACTED_ITEMS },
        });
    });

    it('congela a tela quando a paleta está arquivada', async () => {
        mountEditor({
            archived: true,
            archived_at: '2026-11-10T13:00:00+00:00',
            slug: null,
        });

        expect(
            document.querySelector('[data-test="workspace-stub"]'),
        ).toBeNull();
        expect(
            document.querySelector('[data-test="palette-meta-form"]'),
        ).toBeNull();
        expect(
            document.querySelector('[data-test="palette-editor-save"]'),
        ).toBeNull();

        const archived = query('[data-test="palette-editor-archived"]');

        expect(archived.textContent).toContain('ARQUIVADA_TITULO');
        expect(archived.textContent).toContain('RESTAURAR');
    });

    it('devolve os erros do servidor para o campo e para o aviso', async () => {
        routerMock.patch.mockImplementation(
            (
                _url: string,
                _body: unknown,
                options: { onError: (errors: Record<string, string>) => void },
            ) => {
                options.onError({
                    name: 'Nome em uso.',
                    palette: 'Limite atingido.',
                });
            },
        );

        mountEditor();

        await save();

        const html = document.body.innerHTML;

        expect(html).toContain('Nome em uso.');
        expect(html).toContain('Limite atingido.');
    });

    it('bloqueia o envio com o nome vazio', async () => {
        mountEditor();

        await typeName('   ');
        await save();

        expect(routerMock.patch).not.toHaveBeenCalled();
        expect(document.body.innerHTML).toContain('NOME_OBRIGATORIO');
    });

    it('liga a página pública só para paleta pública com slug', async () => {
        mountEditor({ visibility: 'public', slug: 'oceano' });

        const link = query<HTMLAnchorElement>(
            '[data-test="palette-editor-public"]',
        );

        expect(link.getAttribute('href')).toBe('/pt-br/colors/p/oceano');
    });

    it('segue a aba pedida na query quando ela existe', async () => {
        pageState.url =
            '/dashboard/colors/palettes/01KPALETTE00000000000000001/edit?tab=harmonies';

        mountEditor();

        expect(
            (workspaceProps.current as { initialTab: string }).initialTab,
        ).toBe('harmonies');
    });

    it('abre a aba Prototipo e monta o painel pela query', async () => {
        pageState.url =
            '/dashboard/colors/palettes/01KPALETTE00000000000000001/edit?tab=prototype';

        mountEditor();
        await nextTick();

        expect(
            (workspaceProps.current as { initialTab: string }).initialTab,
        ).toBe('prototype');
        expect(
            document.querySelector('[data-test="prototype-panel"]'),
        ).not.toBeNull();
        expect(
            document.querySelector('[data-test="prototype-preview"]'),
        ).not.toBeNull();
    });

    it('leva no PATCH os papeis editados na aba Prototipo', async () => {
        pageState.url =
            '/dashboard/colors/palettes/01KPALETTE00000000000000001/edit?tab=prototype';

        mountEditor();
        await nextTick();

        const field = query<HTMLInputElement>(
            '[data-test="role-field-primary-text"]',
        );

        field.value = '#22c55e';
        field.dispatchEvent(new Event('input'));
        field.dispatchEvent(new Event('change'));
        await nextTick();

        await save();

        const roles = patchBody().roles as {
            light: Record<string, string>;
            dark: Record<string, string>;
        };
        const darkDefault = rolesFromScaleItems(SCALE_ITEMS).dark;

        expect(roles.light.primary).toBe('#22c55e');
        // O papel editado nao vaza para o outro tema.
        expect(roles.dark).toEqual(darkDefault);
    });

    it('restaura os papeis padrao do tema em edicao', async () => {
        pageState.url =
            '/dashboard/colors/palettes/01KPALETTE00000000000000001/edit?tab=prototype';

        mountEditor();
        await nextTick();

        const field = query<HTMLInputElement>(
            '[data-test="role-field-primary-text"]',
        );

        field.value = '#22c55e';
        field.dispatchEvent(new Event('input'));
        field.dispatchEvent(new Event('change'));
        await nextTick();

        query<HTMLButtonElement>(
            '[data-test="role-editor-restore"]',
        ).click();
        await nextTick();

        await save();

        const roles = patchBody().roles as {
            light: Record<string, string>;
        };
        const tone600 = SCALE_ITEMS.find((item) => item.tone === 600);

        expect(roles.light.primary).toBe(tone600?.hex);
    });

    it('gera a imagem de compartilhamento de uma paleta publica sem OG', async () => {
        mountEditor({ visibility: 'public', slug: 'oceano', has_og: false });

        await flushPromises();

        expect(routerMock.post).toHaveBeenCalledTimes(1);

        const [url, data] = routerMock.post.mock.calls[0] as [
            string,
            { image: File },
        ];

        expect(url).toBe(
            '/dashboard/colors/palettes/01KPALETTE00000000000000001/og',
        );
        expect(data.image).toBeInstanceOf(File);
        expect(data.image.type).toBe('image/png');
        expect(
            document.querySelector('[data-test="og-image"]'),
        ).not.toBeNull();
    });

    it('refaz a imagem ao salvar uma paleta publica, com o payload salvo', async () => {
        routerMock.patch.mockImplementation(
            (
                _url: string,
                _body: unknown,
                options?: { onSuccess?: () => void },
            ) => {
                options?.onSuccess?.();
            },
        );

        mountEditor({ visibility: 'public', slug: 'oceano', has_og: true });

        await flushPromises();

        // Ja existe OG: nada e gerado so por abrir.
        expect(routerMock.post).not.toHaveBeenCalled();

        await typeName('Oceano Novo');
        await save();
        await flushPromises();

        expect(routerMock.post).toHaveBeenCalledTimes(1);

        const [source] = vi.mocked(paletteOgPng).mock.calls[0] ?? [];

        expect((source as PaletteOgSource).name).toBe('Oceano Novo');
    });

    it('falha na imagem nao desfaz o salvamento da paleta', async () => {
        routerMock.patch.mockImplementation(
            (
                _url: string,
                _body: unknown,
                options?: { onSuccess?: () => void },
            ) => {
                options?.onSuccess?.();
            },
        );
        vi.mocked(paletteOgPng).mockRejectedValueOnce(
            new Error('canvas indisponivel'),
        );

        mountEditor({ visibility: 'public', slug: 'oceano', has_og: true });

        await save();
        await flushPromises();

        expect(routerMock.patch).toHaveBeenCalledTimes(1);
        expect(routerMock.post).not.toHaveBeenCalled();
        expect(
            document.querySelector('[data-test="og-image-error"]'),
        ).not.toBeNull();
    });
});
