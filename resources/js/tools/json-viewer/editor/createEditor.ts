import { closeBrackets, closeBracketsKeymap } from '@codemirror/autocomplete';
import { defaultKeymap, history, historyKeymap } from '@codemirror/commands';
import { json } from '@codemirror/lang-json';
import { bracketMatching, indentOnInput, syntaxHighlighting } from '@codemirror/language';
import { lintGutter, setDiagnostics } from '@codemirror/lint';
import { highlightSelectionMatches } from '@codemirror/search';
import { EditorState, type Extension } from '@codemirror/state';
import {
    EditorView,
    drawSelection,
    highlightActiveLine,
    highlightActiveLineGutter,
    keymap,
    lineNumbers,
    placeholder as placeholderExtension,
} from '@codemirror/view';
import { jsonEditorTheme, jsonHighlightStyle } from './theme';

/**
 * O editor de JSON.
 *
 * O conjunto de extensoes e montado item por item, e nao pelo `basicSetup`: o
 * `basicSetup` traz autocompletar, dobra de codigo e um painel de busca com
 * rotulos em ingles - e nenhum dos tres e usado aqui, num projeto em que toda
 * string visivel e traducao.
 *
 * Duas ausencias sao deliberadas:
 *
 * - **Barra de busca do motor** (`searchKeymap`): o painel dela tem rotulo
 *   fixo em ingles, sem traducao. O Cmd+F continua funcionando, mas aponta para
 *   a busca da tela, que e uma so para o documento inteiro.
 * - **Tab para indentar** (`indentWithTab`): prenderia o foco do teclado dentro
 *   do editor - quem precisa de indentacao usa o botao "formatar", que e
 *   melhor que digitar espaco.
 */

export interface EditorOptions {
    doc: string;
    /** Texto de apoio no editor vazio; ja traduzido por quem chama. */
    placeholder?: string;
    onDocChange?: (doc: string) => void;
    /** Cmd/Ctrl+F: a busca do documento mora na tela, nao no editor. */
    onFind?: () => void;
}

/** O erro que o editor desenha - o texto ja vem traduzido de quem chama. */
export interface EditorError {
    /**
     * Posicao em code units **no texto que esta no editor**, ou `null` quando
     * o motor nao informou. Quem chama ajusta o BOM, que o motor nao conta.
     */
    position: number | null;
    message: string;
}

export function createEditor(parent: HTMLElement, options: EditorOptions): EditorView {
    const keymaps = [...closeBracketsKeymap, ...defaultKeymap, ...historyKeymap];

    if (options.onFind !== undefined) {
        const onFind = options.onFind;

        keymaps.push({
            key: 'Mod-f',
            run: () => {
                onFind();

                return true;
            },
        });
    }

    const extensions: Extension[] = [
        lineNumbers(),
        highlightActiveLineGutter(),
        highlightActiveLine(),
        history(),
        drawSelection(),
        bracketMatching(),
        indentOnInput(),
        json(),
        closeBrackets(),
        highlightSelectionMatches(),
        lintGutter(),
        EditorView.lineWrapping,
        syntaxHighlighting(jsonHighlightStyle),
        jsonEditorTheme,
        keymap.of(keymaps),
    ];

    if (options.placeholder !== undefined) {
        extensions.push(placeholderExtension(options.placeholder));
    }

    if (options.onDocChange !== undefined) {
        const onDocChange = options.onDocChange;

        extensions.push(
            EditorView.updateListener.of((update) => {
                if (update.docChanged) {
                    onDocChange(update.state.doc.toString());
                }
            }),
        );
    }

    return new EditorView({
        state: EditorState.create({ doc: options.doc, extensions }),
        parent,
    });
}

/**
 * Troca o documento inteiro.
 *
 * Vai como uma mudanca comum, e nao como estado novo: assim a acao entra no
 * historico e o Ctrl+Z desfaz "formatar" como desfaz uma digitacao.
 */
export function setEditorDoc(view: EditorView, doc: string): void {
    view.dispatch({ changes: { from: 0, to: view.state.doc.length, insert: doc } });
}

/** Marca o erro na linha; `null` limpa a marca. */
export function setEditorError(view: EditorView, error: EditorError | null): void {
    if (error === null || error.position === null) {
        view.dispatch(setDiagnostics(view.state, []));

        return;
    }

    const { doc } = view.state;
    const from = Math.min(Math.max(error.position, 0), Math.max(doc.length - 1, 0));

    view.dispatch(
        setDiagnostics(view.state, [
            {
                from,
                to: Math.min(from + 1, doc.length),
                severity: 'error',
                message: error.message,
            },
        ]),
    );
}
