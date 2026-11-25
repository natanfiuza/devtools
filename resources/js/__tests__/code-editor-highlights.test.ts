import type { EditorView } from '@codemirror/view';
import { afterEach, describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import CodeEditor from '@/components/tool/CodeEditor.vue';
import { createEditor } from '@/lib/editor/createEditor';
import { setLineHighlights, type LineHighlight } from '@/lib/editor/highlights';

/**
 * O realce de linhas do editor - o canal que o diff usa para pintar o que
 * mudou.
 *
 * O CodeMirror mede o texto com `Range.getClientRects`, que o jsdom nao tem;
 * os dois remendos respondem vazio, como no teste das teclas. O que este
 * arquivo cobra e o class do `<div>` da linha, e nao medida nenhuma.
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

function montarEditor(doc: string): EditorView {
    const host = document.createElement('div');

    document.body.appendChild(host);

    const view = createEditor(host, { doc });

    abertos.push(view);

    return view;
}

afterEach(() => {
    for (const view of abertos.splice(0)) {
        view.destroy();
    }

    document.body.innerHTML = '';
});

/** As linhas desenhadas, na ordem do documento. */
function linhas(view: EditorView): Element[] {
    return [...view.contentDOM.querySelectorAll('.cm-line')];
}

/** O tom que a linha pedida recebeu, ou `null` quando nao ha realce. */
function tom(view: EditorView, line: number): string | null {
    const element = linhas(view)[line];
    const tone = element?.className.match(/cm-diff-(\w+)/);

    return tone?.[1] ?? null;
}

describe('realce de linhas', () => {
    it('pinta cada linha com o tom que veio', () => {
        const view = montarEditor('um\ndois\ntres');

        setLineHighlights(view, [
            { line: 0, tone: 'added' },
            { line: 2, tone: 'removed' },
        ]);

        expect(tom(view, 0)).toBe('added');
        expect(tom(view, 1)).toBeNull();
        expect(tom(view, 2)).toBe('removed');
    });

    it('troca o conjunto inteiro a cada lista, sem acumular', () => {
        const view = montarEditor('um\ndois\ntres');

        setLineHighlights(view, [{ line: 0, tone: 'added' }]);
        setLineHighlights(view, [{ line: 1, tone: 'changed' }]);

        expect(tom(view, 0)).toBeNull();
        expect(tom(view, 1)).toBe('changed');
    });

    it('ignora linha fora do documento e linha repetida', () => {
        const view = montarEditor('um\ndois');

        setLineHighlights(view, [
            { line: 9, tone: 'added' },
            { line: -1, tone: 'removed' },
            { line: 0, tone: 'added' },
            { line: 0, tone: 'removed' },
        ]);

        // A primeira opiniao sobre a linha vale; o resto nao estoura nem pinta
        // duas vezes.
        expect(tom(view, 0)).toBe('added');
        expect(tom(view, 1)).toBeNull();
    });

    it('limpa com a lista vazia', () => {
        const view = montarEditor('um\ndois');

        setLineHighlights(view, [{ line: 1, tone: 'removed' }]);
        setLineHighlights(view, []);

        expect(tom(view, 1)).toBeNull();
    });

    it('leva a marca junto quando o texto muda', () => {
        const view = montarEditor('um\ndois\ntres');

        setLineHighlights(view, [{ line: 2, tone: 'changed' }]);

        // Uma digitacao na primeira linha nao cria nem apaga linha: a marca
        // continua na que estava marcada.
        view.dispatch({ changes: { from: 1, insert: 'X' } });

        expect(tom(view, 2)).toBe('changed');
    });
});

describe('CodeEditor com realce', () => {
    function montar(highlights: LineHighlight[]) {
        return mount(CodeEditor, {
            props: {
                modelValue: 'um\ndois\ntres',
                lineHighlights: highlights,
            },
            attachTo: document.body,
        });
    }

    it('aplica a lista da prop e acompanha a troca', async () => {
        const wrapper = montar([{ line: 1, tone: 'added' }]);
        const view = (wrapper.vm as unknown as { view: EditorView | null })
            .view;

        expect(view).not.toBeNull();
        expect(tom(view as EditorView, 1)).toBe('added');

        await wrapper.setProps({
            lineHighlights: [{ line: 0, tone: 'removed' }],
        });

        expect(tom(view as EditorView, 0)).toBe('removed');
        expect(tom(view as EditorView, 1)).toBeNull();

        wrapper.unmount();
    });

    it('desenha o editor sem realce quando a prop nao vem', () => {
        const wrapper = mount(CodeEditor, {
            props: { modelValue: 'um\ndois' },
            attachTo: document.body,
        });
        const view = (wrapper.vm as unknown as { view: EditorView | null })
            .view;

        expect(linhas(view as EditorView)).toHaveLength(2);
        expect(tom(view as EditorView, 0)).toBeNull();

        wrapper.unmount();
    });
});
