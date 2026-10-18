import { closeBrackets, closeBracketsKeymap } from '@codemirror/autocomplete';
import { defaultKeymap, history, historyKeymap } from '@codemirror/commands';
import {
    bracketMatching,
    indentOnInput,
    syntaxHighlighting,
} from '@codemirror/language';
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
    type KeyBinding,
} from '@codemirror/view';
import { codeEditorTheme, codeHighlightStyle } from './theme';

/**
 * O editor de codigo.
 *
 * O conjunto de extensoes e montado item por item, e nao pelo `basicSetup`: o
 * `basicSetup` traz autocompletar, dobra de codigo e um painel de busca com
 * rotulos em ingles - e nenhum dos tres e usado aqui, num projeto em que toda
 * string visivel e traducao.
 *
 * A linguagem entra por `extensions`, e nao por um mapa de nomes de linguagem:
 * quem sabe qual e a linguagem e a tela que a pede, entao o pacote dela (o
 * `@codemirror/lang-sql`, por exemplo) so pesa no pedaco de codigo que o usa.
 *
 * Duas ausencias sao deliberadas:
 *
 * - **Barra de busca do motor** (`searchKeymap`): o painel dela tem rotulo
 *   fixo em ingles, sem traducao. O Cmd+F continua funcionando quando ha quem
 *   escute, mas aponta para a busca da tela, que e uma so para o documento.
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
    /** Cmd/Ctrl+Enter: o mesmo "formatar" do botao, sem tirar a mao do teclado. */
    onFormat?: () => void;
    /**
     * O que **esta** tela acrescenta: a linguagem, o realce de resultados, o
     * que mais ela precisar.
     *
     * Entram uma vez, na montagem - o motor nao troca extensao em uso. O que
     * muda durante a vida do editor (os resultados de uma busca, por exemplo)
     * entra por dentro da propria extensao, que le o de fora e se redesenha.
     */
    extensions?: Extension[];
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

export function createEditor(
    parent: HTMLElement,
    options: EditorOptions,
): EditorView {
    /*
    | As teclas da tela vem **antes** das do motor.
    |
    | Duas teclas com o mesmo nome nao se anulam: elas entram na mesma fila, e a
    | primeira que atender fica com a tecla. O `Mod-Enter` ja tem dono no
    | `defaultKeymap` (inserir linha em branco), entao os atalhos da tela vao na
    | frente - senao o "formatar" nunca chegaria a rodar.
    */
    const command: KeyBinding[] = [];

    if (options.onFormat !== undefined) {
        const onFormat = options.onFormat;

        command.push({
            key: 'Mod-Enter',
            run: () => {
                onFormat();

                return true;
            },
        });
    }

    if (options.onFind !== undefined) {
        const onFind = options.onFind;

        command.push({
            key: 'Mod-f',
            run: () => {
                onFind();

                return true;
            },
        });
    }

    const keymaps = [
        ...command,
        ...closeBracketsKeymap,
        ...defaultKeymap,
        ...historyKeymap,
    ];

    const extensions: Extension[] = [
        lineNumbers(),
        highlightActiveLineGutter(),
        highlightActiveLine(),
        history(),
        drawSelection(),
        bracketMatching(),
        indentOnInput(),
        ...(options.extensions ?? []),
        closeBrackets(),
        highlightSelectionMatches(),
        lintGutter(),
        EditorView.lineWrapping,
        syntaxHighlighting(codeHighlightStyle),
        codeEditorTheme,
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
    view.dispatch({
        changes: { from: 0, to: view.state.doc.length, insert: doc },
    });
}

/** Marca o erro na linha; `null` limpa a marca. */
export function setEditorError(
    view: EditorView,
    error: EditorError | null,
): void {
    if (error === null || error.position === null) {
        view.dispatch(setDiagnostics(view.state, []));

        return;
    }

    const { doc } = view.state;
    const from = Math.min(
        Math.max(error.position, 0),
        Math.max(doc.length - 1, 0),
    );

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
