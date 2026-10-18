/**
 * O motor do Regex Tester, do lado puro.
 *
 * A conta inteira mora aqui, e e por isso que ela pode rodar no worker **e** na
 * thread principal: e a mesma funcao nos dois lados (`lib/worker/client`). O que
 * este modulo faz de proprio, alem de chamar o `RegExp`:
 *
 * - **anda com a mao** depois de um casamento vazio. Com `g`, o `exec` devolve o
 *   mesmo casamento vazio na mesma posicao para sempre se ninguem mexer no
 *   `lastIndex`: `a*` sobre `"bbb"` casaria infinitamente na posicao 0;
 * - **conta os casamentos ate o teto** e diz que cortou, em vez de montar uma
 *   lista sem fim sobre um texto de megabytes;
 * - **devolve o erro do motor como resultado** (`detail`), e nao como excecao:
 *   uma expressao invalida esta sendo digitada tecla a tecla, e derrubar o
 *   worker a cada tecla nao e opcao.
 *
 * O `y` (sticky) fica de fora: ele muda o sentido da busca - casa so na posicao
 * exata, e nao onde quer que o padrao apareca -, e a tela promete "todos os
 * casamentos". O `g` entra **sempre**: sem ele nao ha lista.
 */

export type RegexFlag = 'i' | 'm' | 's' | 'u';

/** As quatro que a tela oferece como alternador, na ordem em que aparecem. */
export const REGEX_FLAGS: readonly RegexFlag[] = ['i', 'm', 's', 'u'];

/**
 * As flags como o `RegExp` as quer: `g` sempre, e as ligadas na ordem da tela.
 *
 * Nao ha repeticao possivel - nenhum dos alternadores e o `g` -, entao o motor
 * nunca recusa esta string.
 */
export function flagsOf(toggles: Partial<Record<RegexFlag, boolean>>): string {
    return ['g', ...REGEX_FLAGS.filter((flag) => toggles[flag] === true)].join(
        '',
    );
}

export interface RegexRequest {
    pattern: string;
    /** Ja montadas por `flagsOf`. */
    flags: string;
    /** O texto de teste: e nele que a busca acontece. */
    text: string;
    /** O campo de substituicao, ou `null` quando ele esta vazio. */
    replacement: string | null;
    /** O teto de casamentos (`TOOLS_REGEX_MAX_MATCHES`). */
    maxMatches: number;
}

export interface RegexGroup {
    name: string;
    /** `null` e o grupo nomeado que nao participou do casamento. */
    text: string | null;
}

export interface RegexMatch {
    /** Onde o casamento comeca - a mesma coordenada que o `slice` usa. */
    index: number;
    text: string;
    /** Os grupos numerados, na ordem; `null` e o que nao participou. */
    groups: (string | null)[];
    /** Os grupos nomeados, na ordem em que o motor os devolve. */
    named: RegexGroup[];
}

export type RegexResult =
    | {
          ok: true;
          matches: RegexMatch[];
          /** Bateu no teto: ha mais casamentos alem dos que vieram. */
          truncated: boolean;
          /** O texto com a substituicao aplicada, ou `null` sem substituicao. */
          replaced: string | null;
      }
    | {
          ok: false;
          /**
           * A mensagem do motor, crua: `Unterminated group`. E o unico texto que
           * diz *o que* esta errado na expressao, e a tela o mostra como
           * detalhe tecnico, embaixo da frase dela.
           */
          detail: string;
      };

export function search(request: RegexRequest): RegexResult {
    let pattern: RegExp;

    try {
        pattern = new RegExp(request.pattern, request.flags);
    } catch (error) {
        return {
            ok: false,
            detail: error instanceof Error ? error.message : '',
        };
    }

    const unicode = pattern.unicode;
    const matches: RegexMatch[] = [];
    let truncated = false;
    let found = pattern.exec(request.text);

    while (found !== null) {
        if (matches.length === request.maxMatches) {
            // O casamento que sobrou e a prova de que a lista cortou.
            truncated = true;
            break;
        }

        matches.push({
            index: found.index,
            text: found[0],
            groups: found.slice(1).map((group) => group ?? null),
            named: Object.entries(found.groups ?? {}).map(([name, text]) => ({
                name,
                text: text ?? null,
            })),
        });

        if (found[0] === '') {
            pattern.lastIndex = nextPosition(
                request.text,
                pattern.lastIndex,
                unicode,
            );
        }

        found = pattern.exec(request.text);
    }

    return {
        ok: true,
        matches,
        truncated,
        // O `replace` de um `RegExp` global zera o `lastIndex` sozinho, entao a
        // varredura acima nao atrapalha a substituicao.
        replaced:
            request.replacement === null
                ? null
                : request.text.replace(pattern, request.replacement),
    };
}

/**
 * Onde o proximo casamento pode comecar, depois de um vazio.
 *
 * Com `u` o motor nao casa no meio de um par substituto, e o avanco tambem nao
 * pode parar la: sobre um emoji, o passo e de **dois** code units. Sem `u`, o
 * passo e de um, que e como o proprio motor enxerga o texto.
 */
function nextPosition(text: string, at: number, unicode: boolean): number {
    const code = unicode ? text.codePointAt(at) : undefined;

    return at + (code !== undefined && code > 0xffff ? 2 : 1);
}

export interface Highlight {
    text: string;
    /** O pedaco e um casamento: a tela o pinta. */
    match: boolean;
}

/**
 * O texto cortado nos casamentos, para a previa realcada.
 *
 * Um casamento **vazio** nao vira pedaco: nao ha o que pintar, e marcar nada
 * seria so mudar a cor de lugar nenhum.
 */
export function highlight(text: string, matches: RegexMatch[]): Highlight[] {
    const parts: Highlight[] = [];
    let at = 0;

    for (const match of matches) {
        if (match.text === '' || match.index < at) {
            continue;
        }

        if (match.index > at) {
            parts.push({ text: text.slice(at, match.index), match: false });
        }

        parts.push({ text: match.text, match: true });
        at = match.index + match.text.length;
    }

    if (at < text.length) {
        parts.push({ text: text.slice(at), match: false });
    }

    return parts;
}
