import { mount } from '@vue/test-utils';
import { defineComponent, h, type PropType } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import Tool from '@/tools/share-text/Tool.vue';

const EditorStub = defineComponent({
    name: 'CodeEditor',
    props: {
        modelValue: { type: String, default: '' },
        placeholder: { type: String, default: '' },
        extensions: { type: Array as PropType<unknown[]>, default: () => [] },
        testid: { type: String, default: 'code-editor' },
    },
    emits: ['update:modelValue'],
    setup(props, { emit }) {
        return () =>
            h('textarea', {
                'data-testid': props.testid,
                value: props.modelValue,
                placeholder: props.placeholder,
                onInput: (e: Event) =>
                    emit(
                        'update:modelValue',
                        (e.target as HTMLTextAreaElement).value,
                    ),
            });
    },
});

const TRADUCOES = {
    tools: {
        'share-text': {
            name: 'Compartilhar Texto',
            description:
                'Compartilhe textos, notas e códigos com links curtos por ULID e compatibilidade total com Hastebin.',
            placeholder: 'Cole ou digite seu texto aqui...',
            actions: {
                save: 'Salvar',
                new: 'Novo',
                duplicate: 'Duplicar & Editar',
                raw: 'Texto Puro (Raw)',
                copy_link: 'Copiar Link',
                copied: 'Link copiado!',
                history: 'Histórico',
            },
            badges: {
                anonymous: 'Expira em 72h (anônimo)',
                permanent: 'Permanente (sua conta)',
                draft: 'Rascunho não salvo',
            },
            dialog: {
                title: 'Texto Compartilhado com Sucesso',
                description:
                    'Seu texto foi salvo com identificador único e já está disponível para compartilhamento.',
                url_label: 'Link de compartilhamento:',
                raw_url_label: 'Link direto (Raw):',
                close: 'Fechar',
            },
            history: {
                title: 'Seus Textos Compartilhados',
                empty: 'Nenhum texto compartilhado ainda.',
                open: 'Abrir',
                delete: 'Excluir',
                confirm_delete:
                    'Tem certeza que deseja excluir permanentemente este texto?',
            },
            languages: {
                auto: 'Texto Puro',
                json: 'JSON',
                sql: 'SQL',
                markdown: 'Markdown',
            },
            errors: {
                empty_content: 'O conteúdo não pode estar vazio.',
                size_exceeded:
                    'O texto excede o limite máximo permitido de {max} MB.',
                save_failed:
                    'Ocorreu um erro ao salvar o texto. Tente novamente.',
                not_found: 'Texto não encontrado ou expirado.',
            },
            messages: {
                deleted: 'Texto excluído com sucesso.',
            },
        },
    },
};

function i18n() {
    const instance = createI18nInstance();
    applyTranslations(instance, { locale: 'pt-BR', translations: TRADUCOES });
    return instance;
}

describe('Share Text Tool Component', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renderiza normalmente com props de visitante anonimo', () => {
        const wrapper = mount(Tool, {
            props: {
                isAuthenticated: false,
                isPaidPlan: false,
                maxUploadBytes: 1048576,
                retentionHours: 72,
                initialDocument: null,
            },
            global: {
                plugins: [i18n()],
                stubs: { CodeEditor: EditorStub },
            },
        });

        expect(wrapper.find('[data-test="share-text-tool"]').exists()).toBe(
            true,
        );
        expect(wrapper.text()).toContain('Expira em 72h (anônimo)');
        expect(wrapper.text()).toContain('Rascunho não salvo');
    });

    it('renderiza com texto inicial carregado e badges corretos', () => {
        const wrapper = mount(Tool, {
            props: {
                isAuthenticated: true,
                isPaidPlan: true,
                maxUploadBytes: 25 * 1024 * 1024,
                retentionHours: null,
                initialDocument: {
                    key: '01HXYZ1234567890ABCDEFGHJK',
                    data: 'SELECT * FROM users;',
                    extension: 'sql',
                    created_at: new Date().toISOString(),
                    size_bytes: 20,
                    is_owner: true,
                },
            },
            global: {
                plugins: [i18n()],
                stubs: { CodeEditor: EditorStub },
            },
        });

        expect(wrapper.text()).toContain('01HXYZ1234567890ABCDEFGHJK');
        expect(wrapper.text()).toContain('Permanente (sua conta)');
        expect(wrapper.text()).toContain('Duplicar & Editar');
        expect(wrapper.text()).toContain('Raw');
    });

    it('exibe botao de historico apenas para usuarios autenticados', () => {
        const anonymousWrapper = mount(Tool, {
            props: {
                isAuthenticated: false,
                maxUploadBytes: 1048576,
            },
            global: {
                plugins: [i18n()],
                stubs: { CodeEditor: EditorStub },
            },
        });

        expect(anonymousWrapper.text()).not.toContain('Histórico');

        const authWrapper = mount(Tool, {
            props: {
                isAuthenticated: true,
                maxUploadBytes: 5242880,
            },
            global: {
                plugins: [i18n()],
                stubs: { CodeEditor: EditorStub },
            },
        });

        expect(authWrapper.text()).toContain('Histórico');
    });
});
