import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { parseColor, type Rgba } from '@/lib/color';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import { useUnsavedGuard } from '@/tools/layout-harmonizer/composables/useUnsavedGuard';
import SaveLayoutSystemDialog from '@/tools/layout-harmonizer/components/SaveLayoutSystemDialog.vue';
import { exportMessages } from './support/langMessages';
import { generateLayoutSystem } from '@/tools/layout-harmonizer/engine';
import { emptyEditState } from '@/tools/layout-harmonizer/lib/editState';
import {
    layoutStateFingerprint,
    layoutSystemPayload,
    suggestCopyName,
    suggestLayoutSystemName,
    truncateLayoutName,
    type LayoutMessageTranslator,
} from '@/tools/layout-harmonizer/lib/save';
import { buildSnapshot } from '@/tools/layout-harmonizer/lib/snapshot';
import {
    LAYOUT_STATE_VERSION,
    buildLayoutState,
    type LayoutStateInputsV1,
} from '@/tools/layout-harmonizer/lib/state';

/**
 * Salvamento do sistema (ADR-168) e guarda de alteracoes (ADR-169).
 *
 * O modal nao recalcula cor: o corpo carrega o envelope do workspace. A guarda
 * bloqueia navegacao do Inertia e recarregamento, libera o salvamento e repete
 * a URL bloqueada quando o usuario confirma.
 */

const ROUTER = vi.hoisted(() => ({
    handlers: [] as ((event: unknown) => void)[],
    visit: vi.fn(),
    off: vi.fn(),
    post: vi.fn(),
    patch: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    router: {
        on: (_name: string, handler: (event: unknown) => void) => {
            ROUTER.handlers.push(handler);

            return ROUTER.off;
        },
        visit: ROUTER.visit,
        post: ROUTER.post,
        patch: ROUTER.patch,
    },
}));

function color(input: string): Rgba {
    const parsed = parseColor(input);

    if (!parsed.ok) {
        throw new Error(`cor de teste invalida: ${input}`);
    }

    return parsed.color.rgba;
}

const INPUTS: LayoutStateInputsV1 = {
    seedText: '#3b82f6',
    backgroundText: '',
    secondaryText: '',
    strategy: 'analogous',
};

const SNAPSHOT = buildSnapshot(
    generateLayoutSystem({ seed: color('#3b82f6'), strategy: 'analogous' }),
    emptyEditState(),
    'aa',
);

/** Tradutor previsivel: devolve a chave e os argumentos. */
const t: LayoutMessageTranslator = (key, named) =>
    named ? `${key}|${JSON.stringify(named)}` : key;

describe('lib/save', () => {
    it('trunca o nome no limite, sem espaco sobrando', () => {
        expect(truncateLayoutName('  Sistema de layout  ', 80)).toBe(
            'Sistema de layout',
        );
        expect(truncateLayoutName('a'.repeat(90), 80)).toHaveLength(80);
        expect(truncateLayoutName('   ', 80)).toBe('');
    });

    it('sugere o nome a partir da paleta de origem', () => {
        const name = suggestLayoutSystemName({
            originName: 'Oceano',
            strategyLabel: 'Analogica',
            hex: '#3b82f6',
            t,
            maxLength: 80,
        });

        expect(name).toContain('name_from_palette');
        expect(name).toContain('Oceano');
    });

    it('sugere o nome pela estrategia e HEX quando nao ha origem', () => {
        const values: LayoutMessageTranslator = (key, named) =>
            `${key} ${Object.values(named ?? {}).join(' ')}`;

        const name = suggestLayoutSystemName({
            originName: null,
            strategyLabel: 'Analogica',
            hex: '#3b82f6',
            t: values,
            maxLength: 80,
        });

        expect(name).toContain('name_from_strategy');
        expect(name).toContain('#3b82f6');
        expect(name.length).toBeLessThanOrEqual(80);
    });

    it('salvar como novo acrescenta o sufixo de copia e trunca', () => {
        const name = suggestCopyName({
            currentName: 'Sistema do site',
            t: () => ' (copia)',
            maxLength: 14,
        });

        expect(name.endsWith('(copia)')).toBe(true);
        expect(name.length).toBeLessThanOrEqual(14);
    });

    it('monta o corpo com o envelope intacto e sem FK quando nao ha origem', () => {
        const state = buildLayoutState(INPUTS, null, SNAPSHOT);
        const payload = layoutSystemPayload(
            { inputs: INPUTS, secondary: null, snapshot: SNAPSHOT },
            {
                name: '  Sistema do site  ',
                description: '   ',
                visibility: 'public',
                tags: ['site', 'blog'],
            },
        );

        expect(payload.name).toBe('Sistema do site');
        expect(payload.description).toBeNull();
        expect(payload.visibility).toBe('public');
        expect(payload.tags).toEqual(['site', 'blog']);
        expect(payload.state).toEqual(state);
        expect(payload.state.stateVersion).toBe(LAYOUT_STATE_VERSION);
        expect('source_palette_id' in payload).toBe(false);
    });

    it('inclui a FK da origem quando a paleta ainda e acessivel', () => {
        const payload = layoutSystemPayload(
            { inputs: INPUTS, secondary: null, snapshot: SNAPSHOT },
            {
                name: 'Sistema',
                description: 'Descricao',
                visibility: 'private',
                tags: [],
            },
            '01HZYPALETTE',
        );

        expect(payload.description).toBe('Descricao');
        expect(payload.source_palette_id).toBe('01HZYPALETTE');
        expect(payload.tags).toEqual([]);
    });

    it('a impressao digital muda quando o envelope muda', () => {
        const state = buildLayoutState(INPUTS, null, SNAPSHOT);
        const edited = buildLayoutState(
            { ...INPUTS, seedText: '#ff0000' },
            null,
            SNAPSHOT,
        );

        expect(layoutStateFingerprint(null)).toBe('');
        expect(layoutStateFingerprint(state)).toBe(
            layoutStateFingerprint({ ...state }),
        );
        expect(layoutStateFingerprint(state)).not.toBe(
            layoutStateFingerprint(edited),
        );
    });
});

interface FakeBeforeEvent {
    detail: { visit: { url: URL } };
    prevented: boolean;
    preventDefault(): void;
}

function beforeEvent(path: string): FakeBeforeEvent {
    const event: FakeBeforeEvent = {
        detail: { visit: { url: new URL(path, 'http://localhost') } },
        prevented: false,
        preventDefault() {
            event.prevented = true;
        },
    };

    return event;
}

function lastHandler(): (event: unknown) => void {
    return ROUTER.handlers[ROUTER.handlers.length - 1]!;
}

describe('useUnsavedGuard', () => {
    it('sem alteracoes deixa a navegacao passar', () => {
        const guard = useUnsavedGuard({ dirty: () => false });
        const event = beforeEvent('/dashboard');

        lastHandler()(event);

        expect(event.prevented).toBe(false);
        expect(guard.pending.value).toBe(false);

        guard.dispose();
    });

    it('bloqueia a navegacao e repete a URL quando o usuario confirma', () => {
        ROUTER.visit.mockClear();

        const guard = useUnsavedGuard({ dirty: () => true });
        const event = beforeEvent('/dashboard/colors/layout-systems/01HZY');

        lastHandler()(event);

        expect(event.prevented).toBe(true);
        expect(guard.pending.value).toBe(true);

        guard.confirm();

        expect(guard.pending.value).toBe(false);
        expect(ROUTER.visit).toHaveBeenCalledWith(
            'http://localhost/dashboard/colors/layout-systems/01HZY',
        );

        // A repeticao da navegacao nao pode ser bloqueada de novo.
        const repeat = beforeEvent('/dashboard/colors/layout-systems/01HZY');
        lastHandler()(repeat);
        expect(repeat.prevented).toBe(false);

        guard.dispose();
    });

    it('cancelar mantem a pagina e descarta a URL', () => {
        ROUTER.visit.mockClear();

        const guard = useUnsavedGuard({ dirty: () => true });

        lastHandler()(beforeEvent('/dashboard'));
        guard.cancel();

        expect(guard.pending.value).toBe(false);
        expect(ROUTER.visit).not.toHaveBeenCalled();

        guard.confirm();
        expect(ROUTER.visit).not.toHaveBeenCalled();

        guard.dispose();
    });

    it('release libera apenas a proxima navegacao (salvamento)', () => {
        const guard = useUnsavedGuard({ dirty: () => true });

        guard.release();

        const saving = beforeEvent('/dashboard/colors/layout-systems/01HZY');
        lastHandler()(saving);
        expect(saving.prevented).toBe(false);

        const leaving = beforeEvent('/dashboard');
        lastHandler()(leaving);
        expect(leaving.prevented).toBe(true);

        guard.dispose();
    });

    it('uma segunda tentativa enquanto pendente nao troca de URL', () => {
        ROUTER.visit.mockClear();

        const guard = useUnsavedGuard({ dirty: () => true });

        lastHandler()(beforeEvent('/dashboard'));
        lastHandler()(beforeEvent('/outra'));

        guard.confirm();

        expect(ROUTER.visit).toHaveBeenCalledWith('http://localhost/dashboard');

        guard.dispose();
    });

    it('enabled=false desliga a guarda', () => {
        const guard = useUnsavedGuard({
            dirty: () => true,
            enabled: () => false,
        });
        const event = beforeEvent('/dashboard');

        lastHandler()(event);

        expect(event.prevented).toBe(false);

        guard.dispose();
    });

    it('avisa ao fechar/recarregar a aba', () => {
        let guard: ReturnType<typeof useUnsavedGuard> | null = useUnsavedGuard({
            dirty: () => true,
        });

        const dirtyEvent = new Event('beforeunload', { cancelable: true });
        window.dispatchEvent(dirtyEvent);

        expect(dirtyEvent.defaultPrevented).toBe(true);

        guard.dispose();

        guard = useUnsavedGuard({ dirty: () => false });

        const cleanEvent = new Event('beforeunload', { cancelable: true });
        window.dispatchEvent(cleanEvent);

        expect(cleanEvent.defaultPrevented).toBe(false);

        guard.dispose();
    });

    it('dispose remove o listener do router', () => {
        ROUTER.off.mockClear();

        const guard = useUnsavedGuard({ dirty: () => true });

        guard.dispose();

        expect(ROUTER.off).toHaveBeenCalled();
    });
});

function mountDialog(
    props: Partial<{
        mode: 'create' | 'update';
        systemId: string | null;
        suggestedName: string;
        initial: {
            name: string;
            description: string;
            visibility: 'private' | 'public';
            tags: string[];
        } | null;
        originPaletteId: string | null;
    }> = {},
) {
    const instance = createI18nInstance();

    applyTranslations(instance, {
        locale: 'pt_BR',
        translations: exportMessages().pt_BR,
    });

    return mount(SaveLayoutSystemDialog, {
        props: {
            open: true,
            mode: 'create',
            source: { inputs: INPUTS, secondary: null, snapshot: SNAPSHOT },
            suggestedName: 'Sistema de layout',
            limits: {
                nameMaxLength: 80,
                descriptionMaxLength: 500,
                tagsPerSystem: 10,
                tagNameMaxLength: 30,
            },
            accountTags: [],
            ...props,
        },
        global: {
            plugins: [instance],
            stubs: {
                Dialog: { template: '<div><slot /></div>' },
                DialogContent: { template: '<div><slot /></div>' },
                DialogHeader: { template: '<div><slot /></div>' },
                DialogTitle: { template: '<div><slot /></div>' },
                DialogDescription: { template: '<div><slot /></div>' },
                DialogFooter: { template: '<div><slot /></div>' },
            },
        },
    });
}

describe('SaveLayoutSystemDialog', () => {
    it('cria com o envelope do workspace e os metadados do modal', async () => {
        ROUTER.post.mockClear();
        ROUTER.patch.mockClear();

        const wrapper = mountDialog();

        await wrapper.get('form').trigger('submit');

        expect(ROUTER.patch).not.toHaveBeenCalled();
        expect(ROUTER.post).toHaveBeenCalledTimes(1);

        const [url, body] = ROUTER.post.mock.calls[0] as [
            string,
            Record<string, unknown>,
        ];

        expect(url).toBe('/dashboard/colors/layout-systems');
        expect(body).toMatchObject({
            name: 'Sistema de layout',
            description: null,
            visibility: 'private',
            tags: [],
        });
        expect(body.state).toEqual(buildLayoutState(INPUTS, null, SNAPSHOT));
        expect('source_palette_id' in body).toBe(false);
    });

    it('atualiza no editor e avisa o pai no sucesso', async () => {
        ROUTER.post.mockClear();
        ROUTER.patch.mockClear();

        const wrapper = mountDialog({
            mode: 'update',
            systemId: '01HZYSYSTEM',
            initial: {
                name: 'Sistema do site',
                description: 'Descricao',
                visibility: 'public',
                tags: ['site'],
            },
        });

        await wrapper.get('form').trigger('submit');

        expect(ROUTER.post).not.toHaveBeenCalled();
        expect(ROUTER.patch).toHaveBeenCalledTimes(1);

        const [url, body, options] = ROUTER.patch.mock.calls[0] as [
            string,
            Record<string, unknown>,
            { onSuccess: () => void },
        ];

        expect(url).toBe('/dashboard/colors/layout-systems/01HZYSYSTEM');
        expect(body).toMatchObject({
            name: 'Sistema do site',
            description: 'Descricao',
            visibility: 'public',
            tags: ['site'],
        });

        options.onSuccess();

        expect(wrapper.emitted('saved')).toHaveLength(1);
        expect(wrapper.emitted('close')).toHaveLength(1);
    });

    it('salvar como novo envia o sufixo de copia e cria outro item', async () => {
        ROUTER.post.mockClear();
        ROUTER.patch.mockClear();

        const wrapper = mountDialog({
            mode: 'update',
            systemId: '01HZYSYSTEM',
            initial: {
                name: 'Sistema do site',
                description: '',
                visibility: 'private',
                tags: [],
            },
        });

        await wrapper.get('[data-test="layout-save-as-new"]').trigger('click');

        expect(ROUTER.patch).not.toHaveBeenCalled();
        expect(ROUTER.post).toHaveBeenCalledTimes(1);

        const [url, body] = ROUTER.post.mock.calls[0] as [
            string,
            Record<string, unknown>,
        ];

        expect(url).toBe('/dashboard/colors/layout-systems');
        expect(String(body.name)).toContain('Sistema do site');
        expect(String(body.name)).toContain('cópia');
    });

    it('bloqueia nome vazio antes de enviar', async () => {
        ROUTER.post.mockClear();

        const wrapper = mountDialog({ suggestedName: '' });

        await wrapper.get('form').trigger('submit');

        expect(ROUTER.post).not.toHaveBeenCalled();
        expect(wrapper.text()).toContain('Informe um nome.');
    });

    it('mostra o erro de cota que o servidor devolveu', async () => {
        ROUTER.post.mockClear();
        ROUTER.post.mockImplementation(
            (
                _url: string,
                _body: unknown,
                options: { onError: (errors: Record<string, string>) => void },
            ) => {
                options.onError({ layout_system: 'Limite atingido.' });
            },
        );

        const wrapper = mountDialog();

        await wrapper.get('form').trigger('submit');
        await wrapper.vm.$nextTick();

        expect(wrapper.get('[data-test="layout-save-error"]').text()).toBe(
            'Limite atingido.',
        );
    });
});
