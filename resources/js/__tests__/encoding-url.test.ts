import { describe, expect, it } from 'vitest';
import { decodeUrl, encodeUrl, URL_VARIANTS } from '@/lib/encoding';

/**
 * A codificacao de URL nas duas variantes.
 *
 * A diferenca entre elas e o motivo de a tool existir: **componente** e o que se
 * poe dentro de um pedaco de URL (valor de query, segmento de caminho), e
 * **formulario** e o que um `<form>` com `GET` manda. As duas quase coincidem -
 * e onde elas nao coincidem esta o bug que traz alguem aqui: o espaco, que no
 * formulario e `+`, e o `*`, que o formulario escapa e o componente nao.
 *
 * Os valores estao escritos por inteiro porque a norma os fixa: um teste que so
 * fizesse ida e volta passaria com as duas variantes trocadas.
 */

/** O texto de um resultado que deu certo; o teste que espera recusa usa `recusa`. */
function texto(result: ReturnType<typeof decodeUrl>): string {
    if (!result.ok) {
        throw new Error('esperava um texto decodificado, veio recusa');
    }

    return result.value;
}

describe('encodeUrl', () => {
    it('escreve o espaco de um jeito em cada variante', () => {
        expect(encodeUrl('a b', 'component')).toBe('a%20b');
        expect(encodeUrl('a b', 'form')).toBe('a+b');
    });

    it('escapa o `+` nas duas', () => {
        // No componente o `+` e so o `+`; no formulario ele **seria** um espaco
        // na volta, entao tem de sair escapado.
        expect(encodeUrl('a+b', 'component')).toBe('a%2Bb');
        expect(encodeUrl('a+b', 'form')).toBe('a%2Bb');
    });

    it('escapa o que separa as partes de uma URL', () => {
        // E o ponto do componente: um valor com `&` ou `=` dentro, jogado numa
        // query string sem escapar, quebra a query em dois parametros.
        expect(encodeUrl('a&b=c', 'component')).toBe('a%26b%3Dc');
        expect(encodeUrl('/caminho?x=1#topo', 'component')).toBe(
            '%2Fcaminho%3Fx%3D1%23topo',
        );
    });

    it('deixa passar o que o componente considera seguro', () => {
        // `!'()*` sao seguros pela RFC 3986, e o `encodeURIComponent` os deixa
        // como estao.
        expect(encodeUrl("!'()*", 'component')).toBe("!'()*");
        expect(encodeUrl('-_.~', 'component')).toBe('-_.~');
    });

    it('escapa o que o formulario nao considera seguro', () => {
        // A lista do `x-www-form-urlencoded` e mais estreita: um `<form>` manda
        // `*` como `%2A`. E por isso que as duas listas de seguros sao
        // separadas, e nao uma so.
        expect(encodeUrl("!'()*", 'form')).toBe('%21%27%28%29%2A');
        expect(encodeUrl('-_.~', 'form')).toBe('-_.~');
    });

    it('codifica o texto acentuado byte a byte', () => {
        expect(encodeUrl('ação', 'component')).toBe('a%C3%A7%C3%A3o');
        expect(encodeUrl('ção', 'form')).toBe('%C3%A7%C3%A3o');
    });

    it('nao lanca com o par solto que derruba o `encodeURIComponent`', () => {
        // Metade de um emoji: o `encodeURIComponent` daria `URIError` aqui, e a
        // tool ficaria sem o que mostrar. Passando por `TextEncoder`, o par
        // solto vira o U+FFFD que o proprio padrao manda.
        expect(encodeUrl('\ud83d', 'component')).toBe('%EF%BF%BD');
    });

    it('oferece as duas variantes e nenhuma outra', () => {
        expect([...URL_VARIANTS]).toEqual(['component', 'form']);
    });
});

describe('decodeUrl', () => {
    it('le os dois jeitos de escrever o espaco', () => {
        expect(texto(decodeUrl('a%20b', 'component'))).toBe('a b');
        expect(texto(decodeUrl('a%20b', 'form'))).toBe('a b');
        expect(texto(decodeUrl('a+b', 'form'))).toBe('a b');
    });

    it('nao trata o `+` como espaco fora do formulario', () => {
        // No componente o `+` e o `+`: trocar nos dois estragaria um `C++`
        // codificado como componente.
        expect(texto(decodeUrl('a+b', 'component'))).toBe('a+b');
    });

    it('devolve o `+` escapado as duas', () => {
        expect(texto(decodeUrl('a%2Bb', 'component'))).toBe('a+b');
        expect(texto(decodeUrl('a%2Bb', 'form'))).toBe('a+b');
    });

    it('desfaz o texto acentuado', () => {
        expect(texto(decodeUrl('a%C3%A7%C3%A3o', 'form'))).toBe('ação');
    });

    it('recusa a sequencia de escape malformada', () => {
        expect(decodeUrl('%', 'component')).toEqual({ ok: false });
        expect(decodeUrl('%zz', 'component')).toEqual({ ok: false });
        expect(decodeUrl('a%2', 'form')).toEqual({ ok: false });
    });

    it('recusa os bytes que nao sao UTF-8', () => {
        // `%E0%A4` comeca uma sequencia de tres bytes e para no segundo, e
        // `%FF` nao abre sequencia nenhuma.
        expect(decodeUrl('%E0%A4', 'component')).toEqual({ ok: false });
        expect(decodeUrl('%FF', 'form')).toEqual({ ok: false });
    });

    it('faz a ida e a volta nas duas variantes', () => {
        // O `+` fica de fora: ele e o unico caractere que **nao** volta igual na
        // variante de formulario, e isso e a norma, nao um defeito.
        const original = 'ação & café? sim/nao = 100%';

        expect(texto(decodeUrl(encodeUrl(original, 'component'), 'component'))).toBe(original);
        expect(texto(decodeUrl(encodeUrl(original, 'form'), 'form'))).toBe(original);
    });
});
