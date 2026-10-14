/**
 * A conta da virtualizacao, isolada.
 *
 * A lista de linhas da arvore pode ter centenas de milhares de itens: montar
 * tudo no DOM trava a pagina, entao renderiza-se so a janela visivel, com duas
 * fileiras de folga em cada lado para o quadro seguinte ja estar pronto quando
 * a rolagem chegar.
 *
 * A conta esta aqui, e nao dentro do componente, porque **isso da para
 * testar**: sem layout, sem DOM e sem altura de linha medida - a arvore usa
 * linha de altura fixa justamente para que a matematica acima continue valendo.
 */

/*
 * O limiar a partir do qual a lista passa a renderizar so a janela **nao** mora
 * aqui: e um limite, e limite mora na configuracao (`config/tools.php`,
 * `tools.limits.virtualize_from`), que o controller entrega a pagina da tool.
 * Do que e geometria - a altura da linha e a folga - este modulo cuida.
 */

/** Altura da linha, em pixels - tem que bater com a classe da linha na tela. */
export const ROW_HEIGHT = 24;

/** Fileiras de folga acima e abaixo da janela. */
export const OVERSCAN = 8;

export interface WindowRange {
    /** Primeiro indice renderizado. */
    start: number;
    /** Fim **exclusivo**: o ultimo renderizado e `end - 1`. */
    end: number;
    /** Espaco reservado acima, em pixels, para a barra de rolagem nao mentir. */
    paddingTop: number;
    /** Espaco reservado abaixo, em pixels. */
    paddingBottom: number;
}

export function windowRange(
    scrollTop: number,
    viewportHeight: number,
    total: number,
    rowHeight: number = ROW_HEIGHT,
    overscan: number = OVERSCAN,
): WindowRange {
    if (total <= 0 || rowHeight <= 0) {
        return { start: 0, end: Math.max(total, 0), paddingTop: 0, paddingBottom: 0 };
    }

    const top = Math.max(scrollTop, 0);
    const height = Math.max(viewportHeight, 0);
    const firstVisible = Math.floor(top / rowHeight);
    const visibleCount = Math.ceil(height / rowHeight);
    // `start` tambem para no fim da lista: rolagem maior que o conteudo (o que
    // acontece enquanto a lista encolhe por baixo do dedo) inverteria a janela.
    const start = Math.min(Math.max(firstVisible - overscan, 0), total);
    const end = Math.min(firstVisible + visibleCount + overscan, total);

    return {
        start,
        end,
        paddingTop: start * rowHeight,
        paddingBottom: (total - end) * rowHeight,
    };
}
