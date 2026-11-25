import {
    StateEffect,
    StateField,
    type EditorState,
    type Range,
} from '@codemirror/state';
import { Decoration, EditorView, type DecorationSet } from '@codemirror/view';

/**
 * O realce de linhas que vem de fora: o diff pinta "acrescentada", "removida"
 * e "alterada" sem que o editor saiba o que e um diff.
 *
 * O canal e um campo de estado e um efeito, e nao uma `Compartment`: a lista
 * troca a cada resultado, e o campo so precisa reaproveitar as decoracoes que
 * ja desenhou. A pintura fica a cargo do tema (`.cm-diff-*`), com token do
 * DESIGN - aqui nao ha cor.
 *
 * A linha chega **zero-based**, ao contrario da do motor: quem chama conta
 * linhas de uma lista (indice 0), nao de um texto.
 */

export type LineTone = 'added' | 'removed' | 'changed';

export interface LineHighlight {
    /** Linha do documento, zero-based. Linha fora do texto e ignorada. */
    line: number;
    tone: LineTone;
}

const marks = {
    added: Decoration.line({ class: 'cm-diff-added' }),
    removed: Decoration.line({ class: 'cm-diff-removed' }),
    changed: Decoration.line({ class: 'cm-diff-changed' }),
} as const;

export const setLineHighlightsEffect = StateEffect.define<LineHighlight[]>();

/**
 * Monta o conjunto a partir da lista.
 *
 * Linhas repetidas ficam com o primeiro tom (a lista sai ordenada por linha, e
 * quem desenha a esquerda manda); linhas fora do documento somem em vez de
 * estourar - o resultado pode chegar depois de o texto ja ter sido editado.
 */
function build(
    highlights: readonly LineHighlight[],
    state: EditorState,
): DecorationSet {
    const seen = new Set<number>();
    const ranges: Range<Decoration>[] = [];
    const ordered = [...highlights].sort((a, b) => a.line - b.line);

    for (const highlight of ordered) {
        if (!Number.isInteger(highlight.line)) {
            continue;
        }

        if (highlight.line < 0 || highlight.line >= state.doc.lines) {
            continue;
        }

        if (seen.has(highlight.line)) {
            continue;
        }

        seen.add(highlight.line);
        ranges.push(
            marks[highlight.tone].range(
                state.doc.line(highlight.line + 1).from,
            ),
        );
    }

    return Decoration.set(ranges, true);
}

export const lineHighlightField = StateField.define<DecorationSet>({
    create: () => Decoration.none,
    update(set, transaction) {
        for (const effect of transaction.effects) {
            if (effect.is(setLineHighlightsEffect)) {
                return build(effect.value, transaction.state);
            }
        }

        // A digitacao leva as marcas junto: a linha que recebe texto continua
        // sendo a mesma linha para o leitor, ainda que o texto dela mude.
        return set.map(transaction.changes);
    },
    provide: (field) => EditorView.decorations.from(field),
});

/** Troca as linhas pintadas; lista vazia limpa. */
export function setLineHighlights(
    view: EditorView,
    highlights: readonly LineHighlight[],
): void {
    view.dispatch({ effects: setLineHighlightsEffect.of([...highlights]) });
}
