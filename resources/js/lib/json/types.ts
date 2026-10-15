/**
 * O que as ferramentas de JSON manipulam.
 *
 * Nasceu no JSON Viewer e mora em `lib` desde que o JSON to Code passou a ler
 * os mesmos valores. Nada aqui e especifico de Vue: sao os tipos das funcoes
 * puras (parse, arvore, caminho, transformacao, inferencia de tipo), que rodam
 * tanto na thread principal quanto dentro do Web Worker.
 */

export type JsonPrimitive = string | number | boolean | null;

export type JsonValue = JsonPrimitive | JsonValue[] | { [key: string]: JsonValue };

/** Tipo do no na arvore - o que decide a cor e o formato da linha. */
export type JsonNodeKind = 'object' | 'array' | 'string' | 'number' | 'boolean' | 'null';
