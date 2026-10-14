/**
 * O que o JSON Viewer manipula.
 *
 * Nada aqui e especifico de Vue: sao os tipos das funcoes puras (parse,
 * arvore, caminho, transformacao), que rodam tanto na thread principal quanto
 * dentro do Web Worker.
 */

export type JsonPrimitive = string | number | boolean | null;

export type JsonValue = JsonPrimitive | JsonValue[] | { [key: string]: JsonValue };

/** Tipo do no na arvore - o que decide a cor e o formato da linha. */
export type JsonNodeKind = 'object' | 'array' | 'string' | 'number' | 'boolean' | 'null';
