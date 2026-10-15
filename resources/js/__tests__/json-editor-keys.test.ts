import type { EditorView } from '@codemirror/view';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { createEditor, type EditorOptions } from '@/lib/editor/createEditor';

/**
 * As teclas do editor.
 *
 * Este e o unico teste que monta o CodeMirror de verdade: o resto da tela usa o
 * duble do editor, porque o que se cobra la e o comportamento da pagina. Aqui o
 * que esta em jogo e a **fila de teclas** - o `Mod-Enter` tem dono no
 * `defaultKeymap` do motor (inserir linha em branco), e o atalho da tela so
 * atende a tecla se for registrado antes. Um teste que so olhasse a chamada do
 * componente passaria com o atalho quebrado na mao do usuario.
 *
 * O jsdom nao mede nada: o CodeMirror mede o texto com `Range.getClientRects`,
 * que o jsdom nao implementa. Os dois remendos abaixo respondem vazio, que e o
 * suficiente para o editor montar - o que este arquivo cobra nao depende de
 * medida nenhuma.
 */

const rect = {
    x: 0,
    y: 0,
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    width: 0,
    height: 0,
    toJSON: () => ({}),
};

Object.defineProperties(Range.prototype, {
    getClientRects: { value: () => [], configurable: true },
    getBoundingClientRect: { value: () => rect, configurable: true },
});

const abertos: EditorView[] = [];

function montar(options: EditorOptions): EditorView {
    const host = document.createElement('div');

    document.body.appendChild(host);

    const view = createEditor(host, options);

    abertos.push(view);

    return view;
}

afterEach(() => {
    for (const view of abertos.splice(0)) {
        view.destroy();
    }

    document.body.innerHTML = '';
});

/** A tecla como o navegador a manda: `key` e os modificadores. */
function teclar(view: EditorView, init: KeyboardEventInit): void {
    view.contentDOM.dispatchEvent(
        new KeyboardEvent('keydown', { bubbles: true, cancelable: true, ...init }),
    );
}

describe('atalhos do editor', () => {
    it('chama o formatar no Mod-Enter, sem deixar o motor inserir a linha em branco', () => {
        const onFormat = vi.fn();
        const view = montar({ doc: '{"a":1}', onFormat });

        teclar(view, { key: 'Enter', ctrlKey: true });

        expect(onFormat).toHaveBeenCalledTimes(1);
        // A tecla foi atendida pela tela: o documento nao ganhou a linha em
        // branco que o `defaultKeymap` inseriria.
        expect(view.state.doc.toString()).toBe('{"a":1}');
        expect(view.state.doc.lines).toBe(1);
    });

    it('chama a busca no Mod-f', () => {
        const onFind = vi.fn();
        const view = montar({ doc: '{"a":1}', onFind });

        teclar(view, { key: 'f', ctrlKey: true });

        expect(onFind).toHaveBeenCalledTimes(1);
    });

    it('deixa a linha em branco do motor quando a tela nao registra o atalho', () => {
        const view = montar({ doc: '{"a":1}' });

        teclar(view, { key: 'Enter', ctrlKey: true });

        // Sem `onFormat`, o `Mod-Enter` continua sendo o do CodeMirror.
        expect(view.state.doc.lines).toBe(2);
    });
});
