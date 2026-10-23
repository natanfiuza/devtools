import { mount, type VueWrapper } from '@vue/test-utils';
import { defineComponent, h } from 'vue';
import { describe, expect, it, vi } from 'vitest';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import {
    countChars,
    countWords,
    parseMarkdown,
} from '@/tools/markdown-to-pdf/lib/markdown';
import { exportToPdf } from '@/tools/markdown-to-pdf/lib/pdf';
import {
    getDocumentTypographyCss,
    getFontFamilyCss,
    getFontSizeClasses,
    getFontSizeCss,
    getPageSheetStyle,
    getPaperDimensions,
    getPrintCss,
    getThemeClasses,
    MARGIN_VALUES_MM,
    PAPER_DIMENSIONS,
} from '@/tools/markdown-to-pdf/lib/themes';
import { DEFAULT_CONFIG } from '@/tools/markdown-to-pdf/lib/types';
import Tool from '@/tools/markdown-to-pdf/Tool.vue';

const mockSave = vi.fn();
const mockAddPage = vi.fn();
const mockAddImage = vi.fn();
let capturedOnclone: ((doc: Document, el: HTMLElement) => void) | undefined;

vi.mock('html2canvas-pro', () => ({
    default: vi.fn(async (_el, opts) => {
        if (opts?.onclone) {
            capturedOnclone = opts.onclone;
        }

        return {
            width: 800,
            height: 1200,
        };
    }),
}));

vi.mock('jspdf', () => ({
    jsPDF: class {
        save = mockSave;
        addPage = mockAddPage;
        addImage = mockAddImage;
    },
}));

describe('markdown parser', () => {
    it('renderiza titulos, paragrafos e enfase', () => {
        const md =
            '# Titulo Principal\n\nEste e um **texto em negrito** e *italico*.';
        const html = parseMarkdown(md);

        expect(html).toContain('<h1>Titulo Principal</h1>');
        expect(html).toContain('<strong>texto em negrito</strong>');
        expect(html).toContain('<em>italico</em>');
    });

    it('suporta tabelas e checklists do GFM', () => {
        const md = `
| Coluna 1 | Coluna 2 |
| :--- | :--- |
| Item A | Item B |

- [x] Tarefa concluida
- [ ] Tarefa pendente
`;
        const html = parseMarkdown(md);

        expect(html).toContain('<table>');
        expect(html).toContain('>Coluna 1</th>');
        expect(html).toContain('>Item A</td>');
        expect(html).toContain('type="checkbox"');
    });

    it('sanitiza tags maliciosas e scripts prevenindo XSS', () => {
        const malicious =
            'Texto seguro <script>alert("xss")</script><img src="x" onerror="alert(1)">';
        const html = parseMarkdown(malicious);

        expect(html).not.toContain('<script>');
        expect(html).not.toContain('onerror');
    });

    it('devolve string vazia para entrada vazia ou em branco', () => {
        expect(parseMarkdown('')).toBe('');
        expect(parseMarkdown('   \n  ')).toBe('');
    });

    it('conta palavras e caracteres corretamente', () => {
        const text = 'Ola mundo, teste de contagem!';

        expect(countWords(text)).toBe(5);
        expect(countChars(text)).toBe(29);
        expect(countWords('')).toBe(0);
        expect(countChars('')).toBe(0);
    });
});

describe('temas e configuracoes de pagina', () => {
    it('possui configuracao padrao esperada', () => {
        expect(DEFAULT_CONFIG).toEqual({
            format: 'a4',
            orientation: 'portrait',
            margin: 'normal',
            font: 'sans',
            fontSize: 'md',
            theme: 'modern',
        });
    });

    it('calcula dimensoes em retrato e paisagem', () => {
        const a4Portrait = getPaperDimensions('a4', 'portrait');

        expect(a4Portrait).toEqual({
            widthMm: PAPER_DIMENSIONS.a4.widthMm,
            minHeightMm: PAPER_DIMENSIONS.a4.heightMm,
        });

        const a4Landscape = getPaperDimensions('a4', 'landscape');

        expect(a4Landscape).toEqual({
            widthMm: PAPER_DIMENSIONS.a4.heightMm,
            minHeightMm: PAPER_DIMENSIONS.a4.widthMm,
        });
    });

    it('calcula estilos da folha com margens e dimensoes responsivas no preview', () => {
        const style = getPageSheetStyle(DEFAULT_CONFIG);

        expect(style.width).toBe('100%');
        expect(style.maxWidth).toBe('210mm');
        expect(style.minHeight).toBe('297mm');
        expect(style.padding).toBe(`${MARGIN_VALUES_MM.normal}mm`);
        expect(style.fontFamily).toContain('Inter');
    });

    it('aplica classes corretas para os temas visuais', () => {
        expect(getThemeClasses('modern')).toContain('theme-modern');
        expect(getThemeClasses('editorial')).toContain('theme-editorial');
        expect(getThemeClasses('minimal')).toContain('theme-minimal');
        expect(getThemeClasses('academic')).toContain('theme-academic');
    });

    it('fornece as fontes configuradas incluindo Fira Code', () => {
        expect(getFontFamilyCss('sans')).toContain('Inter');
        expect(getFontFamilyCss('serif')).toContain('Merriweather');
        expect(getFontFamilyCss('mono')).toContain('JetBrains Mono');
        expect(getFontFamilyCss('fira-code')).toContain('Fira Code');
    });

    it('ajusta classes e css de tamanho de fonte no padrao Google Docs (9pt, 10pt, 11pt)', () => {
        expect(getFontSizeClasses('sm')).toContain('12px');
        expect(getFontSizeClasses('md')).toContain('13.3px');
        expect(getFontSizeClasses('lg')).toContain('14.67px');

        expect(getFontSizeCss('sm')).toEqual({
            fontSize: '9pt',
            lineHeight: '1.35',
        });
        expect(getFontSizeCss('md')).toEqual({
            fontSize: '10pt',
            lineHeight: '1.35',
        });
        expect(getFontSizeCss('lg')).toEqual({
            fontSize: '11pt',
            lineHeight: '1.35',
        });
    });

    it('contem regras de contencao de tabelas na margem direita e escala proporcional em em', () => {
        const css = getDocumentTypographyCss('modern');

        expect(css).toContain('max-width: 100%;');
        expect(css).toContain('overflow-wrap: anywhere;');
        expect(css).toContain('word-break: break-word;');
        expect(css).toContain('font-size: 1.65em;');
        expect(css).toContain('font-size: 0.9em;');
    });

    it('gera css de impressao com tamanho de folha, margens, fontSize e fluxo multipagina de tabelas', () => {
        const css = getPrintCss(DEFAULT_CONFIG);

        expect(css).toContain('@page {');
        expect(css).toContain('size: 210mm 297mm;');
        expect(css).toContain('margin: 20mm;');
        expect(css).toContain('@media print');
        expect(css).toContain('font-size: 10pt !important;');
        expect(css).toContain('display: table-header-group;');
        expect(css).toContain('blockquote, img, tr {');
    });
});

describe('componente Tool.vue', () => {
    const EditorStub = defineComponent({
        name: 'CodeEditor',
        props: {
            modelValue: { type: String, default: '' },
            placeholder: { type: String, default: '' },
        },
        emits: ['update:modelValue'],
        setup(props, { emit }) {
            return () =>
                h('textarea', {
                    'data-test': 'code-editor-stub',
                    value: props.modelValue,
                    onInput: (e: Event) =>
                        emit(
                            'update:modelValue',
                            (e.target as HTMLTextAreaElement).value,
                        ),
                });
        },
    });

    const mockTranslations = {
        tools: {
            ui: {
                input: 'Entrada',
                output: 'Saída',
                resize: 'Redimensionar',
                drop: 'Solte o arquivo',
                actions: {
                    example: 'Exemplo',
                    open: 'Abrir',
                    clear: 'Limpar',
                    copy: 'Copiar',
                },
                states: {
                    empty: 'Cole, digite ou solte um arquivo para começar.',
                    too_large: 'Arquivo muito grande.',
                },
                limits: { of: 'de {limit}' },
            },
            'markdown-to-pdf': {
                name: 'Markdown to PDF',
                description: 'Conversor de Markdown para PDF',
                keywords: 'markdown, pdf',
                intro: 'Intro',
                download_name: 'documento',
                file_types: 'MD ou TXT',
                controls: {
                    format: 'Formato',
                    orientation: 'Orientação',
                    margin: 'Margem',
                    font: 'Fonte',
                    font_size: 'Tamanho',
                    theme: 'Tema',
                },
                formats: { a4: 'A4', letter: 'Carta', a5: 'A5' },
                orientations: { portrait: 'Retrato', landscape: 'Paisagem' },
                margins: {
                    normal: 'Normal (20mm)',
                    compact: 'Compacta (10mm)',
                    wide: 'Larga (30mm)',
                },
                fonts: {
                    sans: 'Sans-serif',
                    serif: 'Serif',
                    mono: 'Monospace',
                    'fira-code': 'Fira Code',
                },
                font_sizes: { sm: 'Pequeno', md: 'Médio', lg: 'Grande' },
                themes: {
                    modern: 'Moderno',
                    editorial: 'Editorial',
                    minimal: 'Minimalista P&B',
                    academic: 'Acadêmico',
                },
                actions: {
                    download_pdf: 'Baixar PDF',
                    print: 'Imprimir / Salvar como PDF',
                    generating: 'Gerando...',
                },
                stats: {
                    words: '{count} palavras',
                    chars: '{count} caracteres',
                },
            },
        },
    };

    function montar(maxInputBytes: number = 5_000_000): VueWrapper {
        const i18n = createI18nInstance();
        applyTranslations(i18n, {
            locale: 'pt-br',
            translations: mockTranslations,
        });

        return mount(Tool, {
            props: { maxInputBytes },
            global: {
                plugins: [i18n],
                stubs: {
                    CodeEditor: EditorStub,
                },
            },
        });
    }

    it('monta com o documento de exemplo inicializado e preview visivel', () => {
        const wrapper = montar();

        expect(wrapper.find('[data-test="tool-shell"]').exists()).toBe(true);
        expect(
            wrapper.find('[data-test="markdown-pdf-preview"]').exists(),
        ).toBe(true);
        expect(
            wrapper.find('[data-test="markdown-to-pdf-controls"]').exists(),
        ).toBe(true);
    });

    it('exibe todos os 6 seletores de configuracao e o botao de download no header da saida', () => {
        const wrapper = montar();

        expect(wrapper.find('[data-test="select-format"]').exists()).toBe(true);
        expect(wrapper.find('[data-test="select-orientation"]').exists()).toBe(
            true,
        );
        expect(wrapper.find('[data-test="select-margin"]').exists()).toBe(true);
        expect(wrapper.find('[data-test="select-font"]').exists()).toBe(true);
        expect(wrapper.find('[data-test="select-font-size"]').exists()).toBe(
            true,
        );
        expect(wrapper.find('[data-test="select-theme"]').exists()).toBe(true);
        expect(
            wrapper.find('[data-test="top-download-pdf-button"]').exists(),
        ).toBe(true);
    });

    it('limpa o texto e exibe estado vazio ao clicar em limpar', async () => {
        const wrapper = montar();

        const clearBtn = wrapper.find('[data-test="action-clear"]');
        expect(clearBtn.exists()).toBe(true);

        await clearBtn.trigger('click');

        expect(
            wrapper.find('[data-test="markdown-to-pdf-empty"]').exists(),
        ).toBe(true);
        expect(
            wrapper.find('[data-test="markdown-pdf-preview"]').exists(),
        ).toBe(false);
    });

    it('recarrega o exemplo ao clicar no botao de exemplo', async () => {
        const wrapper = montar();

        await wrapper.find('[data-test="action-clear"]').trigger('click');
        expect(
            wrapper.find('[data-test="markdown-to-pdf-empty"]').exists(),
        ).toBe(true);

        await wrapper.find('[data-test="action-example"]').trigger('click');
        expect(
            wrapper.find('[data-test="markdown-pdf-preview"]').exists(),
        ).toBe(true);
    });
});

describe('exportToPdf utilitario', () => {
    it('executa o fatiamento de paginas e chama o save do jsPDF com nome higienizado', async () => {
        mockSave.mockClear();
        mockAddPage.mockClear();
        mockAddImage.mockClear();

        const el = document.createElement('div');
        el.className =
            'markdown-pdf-document rounded border border-neutral-200/80 shadow-md';
        el.innerHTML = '<p>Conteudo de teste</p>';

        await exportToPdf(el, 'relatorio', DEFAULT_CONFIG);

        expect(mockSave).toHaveBeenCalledWith('relatorio.pdf');
        expect(mockAddImage).toHaveBeenCalledWith(
            null,
            'JPEG',
            20,
            20,
            170,
            expect.any(Number),
            undefined,
            'FAST',
        );

        if (capturedOnclone) {
            const cloned = document.createElement('div');
            cloned.className =
                'rounded border border-neutral-200/80 shadow-md dark:border-neutral-700/80';
            capturedOnclone(document, cloned);
            expect(cloned.style.boxShadow).toBe('none');
            expect(
                cloned.style.border === 'none' ||
                    cloned.style.borderStyle === 'none',
            ).toBe(true);
            expect(cloned.style.width).toBe('170mm');
            expect(cloned.style.maxWidth).toBe('170mm');
            expect(cloned.style.padding).toBe('0px');
            expect(cloned.classList.contains('shadow-md')).toBe(false);
            expect(cloned.classList.contains('border-neutral-200/80')).toBe(
                false,
            );
        }
    });
});
