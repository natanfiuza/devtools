import { HighlightStyle } from '@codemirror/language';
import { EditorView } from '@codemirror/view';
import { tags } from '@lezer/highlight';

/**
 * O tema do editor.
 *
 * Nao ha paleta paralela aqui: toda cor e um `var(--json-*)` do DESIGN, a mesma
 * familia de tokens que a arvore do JSON Viewer usa (ADR-018). Duas
 * consequencias boas - as telas de codigo nunca saem de sincronia com o resto,
 * e trocar de tema claro/escuro nao passa por JavaScript: a classe no documento
 * troca as variaveis e o CodeMirror repinta.
 *
 * O tema **nao** se declara escuro para o motor (`EditorView.theme(spec, {dark:
 * true})`): no projeto o tema e uma classe no documento, e nao a preferencia do
 * sistema, entao escolher um dos dois deixaria o outro com a selecao e os
 * paineis nas cores do motor. Os paineis e a selecao estao estilizados abaixo.
 *
 * Os tokens ainda se chamam `--json-*` porque nasceram com o JSON Viewer: a
 * familia agora pinta qualquer linguagem (o `keyword` de baixo e o `SELECT` do
 * SQL), e renomear tudo mexeria no DESIGN e em quem consome os mesmos tokens -
 * a arvore do JSON - sem mudar um pixel.
 *
 * As cores sao conferidas em resources/js/__tests__/json-editor-theme.test.ts.
 */

/** Uma cor por token de sintaxe, e nenhuma cor literal. */
export const codeHighlightStyle = HighlightStyle.define([
    { tag: tags.propertyName, color: 'var(--json-key)' },
    { tag: tags.string, color: 'var(--json-string)' },
    { tag: tags.number, color: 'var(--json-number)' },
    { tag: [tags.bool, tags.null], color: 'var(--json-literal)' },
    { tag: [tags.brace, tags.squareBracket, tags.separator], color: 'var(--json-punct)' },
    { tag: tags.keyword, color: 'var(--json-key)' },
    { tag: tags.typeName, color: 'var(--json-key)' },
    { tag: tags.className, color: 'var(--json-key)' },
    { tag: tags.comment, color: 'var(--subtle)' },
    // O `⚠` que o parser usa no texto que nao entende.
    { tag: tags.invalid, color: 'var(--destructive)' },
]);

export const codeEditorTheme = EditorView.theme({
    '&': {
        height: '100%',
        color: 'var(--foreground)',
        backgroundColor: 'transparent',
    },
    '&.cm-focused': {
        outline: 'none',
    },
    // A fonte vem da classe do elemento que hospeda o editor (`font-mono`),
    // em vez de repetir a pilha de fontes aqui.
    '.cm-scroller': {
        fontFamily: 'inherit',
        lineHeight: '1.6',
    },
    '.cm-content': {
        padding: '0.5rem 0',
        caretColor: 'var(--brand)',
    },
    '.cm-cursor, .cm-dropCursor': {
        borderLeftColor: 'var(--brand)',
    },
    '.cm-line': {
        padding: '0 0.75rem',
    },
    '.cm-gutters': {
        backgroundColor: 'transparent',
        color: 'var(--subtle)',
        border: 'none',
        borderRight: '1px solid var(--border)',
    },
    '.cm-gutterElement': {
        padding: '0 0.5rem 0 0.75rem',
    },
    '.cm-activeLine': {
        backgroundColor: 'var(--surface-2)',
    },
    '.cm-activeLineGutter': {
        backgroundColor: 'var(--surface-2)',
        color: 'var(--foreground)',
    },
    '.cm-selectionBackground, .cm-content ::selection': {
        backgroundColor: 'var(--secondary)',
    },
    '&.cm-focused .cm-selectionBackground': {
        backgroundColor: 'var(--secondary)',
    },
    '.cm-selectionMatch': {
        backgroundColor: 'var(--surface-2)',
        outline: '1px solid var(--border-strong)',
    },
    '.cm-panels': {
        backgroundColor: 'var(--surface-2)',
        color: 'var(--foreground)',
    },
    '.cm-panels.cm-panels-top': {
        borderBottom: '1px solid var(--border)',
    },
    '.cm-searchMatch': {
        backgroundColor: 'var(--surface-2)',
        outline: '1px solid var(--border-strong)',
    },
    '.cm-searchMatch.cm-searchMatch-selected': {
        backgroundColor: 'var(--brand)',
        color: 'var(--primary-foreground)',
    },
    '.cm-tooltip': {
        backgroundColor: 'var(--surface)',
        color: 'var(--foreground)',
        border: '1px solid var(--border)',
    },
    '.cm-tooltip-lint': {
        padding: '0.25rem 0.5rem',
    },
    '.cm-lintRange-error': {
        textDecoration: 'underline wavy var(--destructive)',
    },
    '.cm-diagnostic-error': {
        borderLeft: '3px solid var(--destructive)',
    },
    '.cm-placeholder': {
        color: 'var(--subtle)',
    },
});
