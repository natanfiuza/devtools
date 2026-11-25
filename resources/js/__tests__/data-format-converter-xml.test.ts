import { describe, expect, it } from 'vitest';
import { convert } from '@/tools/data-format-converter/lib/convert';
import type { ConvertSuccess } from '@/tools/data-format-converter/types';

/**
 * A convencao do XML (ADR-180), nos dois sentidos.
 *
 * O XML nao tem objeto nem numero: ele tem elementos, atributos e texto. A
 * convencao e a resposta para "o que vira o que", e e ela que faz a ida e a
 * volta nao perderem a forma:
 *
 * - o elemento raiz vira a chave de topo;
 * - atributo vira `@nome`;
 * - o texto de um elemento que tambem tem atributo ou filho vira `#text`;
 * - irmaos com o mesmo nome viram array, na ordem do documento;
 * - elemento vazio vira string vazia.
 *
 * As mensagens do `parsererror` muda de motor para motor; por isso nenhum teste
 * le o texto do erro, so o codigo.
 */

function xmlToJson(xml: string): {
    value: unknown;
    resultado: ConvertSuccess;
} {
    const resultado = convert({ text: xml, from: 'xml', to: 'json' });

    expect(resultado.ok, JSON.stringify(resultado)).toBe(true);

    const success = resultado as ConvertSuccess;

    return { value: JSON.parse(success.text), resultado: success };
}

function jsonToXml(json: string): ConvertSuccess {
    const resultado = convert({ text: json, from: 'json', to: 'xml' });

    expect(resultado.ok, JSON.stringify(resultado)).toBe(true);

    return resultado as ConvertSuccess;
}

describe('convencao do XML', () => {
    it('leva o elemento raiz para a chave de topo', () => {
        const { value } = xmlToJson('<user><name>Ada</name></user>');

        expect(value).toEqual({ user: { name: 'Ada' } });
    });

    it('transforma atributo em @nome', () => {
        const { value } = xmlToJson('<user id="1" active="true"/>');

        expect(value).toEqual({ user: { '@id': '1', '@active': 'true' } });
    });

    it('transforma o texto de um elemento com atributo em #text', () => {
        const { value } = xmlToJson('<p lang="pt">Ola</p>');

        expect(value).toEqual({ p: { '@lang': 'pt', '#text': 'Ola' } });
    });

    it('agrupa irmaos de mesmo nome em array, na ordem', () => {
        const { value } = xmlToJson(
            '<list><item>a</item><item>b</item><item>c</item></list>',
        );

        expect(value).toEqual({ list: { item: ['a', 'b', 'c'] } });
    });

    it('transforma elemento vazio em string vazia', () => {
        // E nao em `null`: o XML nao tem nulo, e inventar um aqui viraria aviso
        // de perda numa conversao que nao perdeu nada.
        const { value } = xmlToJson('<root><a/><b></b></root>');

        expect(value).toEqual({ root: { a: '', b: '' } });
    });

    it('junta o texto em volta dos filhos e avisa que a ordem se perdeu', () => {
        const { value, resultado } = xmlToJson('<p>a<b>c</b>d</p>');

        expect(value).toEqual({ p: { '#text': 'ad', b: 'c' } });
        expect(resultado.losses.map((loss) => loss.code)).toContain(
            'mixed-content-flattened',
        );
    });

    it('descarta a declaracao, os comentarios e o espaco entre elementos', () => {
        const { value, resultado } = xmlToJson(
            '<?xml version="1.0"?>\n<!-- nota -->\n<root>\n  <a>1</a>\n</root>',
        );

        expect(value).toEqual({ root: { a: '1' } });
        // O comentario estava no texto e nao chega ao destino: e uma perda de
        // verdade, contada, e nao um aviso de formato.
        expect(resultado.losses).toContainEqual({
            code: 'comments-dropped',
            count: 1,
        });
    });

    it('mantem todo valor como texto, porque o XML nao tem numero', () => {
        const { value } = xmlToJson('<n><a>1</a><b>true</b></n>');

        expect(value).toEqual({ n: { a: '1', b: 'true' } });
    });
});

describe('convencao do XML: a volta', () => {
    it('escreve atributos, filhos e irmaos repetidos', () => {
        const resultado = jsonToXml(
            '{"user":{"@id":"1","name":"Ada","tag":["a","b"]}}',
        );

        expect(resultado.text).toBe(`<?xml version="1.0" encoding="UTF-8"?>
<user id="1">
  <name>Ada</name>
  <tag>a</tag>
  <tag>b</tag>
</user>`);
    });

    it('faz a ida e a volta sem perder a forma', () => {
        const original = {
            user: {
                '@id': '1',
                name: 'Ada',
                tag: ['a', 'b'],
                address: { city: 'Recife' },
            },
        };
        const xml = jsonToXml(JSON.stringify(original)).text;
        const { value } = xmlToJson(xml);

        expect(value).toEqual(original);
    });

    it('escreve #text antes dos filhos, como o parser junta', () => {
        // O texto sai na linha do elemento e os filhos vem indentados: o que o
        // parser le de volta e o texto aparado, e a volta preserva o valor.
        const resultado = jsonToXml('{"p":{"#text":"a","b":"c"}}');

        expect(resultado.text).toBe(`<?xml version="1.0" encoding="UTF-8"?>
<p>a
  <b>c</b>
</p>`);
    });

    it('envolve em <root> o documento com mais de uma chave de topo', () => {
        const resultado = jsonToXml('{"a":1,"b":2}');

        expect(resultado.text).toBe(`<?xml version="1.0" encoding="UTF-8"?>
<root>
  <a>1</a>
  <b>2</b>
</root>`);
        expect(resultado.losses.map((loss) => loss.code)).toContain(
            'root-wrapped',
        );
    });

    it('envolve em <root> a lista que seria a raiz', () => {
        // Dois elementos raiz nao seriam XML: a lista entra dentro de um, e o
        // nome do item e `item` porque a lista nao tinha nome.
        const resultado = jsonToXml('[1,2]');

        expect(resultado.text).toBe(`<?xml version="1.0" encoding="UTF-8"?>
<root>
  <item>1</item>
  <item>2</item>
</root>`);
        expect(resultado.losses.map((loss) => loss.code)).toContain(
            'root-wrapped',
        );
    });

    it('escapa o texto e o atributo', () => {
        const resultado = jsonToXml('{"p":{"@title":"a & b","#text":"<x>"}}');

        expect(resultado.text).toContain('<p title="a &amp; b">&lt;x&gt;</p>');
    });

    it('recusa o nome reservado xml', () => {
        const resultado = convert({
            text: '{"xmlLang":1}',
            from: 'json',
            to: 'xml',
        });

        expect(resultado.ok).toBe(false);
        expect(resultado.ok ? null : resultado.code).toBe('xml-name');
    });
});
