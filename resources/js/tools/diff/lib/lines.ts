import type { DiffHunk, HunkLine, TextDiff } from '../types';

/**
 * A comparacao por linhas - o motor do modo texto.
 *
 * O algoritmo e o de Myers (1986): o mesmo do `diff` e do `git diff`, e o que
 * garante o **menor numero de edicoes** entre os dois documentos. Ele custa
 * O((N+M)·D) em tempo, onde D e o numero de linhas diferentes - o que e barato
 * para o caso comum e caro para dois documentos sem nada em comum.
 *
 * O custo e o que o orcamento limita: passando de `MAX_EDITS` edicoes, o diff
 * vira **por posicao** (linha 1 com linha 1, e assim por diante), que e barato
 * e honesto para dois textos sem relacao - e o resultado vai marcado com
 * `truncated`, para a tela avisar que aquele diff nao e o minimo.
 *
 * Antes de tudo, o prefixo e o sufixo iguais saem da conta: dois arquivos que
 * so mudam no meio nao pagam pelo resto, e o diff de um caso real quase sempre
 * tem muito em comum nas pontas.
 *
 * A comparacao e por linha inteira (`splitLines` normaliza CRLF): o diff nao
 * compara caractere nem palavra. O modo JSON existe para a comparacao
 * semantica; no modo texto, uma virgula diferente e uma linha diferente.
 */

/**
 * Orcamento de edicoes do Myers.
 *
 * A memoria do algoritmo e o preco: cada passo guarda a fronteira da busca para
 * reconstruir o caminho, e o total e O(D²). Dois mil e o ponto em que o
 * resultado (um diff de 2000 linhas) continua sendo o que alguem quer ler, e o
 * pico de memoria (~32 MB de `Int32Array`) continua aceitavel no navegador.
 * Acima disso, o diff por posicao custa menos e diz a mesma coisa: "daqui para
 * baixo, tudo mudou".
 */
export const MAX_EDITS = 2000;

/**
 * As linhas do texto, sem a linha vazia que o newline final cria.
 *
 * O CRLF vira LF antes do corte: um arquivo do Windows comparado com um do Unix
 * nao pode virar "todas as linhas mudaram" por causa de um `\r` invisivel.
 */
export function splitLines(text: string): string[] {
    if (text === '') {
        return [];
    }

    const lines = text.replace(/\r\n?/g, '\n').split('\n');

    if (lines[lines.length - 1] === '') {
        lines.pop();
    }

    return lines;
}

/** Um passo do caminho: linha comum aos dois, ou linha de um lado so. */
type Step =
    | { type: 'same'; a: number; b: number }
    | { type: 'removed'; a: number }
    | { type: 'added'; b: number };

function lineAt(lines: readonly string[], index: number): string {
    return lines[index] ?? '';
}

/**
 * A leitura do `Int32Array` da fronteira.
 *
 * O `noUncheckedIndexedAccess` trata toda leitura indexada como possivelmente
 * `undefined`; na fronteira do Myers o indice e sempre valido por construcao,
 * entao o zero e um piso que nunca aparece - e existe para o tipo fechar.
 */
function cell(values: Int32Array, index: number): number {
    return values[index] ?? 0;
}

/**
 * Cada linha vira um numero.
 *
 * O Myers compara linhas o tempo todo; comparar numero e mais barato que
 * comparar string, e o mapa tambem tira o custo de linha longa do caminho
 * quente.
 */
function intern(
    a: readonly string[],
    b: readonly string[],
): [number[], number[]] {
    const ids = new Map<string, number>();
    let next = 0;

    const idOf = (line: string): number => {
        const found = ids.get(line);

        if (found !== undefined) {
            return found;
        }

        ids.set(line, next);

        return next++;
    };

    return [a.map(idOf), b.map(idOf)];
}

/**
 * Myers com orcamento: o caminho minimo, ou `null` quando passou do limite.
 *
 * O `trace` guarda a fronteira da busca ao fim de cada distancia; e dele que a
 * volta reconstroi o caminho (sem ele, so daria para saber o tamanho da
 * diferenca, nao onde ela esta).
 */
function myers(a: readonly number[], b: readonly number[]): Step[] | null {
    const n = a.length;
    const m = b.length;
    const limit = Math.min(n + m, MAX_EDITS);
    const offset = limit + 1;
    const v = new Int32Array(2 * limit + 3);
    const trace: Int32Array[] = [];
    let foundD = -1;

    for (let d = 0; d <= limit; d += 1) {
        for (let k = -d; k <= d; k += 2) {
            const down =
                k === -d ||
                (k !== d && cell(v, offset + k - 1) < cell(v, offset + k + 1));
            let x = down
                ? cell(v, offset + k + 1)
                : cell(v, offset + k - 1) + 1;
            let y = x - k;

            while (x < n && y < m && a[x] === b[y]) {
                x += 1;
                y += 1;
            }

            v[offset + k] = x;

            if (x >= n && y >= m) {
                foundD = d;
                break;
            }
        }

        trace.push(v.slice());

        if (foundD >= 0) {
            break;
        }
    }

    if (foundD < 0) {
        return null;
    }

    const steps: Step[] = [];
    let x = n;
    let y = m;

    for (let d = foundD; d > 0; d -= 1) {
        const frontier = trace[d];

        if (frontier === undefined) {
            return null;
        }

        const k = x - y;
        const down =
            k === -d ||
            (k !== d &&
                cell(frontier, offset + k - 1) <
                    cell(frontier, offset + k + 1));
        const prevK = down ? k + 1 : k - 1;
        const prevX = cell(frontier, offset + prevK);
        const prevY = prevX - prevK;

        // O trecho comum antes da edicao da vez.
        while (x > prevX && y > prevY) {
            steps.push({ type: 'same', a: x - 1, b: y - 1 });
            x -= 1;
            y -= 1;
        }

        if (x === prevX) {
            steps.push({ type: 'added', b: y - 1 });
            y -= 1;
        } else {
            steps.push({ type: 'removed', a: x - 1 });
            x -= 1;
        }
    }

    // O primeiro trecho comum, antes da primeira edicao.
    while (x > 0 && y > 0) {
        steps.push({ type: 'same', a: x - 1, b: y - 1 });
        x -= 1;
        y -= 1;
    }

    steps.reverse();

    return steps;
}

/**
 * O diff de quem estourou o orcamento: linha da esquerda com linha da direita.
 *
 * Nao e o minimo, mas e previsivel: a linha 10 do original e comparada com a
 * linha 10 do modificado, e o que sobrou de um lado vira adicao/remocao no fim.
 */
function byPosition(a: readonly number[], b: readonly number[]): Step[] {
    const steps: Step[] = [];
    const common = Math.min(a.length, b.length);

    for (let index = 0; index < common; index += 1) {
        if (a[index] === b[index]) {
            steps.push({ type: 'same', a: index, b: index });
            continue;
        }

        steps.push({ type: 'removed', a: index });
        steps.push({ type: 'added', b: index });
    }

    for (let index = common; index < a.length; index += 1) {
        steps.push({ type: 'removed', a: index });
    }

    for (let index = common; index < b.length; index += 1) {
        steps.push({ type: 'added', b: index });
    }

    return steps;
}

/**
 * Fecha a regiao aberta: conta os pares e empurra o hunk.
 *
 * O par e a leitura "esta linha mudou": com 3 remocoes e 3 adicoes na mesma
 * regiao, cada uma e par da outra e a tela pinta o tom de "alterado"; com 3
 * remocoes e 1 adicao, so a primeira adicao tem par e o resto e entrada/saida.
 */
function appendHunk(
    hunks: DiffHunk[],
    lines: HunkLine[],
    leftStart: number,
    rightStart: number,
): void {
    const removed = lines.filter((line) => line.type === 'removed').length;
    const added = lines.filter((line) => line.type === 'added').length;
    const pairs = Math.min(removed, added);

    let removedSeen = 0;
    let addedSeen = 0;

    for (const line of lines) {
        if (line.type === 'removed') {
            line.paired = removedSeen < pairs;
            removedSeen += 1;
        } else {
            line.paired = addedSeen < pairs;
            addedSeen += 1;
        }
    }

    hunks.push({ leftStart, rightStart, lines });
}

export function diffLines(leftText: string, rightText: string): TextDiff {
    const left = splitLines(leftText);
    const right = splitLines(rightText);
    const [leftIds, rightIds] = intern(left, right);

    let start = 0;
    while (
        start < left.length &&
        start < right.length &&
        leftIds[start] === rightIds[start]
    ) {
        start += 1;
    }

    let endLeft = left.length;
    let endRight = right.length;

    while (
        endLeft > start &&
        endRight > start &&
        leftIds[endLeft - 1] === rightIds[endRight - 1]
    ) {
        endLeft -= 1;
        endRight -= 1;
    }

    const middleLeft = leftIds.slice(start, endLeft);
    const middleRight = rightIds.slice(start, endRight);
    const middle = myers(middleLeft, middleRight);
    const truncated = middle === null;
    const steps = middle ?? byPosition(middleLeft, middleRight);

    // Os passos voltam com indices do miolo; o prefixo e o sufixo recortados
    // entram na forma dos indices absolutos de cada documento.
    const full: Step[] = [];

    for (let index = 0; index < start; index += 1) {
        full.push({ type: 'same', a: index, b: index });
    }

    for (const step of steps) {
        if (step.type === 'same') {
            full.push({ type: 'same', a: step.a + start, b: step.b + start });
        } else if (step.type === 'removed') {
            full.push({ type: 'removed', a: step.a + start });
        } else {
            full.push({ type: 'added', b: step.b + start });
        }
    }

    for (let index = 0; index < left.length - endLeft; index += 1) {
        full.push({ type: 'same', a: endLeft + index, b: endRight + index });
    }

    const hunks: DiffHunk[] = [];
    let current: HunkLine[] | null = null;
    let hunkLeftStart = 0;
    let hunkRightStart = 0;
    let leftPos = 0;
    let rightPos = 0;
    let removedCount = 0;
    let addedCount = 0;

    for (const step of full) {
        if (step.type === 'same') {
            if (current !== null) {
                appendHunk(hunks, current, hunkLeftStart, hunkRightStart);
                current = null;
            }

            leftPos += 1;
            rightPos += 1;
            continue;
        }

        if (current === null) {
            current = [];
            hunkLeftStart = leftPos;
            hunkRightStart = rightPos;
        }

        if (step.type === 'removed') {
            current.push({
                type: 'removed',
                line: step.a,
                text: lineAt(left, step.a),
                paired: false,
            });
            removedCount += 1;
            leftPos += 1;
        } else {
            current.push({
                type: 'added',
                line: step.b,
                text: lineAt(right, step.b),
                paired: false,
            });
            addedCount += 1;
            rightPos += 1;
        }
    }

    if (current !== null) {
        appendHunk(hunks, current, hunkLeftStart, hunkRightStart);
    }

    return { hunks, removed: removedCount, added: addedCount, truncated };
}
