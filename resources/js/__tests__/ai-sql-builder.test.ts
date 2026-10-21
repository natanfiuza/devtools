import { mount, type VueWrapper } from '@vue/test-utils';
import { defineComponent, h, type PropType } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import ResultPanel from '@/tools/ai-sql-builder/ResultPanel.vue';
import SchemaForm from '@/tools/ai-sql-builder/SchemaForm.vue';
import Tool from '@/tools/ai-sql-builder/Tool.vue';
import type {
    AiQueryResult,
    AiToolProps,
    Quota,
} from '@/tools/ai-sql-builder/types';

/**
 * A tela do AI SQL Builder.
 *
 * A fila, o HTTP e o relogio do polling tem arquivos proprios; aqui se cobra o
 * que a pessoa ve: o motivo do bloqueio (visitante, e-mail, conta, cota,
 * provider), o formulario que monta o payload, e o painel que apresenta a
 * resposta. O editor de verdade fica de fora - o CodeMirror precisa de DOM
 * real - e entra um duble com o mesmo `v-model`.
 */

const { post } = vi.hoisted(() => ({ post: vi.fn() }));
const { downloadText } = vi.hoisted(() => ({ downloadText: vi.fn() }));

vi.mock('@/lib/download', () => ({
    downloadText,
}));

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({ props: { localeSlug: 'pt-br' } }),
    useForm: () => ({ processing: false, post }),
    Link: defineComponent({
        name: 'InertiaLink',
        props: { href: { type: String, default: '' } },
        render() {
            return h('a', { href: this.href }, this.$slots.default?.());
        },
    }),
}));

const EditorStub = defineComponent({
    name: 'CodeEditor',
    props: {
        modelValue: { type: String, default: '' },
        placeholder: { type: String, default: '' },
        extensions: { type: Array as PropType<unknown[]>, default: () => [] },
    },
    emits: ['update:modelValue'],
    setup(props, { emit }) {
        return () =>
            h('textarea', {
                'data-test': 'ai-schema-editor',
                value: props.modelValue,
                placeholder: props.placeholder,
                onInput: (event: Event) =>
                    emit(
                        'update:modelValue',
                        (event.target as HTMLTextAreaElement).value,
                    ),
            });
    },
});

const TRADUCOES = {
    tools: {
        ui: {
            actions: { copied: 'COPIADO', copy_manual: 'COPIE_NA_MAO' },
        },
    },
    ai: {
        form: {
            title: 'TITULO_FORM',
            schema_source: 'FONTE',
            schema: 'SCHEMA',
            schema_placeholder: 'COLE_SCHEMA',
            dialect: 'DIALETO',
            target: 'ALVO',
            description: 'DESCRICAO',
            description_placeholder: 'COLE_DESCRICAO',
            submit: 'GERAR',
            submitting: 'ENVIANDO',
            schema_limit: 'SCHEMA:{used}:{limit}',
            description_limit: 'DESC:{used}:{limit}',
            over_limit: 'PASSOU',
            required: 'OBRIGATORIO',
        },
        sources: {
            ddl: 'DDL',
            json: 'JSON',
            migration: 'MIGRATION',
            models: 'MODELS',
        },
        dialects: {
            mysql: 'MYSQL',
            pgsql: 'PGSQL',
            sqlsrv: 'SQLSRV',
            sqlite: 'SQLITE',
        },
        targets: {
            sql: 'SQL',
            eloquent: 'ELOQUENT',
        },
        quota: {
            title: 'COTA_TITULO',
            body_uses: 'USOS:{limit}',
            body_tokens: 'TOKENS:{limit}',
            remaining_uses: 'RESTAM_USOS:{remaining}:{limit}',
            remaining_tokens: 'RESTAM_TOKENS:{remaining}:{limit}',
            cta: 'VER_PLANOS',
        },
        guest: {
            title: 'VISITANTE',
            body: 'CORPO_VISITANTE',
            cta: 'ENTRAR',
        },
        unverified: {
            title: 'NAO_VERIFICADO',
            body: 'CORPO_NAO_VERIFICADO',
            cta: 'REENVIAR',
            sent: 'ENVIADO',
            failed: 'FALHOU_ENVIO',
        },
        suspended: { title: 'SUSPENSA', body: 'CORPO_SUSPENSA' },
        unavailable: {
            title: 'INDISPONIVEL_IA',
            body: 'CORPO_INDISPONIVEL',
        },
        states: {
            idle: 'OCIOSO',
            queued: 'NA_FILA',
            processing: 'GERANDO',
            done: 'PRONTO',
            failed: 'FALHOU_ESTADO',
            timeout: 'TEMPO_ESGOTADO',
            retry: 'TENTAR_DE_NOVO',
            poll_hint: 'AGUARDE',
        },
        warnings: { review: 'REVISE', sensitive: 'SENSIVEL' },
        result: {
            title: 'RESULTADO',
            copy: 'COPIAR',
            copied: 'COPIADO_RESULTADO',
            download: 'BAIXAR',
            download_name: 'consulta',
            assumptions: 'PREMISSAS',
            warnings: 'AVISOS',
            tables: 'TABELAS',
            tokens_in: 'ENTRADA:{count}',
            tokens_out: 'SAIDA:{count}',
            cache_hit: 'CACHE',
            clarification: 'CLARIFICACAO',
            empty: 'VAZIO_RESULTADO',
        },
        errors: { generic: 'ERRO_GENERICO' },
    },
};

const COTA: Quota = {
    unit: 'uses',
    limit: 5,
    used: 1,
    remaining: 4,
    exhausted: false,
    window: { start: null, end: null },
};

const ALVO = {
    slug: 'sql',
    label: 'SQL',
    language: 'sql',
    dialects: ['mysql', 'pgsql', 'sqlsrv', 'sqlite'] as const,
};

function props(overrides: Partial<AiToolProps> = {}): AiToolProps {
    return {
        available: true,
        canUse: { allowed: true, reason: null },
        quota: COTA,
        targets: [{ ...ALVO, dialects: [...ALVO.dialects] }],
        sources: ['ddl', 'json', 'migration', 'models'],
        dialects: ['mysql', 'pgsql', 'sqlsrv', 'sqlite'],
        limits: { schema: 8192, description: 500 },
        poll: { initialMs: 1500, maxMs: 5000, timeoutMs: 120000 },
        endpoints: {
            store: '/api/ai/queries',
            show: '/api/ai/queries/__ID__',
        },
        ...overrides,
    };
}

function i18n() {
    const instance = createI18nInstance();

    applyTranslations(instance, { locale: 'pt-br', translations: TRADUCOES });

    return instance;
}

function montarTool(overrides: Partial<AiToolProps> = {}): VueWrapper {
    return mount(Tool, {
        props: props(overrides),
        global: { plugins: [i18n()], stubs: { CodeEditor: EditorStub } },
    });
}

function montarResultado(
    overrides: Partial<{
        status:
            | 'idle'
            | 'submitting'
            | 'queued'
            | 'processing'
            | 'done'
            | 'failed'
            | 'timeout';
        result: AiQueryResult | null;
        tokens: { in: number | null; out: number | null };
        cacheHit: boolean;
        failureMessage: string | null;
    }> = {},
): VueWrapper {
    return mount(ResultPanel, {
        props: {
            status: 'done',
            result: null,
            tokens: { in: null, out: null },
            cacheHit: false,
            failureMessage: null,
            ...overrides,
        },
        global: { plugins: [i18n()] },
    });
}

const RESULTADO: AiQueryResult = {
    sql: 'SELECT * FROM users',
    dialect: 'mysql',
    assumptions: ['A tabela users existe'],
    warnings: ['Confira o indice'],
    tables_used: ['users'],
    needs_clarification: false,
    clarification: null,
    outputs: { eloquent: 'User::query()->get();' },
};

beforeEach(() => {
    post.mockClear();
});

describe('Tool - bloqueios', () => {
    it('visitante ve o convite para entrar, e nao o formulario', () => {
        const wrapper = montarTool({
            canUse: { allowed: false, reason: 'guest' },
            quota: null,
        });

        expect(
            wrapper
                .get('[data-test="ai-state-notice"]')
                .attributes('data-kind'),
        ).toBe('guest');
        expect(wrapper.text()).toContain('VISITANTE');
        expect(wrapper.get('a').attributes('href')).toBe('/pt-br/login');
        expect(wrapper.find('form').exists()).toBe(false);
        expect(wrapper.find('[data-test="ai-result"]').exists()).toBe(false);
    });

    it('e-mail nao verificado oferece reenviar a confirmacao', async () => {
        const wrapper = montarTool({
            canUse: { allowed: false, reason: 'email_unverified' },
        });

        expect(
            wrapper
                .get('[data-test="ai-state-notice"]')
                .attributes('data-kind'),
        ).toBe('unverified');

        await wrapper.get('[data-test="ai-resend"]').trigger('click');

        expect(post).toHaveBeenCalledTimes(1);
        expect(post.mock.calls[0]?.[0]).toBe(
            '/pt-br/email/verification-notification',
        );
    });

    it('conta suspensa e IA desligada explicam o motivo sem CTA', () => {
        const suspended = montarTool({
            canUse: { allowed: false, reason: 'account_suspended' },
        });

        expect(
            suspended
                .get('[data-test="ai-state-notice"]')
                .attributes('data-kind'),
        ).toBe('suspended');
        expect(suspended.find('[data-test="ai-resend"]').exists()).toBe(false);

        const unavailable = montarTool({
            available: false,
            canUse: { allowed: false, reason: 'unavailable' },
        });

        expect(
            unavailable
                .get('[data-test="ai-state-notice"]')
                .attributes('data-kind'),
        ).toBe('unavailable');
    });

    it('cota esgotada mostra o consumo e leva aos planos', () => {
        const wrapper = montarTool({
            canUse: { allowed: false, reason: 'quota_exhausted' },
            quota: { ...COTA, used: 5, remaining: 0, exhausted: true },
        });

        const notice = wrapper.get('[data-test="ai-state-notice"]');

        expect(notice.attributes('data-kind')).toBe('quota');
        expect(notice.text()).toContain('USOS:5');
        expect(notice.get('a').attributes('href')).toBe('/pt-br/pricing');
    });
});

describe('Tool - conta liberada', () => {
    it('mostra formulario e painel vazio', () => {
        const wrapper = montarTool();

        expect(wrapper.find('[data-test="ai-state-notice"]').exists()).toBe(
            false,
        );
        expect(wrapper.find('[data-test="ai-submit"]').exists()).toBe(true);
        expect(
            wrapper.get('[data-test="ai-result"]').attributes('data-status'),
        ).toBe('idle');
    });

    it('o painel ocioso diz que nada foi gerado ainda', () => {
        const wrapper = montarTool();

        // A fila so aparece depois do POST; abrir a pagina e `idle`.
        expect(wrapper.text()).toContain('VAZIO_RESULTADO');
    });
});

describe('SchemaForm', () => {
    function montarForm(): VueWrapper {
        return mount(SchemaForm, {
            props: {
                sources: ['ddl', 'json', 'migration', 'models'],
                dialects: ['mysql', 'pgsql', 'sqlsrv', 'sqlite'],
                targets: [{ ...ALVO, dialects: [...ALVO.dialects] }],
                limits: { schema: 100, description: 10 },
                quota: COTA,
                busy: false,
            },
            global: { plugins: [i18n()], stubs: { CodeEditor: EditorStub } },
        });
    }

    it('monta o payload com a fonte, o dialeto e o alvo escolhidos', async () => {
        const wrapper = montarForm();

        await wrapper
            .get('[data-test="ai-schema-editor"]')
            .setValue('CREATE TABLE users (id int)');
        await wrapper.get('[data-test="ai-description"]').setValue('Listar');
        await wrapper.get('form').trigger('submit');

        expect(wrapper.emitted('submit')?.[0]?.[0]).toEqual({
            schema_source: 'ddl',
            dialect: 'mysql',
            output_target: 'sql',
            schema: 'CREATE TABLE users (id int)',
            description: 'Listar',
        });
    });

    it('cobra os campos obrigatorios antes de enviar', async () => {
        const wrapper = montarForm();

        await wrapper.get('form').trigger('submit');

        expect(wrapper.find('[data-test="ai-required"]').exists()).toBe(true);
        expect(wrapper.emitted('submit')).toBeUndefined();
    });

    it('bloqueia o envio quando passa do limite do plano', async () => {
        const wrapper = montarForm();

        await wrapper
            .get('[data-test="ai-schema-editor"]')
            .setValue('x'.repeat(101));
        await wrapper.get('[data-test="ai-description"]').setValue('Listar');

        expect(wrapper.find('[data-test="ai-over-limit"]').exists()).toBe(true);
        expect(
            wrapper.get('[data-test="ai-submit"]').attributes('disabled'),
        ).toBeDefined();
    });
});

describe('ResultPanel', () => {
    it('apresenta a saida, as premissas e o consumo', () => {
        const wrapper = montarResultado({
            result: RESULTADO,
            tokens: { in: 12, out: 34 },
        });

        expect(wrapper.get('[data-test="ai-output"]').text()).toContain(
            'User::query()->get();',
        );
        expect(wrapper.text()).toContain('PREMISSAS');
        expect(wrapper.text()).toContain('A tabela users existe');
        expect(wrapper.text()).toContain('AVISOS');
        expect(wrapper.text()).toContain('TABELAS');
        expect(wrapper.text()).toContain('ENTRADA:12');
        expect(wrapper.text()).toContain('SAIDA:34');
        expect(wrapper.find('[data-test="ai-cache-hit"]').exists()).toBe(false);
    });

    it('separa o resultado que veio do cache', () => {
        const wrapper = montarResultado({
            result: RESULTADO,
            cacheHit: true,
        });

        expect(wrapper.get('[data-test="ai-cache-hit"]').text()).toBe('CACHE');
    });

    it('mostra a pergunta quando o modelo pede mais informacao', () => {
        const wrapper = montarResultado({
            result: {
                ...RESULTADO,
                sql: '',
                outputs: {},
                needs_clarification: true,
                clarification: 'Qual tabela guarda os pedidos?',
            },
        });

        const clarification = wrapper.get('[data-test="ai-clarification"]');

        expect(clarification.text()).toContain('CLARIFICACAO');
        expect(clarification.text()).toContain(
            'Qual tabela guarda os pedidos?',
        );
        expect(wrapper.find('[data-test="ai-output"]').exists()).toBe(false);
    });

    it('apresenta a falha terminal com a mensagem recebida', () => {
        const wrapper = montarResultado({
            status: 'failed',
            failureMessage: 'ERRO_DO_PROVEDOR',
        });

        const notice = wrapper.get('[data-test="ai-state-notice"]');

        expect(notice.attributes('data-kind')).toBe('error');
        expect(notice.text()).toContain('ERRO_DO_PROVEDOR');
    });

    it('depois do timeout oferece consultar de novo', async () => {
        const wrapper = montarResultado({ status: 'timeout' });

        expect(
            wrapper
                .get('[data-test="ai-state-notice"]')
                .attributes('data-kind'),
        ).toBe('timeout');

        await wrapper.get('[data-test="ai-retry"]').trigger('click');

        expect(wrapper.emitted('retry')).toHaveLength(1);
    });

    it('enquanto espera, explica que o resultado aparece ali', () => {
        const wrapper = montarResultado({ status: 'processing' });

        expect(wrapper.get('[data-test="ai-pending"]').text()).toContain(
            'GERANDO',
        );
        expect(wrapper.text()).toContain('AGUARDE');
    });

    it('ao baixar o resultado, inclui os metadados como comentarios', async () => {
        const wrapper = montarResultado({
            result: RESULTADO,
            status: 'done',
        });

        await wrapper.get('[data-test="ai-download"]').trigger('click');

        expect(downloadText).toHaveBeenCalledWith(
            expect.stringContaining('.php'),
            expect.stringContaining('PREMISSAS'),
            'text/plain;charset=utf-8',
        );
        expect(downloadText).toHaveBeenCalledWith(
            expect.any(String),
            expect.stringContaining('User::query()->get();'),
            'text/plain;charset=utf-8',
        );
    });
});

describe('Tool - fluxo de envio', () => {
    it('nao chama a API antes de um pedido', () => {
        montarTool();

        expect(post).not.toHaveBeenCalled();
    });
});
