/**
 * O JSON em pedacos ja classificados, para a tela colorir cada um.
 *
 * `JSON.stringify(value, null, 2)` daria o texto pronto, mas sem saber o que e
 * chave, o que e texto e o que e numero - e a cor precisa exatamente disso.
 * Colorir a string de volta seria analisar o que o `stringify` acabou de
 * escrever, com o trabalho de adivinhar onde termina uma string que tem aspas
 * dentro. Aqui cada pedaco ja sai sabendo o que e, e o texto sai da soma deles.
 *
 * A indentacao de dois espacos e o padrao do `JSON.stringify`, entao o que a
 * tela mostra e o que se ve ao copiar o bloco.
 */

/** As cores do JSON do DESIGN: `--color-json-*` no `app.css`. */
export type JsonTokenKind = 'key' | 'string' | 'number' | 'literal' | 'punct';

export interface JsonToken {
    text: string;
    kind: JsonTokenKind;
}

const INDENT = '  ';

/** Uma linha do objeto ou da lista: em lista nao ha chave. */
interface Entry {
    key: string | null;
    value: unknown;
}

export function paintJson(value: unknown): JsonToken[] {
    const tokens: JsonToken[] = [];

    paint(value, 0, tokens);

    return tokens;
}

/**
 * O texto que os pedacos formam, sem markup.
 *
 * E o que vai para a area de transferencia quando a pessoa copia o bloco: sair
 * daqui, e nao de um `JSON.stringify` paralelo, garante que o copiado e o que
 * esta na tela - os dois nunca podem divergir.
 */
export function paintToText(tokens: JsonToken[]): string {
    return tokens.map((token) => token.text).join('');
}

function paint(value: unknown, depth: number, out: JsonToken[]): void {
    if (Array.isArray(value)) {
        paintEntries(
            value.map((item) => ({ key: null, value: item })),
            depth,
            out,
            '[',
            ']',
        );

        return;
    }

    if (value !== null && typeof value === 'object') {
        paintEntries(
            Object.entries(value).map(([key, item]) => ({ key, value: item })),
            depth,
            out,
            '{',
            '}',
        );

        return;
    }

    out.push(scalar(value));
}

function paintEntries(
    entries: Entry[],
    depth: number,
    out: JsonToken[],
    open: string,
    close: string,
): void {
    // Vazio fica numa linha so - `{}` e nao um `{` com um `}` embaixo.
    if (entries.length === 0) {
        out.push({ text: open + close, kind: 'punct' });

        return;
    }

    out.push({ text: open, kind: 'punct' });

    entries.forEach((entry, index) => {
        // A virgula vai na frente do quebra-linha do proximo, e nao depois do
        // anterior: e o que deixa o ultimo item sem virgula sobrando.
        out.push({
            text: (index === 0 ? '\n' : ',\n') + INDENT.repeat(depth + 1),
            kind: 'punct',
        });

        if (entry.key !== null) {
            out.push({ text: JSON.stringify(entry.key), kind: 'key' });
            out.push({ text: ': ', kind: 'punct' });
        }

        paint(entry.value, depth + 1, out);
    });

    out.push({ text: '\n' + INDENT.repeat(depth) + close, kind: 'punct' });
}

function scalar(value: unknown): JsonToken {
    if (typeof value === 'string') {
        // `JSON.stringify` na string e o que escapa aspas, barras e controle -
        // as mesmas regras do outro lado, que e o que faz o texto colado de
        // volta ser JSON valido.
        return { text: JSON.stringify(value), kind: 'string' };
    }

    if (typeof value === 'number') {
        return { text: String(value), kind: 'number' };
    }

    if (typeof value === 'boolean') {
        return { text: value ? 'true' : 'false', kind: 'literal' };
    }

    if (value === null) {
        return { text: 'null', kind: 'literal' };
    }

    // Nao acontece com o que vem do `JSON.parse`, mas o `paintJson` nao deve
    // explodir se um dia receber outra coisa: `undefined` vira `null`, que e o
    // que o proprio JSON tem para dizer "nao ha valor".
    return { text: JSON.stringify(value) ?? 'null', kind: 'literal' };
}
