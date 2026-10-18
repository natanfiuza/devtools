/**
 * Nomes: o que vira identificador em cada linguagem de destino.
 *
 * Tres regras convivem aqui, e elas **nao** sao a mesma coisa:
 *
 * - TypeScript aceita qualquer texto como nome de campo - o que nao couber vira
 *   chave entre aspas (`"first-name": string`), e o campo continua com o nome
 *   que tem no JSON;
 * - PHP e Python so aceitam identificador, e o MVP **nao** emite alias: a chave
 *   `first-name` vira `first_name` nos dois. Quem for usar o codigo gerado
 *   precisa saber disso, e por isso o modulo do emissor diz;
 * - palavra reservada nao pode ser nome de campo em Python nem nome de classe
 *   em PHP - que compara sem caixa, entao `Class` tambem e reservada.
 *
 * A regra de identificador daqui **nao** e a do `json-viewer/lib/path.ts`: la o
 * que se decide e se a chave cabe num `.chave` de JSONPath, que so aceita ASCII
 * e aceita `$`; aqui, o que TypeScript, PHP e Python aceitam - e eles aceitam
 * Unicode, entao `ação` e um nome de campo perfeitamente legal.
 */

/** Identificador inteiro: comeca com letra ou `_`, segue com letra, numero ou `_`. */
const IDENTIFIER = /^[\p{L}\p{Nl}_][\p{L}\p{Nl}\p{Nd}\p{Mn}\p{Mc}\p{Pc}]*$/u;

/** Numero no comeco - o unico caso em que um nome valido deixa de ser identificador. */
const STARTS_WITH_DIGIT = /^[\p{Nd}]/u;

/** Fora de identificador, um `_` no lugar do caractere - `2x2` vira `_2x2`. */
const NOT_IDENTIFIER_CHAR = /[^\p{L}\p{Nl}\p{Nd}\p{Mn}\p{Mc}\p{Pc}_]/gu;

export function isIdentifier(text: string): boolean {
    return IDENTIFIER.test(text);
}

/**
 * Texto livre virando identificador - o nome da raiz que a pessoa digitou.
 *
 * O que nao cabe vira `_`, e o que sobra de vazio cai no reserva. O nome sai
 * **como digitado** (so corrigido): quem escreve `meu_tipo` recebe `meu_tipo`,
 * e nao `MeuTipo` - o nome e escolha de quem usa, nao do gerador.
 */
export function identifier(text: string, fallback: string): string {
    const cleaned = text.trim().replace(NOT_IDENTIFIER_CHAR, '_');

    if (cleaned === '') {
        return fallback;
    }

    return STARTS_WITH_DIGIT.test(cleaned) ? `_${cleaned}` : cleaned;
}

function capitalize(part: string): string {
    // Espalhado em caracteres, e nao `charAt`: fora do plano basico (um ideograma
    // raro, por exemplo) `charAt` corta o par surrogado no meio e o nome sai
    // quebrado.
    const [first, ...rest] = [...part];

    return first === undefined ? '' : first.toUpperCase() + rest.join('');
}

/**
 * Nome de tipo a partir da chave: `created_at` vira `CreatedAt`.
 *
 * PascalCase e a convencao das tres linguagens para nome de tipo, e a chave em
 * `snake_case` e o formato mais comum de JSON. Nao ha singularizacao: `tags`
 * vira `Tags`, e nao `Tag` - adivinhar o singular erraria em `status`, em
 * `data` e em toda palavra que o ingles faz no plural sem regra.
 */
export function typeNameFrom(key: string, fallback: string): string {
    const parts = identifier(key, fallback)
        .split('_')
        .filter((part) => part !== '');

    const name = parts.map(capitalize).join('');

    return name === '' ? fallback : name;
}

/**
 * Nome de campo no TypeScript: o do JSON, entre aspas quando precisa.
 *
 * Aspas aqui nao sao renomeacao - `"first-name"` continua sendo a chave
 * `first-name`.
 */
export function tsProperty(key: string): string {
    return isIdentifier(key) ? key : JSON.stringify(key);
}

/** Palavras reservadas do Python: o que nao pode ser nome de campo. */
const PYTHON_KEYWORDS = new Set([
    'False',
    'None',
    'True',
    'and',
    'as',
    'assert',
    'async',
    'await',
    'break',
    'class',
    'continue',
    'def',
    'del',
    'elif',
    'else',
    'except',
    'finally',
    'for',
    'from',
    'global',
    'if',
    'import',
    'in',
    'is',
    'lambda',
    'nonlocal',
    'not',
    'or',
    'pass',
    'raise',
    'return',
    'try',
    'while',
    'with',
    'yield',
]);

/**
 * Nome de campo no Pydantic.
 *
 * Duas correcoes:
 *
 * - sublinhado no comeco vai para o fim. No Pydantic, nome com `_` na frente e
 *   atributo **privado**: o campo sumiria da classe sem erro nenhum - o pior
 *   tipo de falha, a silenciosa;
 * - palavra reservada ganha um `_` no fim (`class` vira `class_`), que e a
 *   convencao do proprio Python.
 */
export function pythonField(key: string): string {
    const leading = /^_+/.exec(key)?.[0].length ?? 0;
    const name = identifier(key.replace(/^_+/, ''), 'field');

    if (PYTHON_KEYWORDS.has(name)) {
        return `${name}_`;
    }

    return leading > 0 ? `${name}_` : name;
}

/**
 * Nome de propriedade no PHP: a chave virada identificador.
 *
 * `first-name` vira `$first_name`; a palavra reservada **nao** mexe, porque o
 * PHP aceita reservada como nome de propriedade desde a versao 7 - o `$class` e
 * `$list` de um JSON passam inteiros.
 */
export function phpProperty(key: string): string {
    return identifier(key, 'field');
}

/** Palavras reservadas do Python que **nao** sao minusculas. */
const PYTHON_TYPE_RESERVED = new Set(['False', 'None', 'True']);

/**
 * Nome de classe no Python.
 *
 * Quase nada a fazer: o PascalCase ja tira o nome do caminho das palavras
 * reservadas, que sao todas minusculas. As tres excecoes sao `None`, `True` e
 * `False` - maiusculas, reservadas, e alcancaveis por uma chave com esse nome.
 */
export function pythonClassName(name: string): string {
    return PYTHON_TYPE_RESERVED.has(name) ? `${name}_` : name;
}

/**
 * Palavras reservadas do PHP, que valem como nome de **classe**.
 *
 * A lista e a de todas elas, e nao a das que parecem provaveis: o PHP compara
 * sem caixa, entao uma chave `foreach` viraria `class Foreach`, que nao compila.
 * Nome de **propriedade** nao entra nesta conta - o PHP aceita reservada ali
 * desde a versao 7.
 */
const PHP_RESERVED = new Set([
    'abstract',
    'and',
    'array',
    'as',
    'break',
    'bool',
    'callable',
    'case',
    'catch',
    'class',
    'clone',
    'const',
    'continue',
    'declare',
    'default',
    'die',
    'do',
    'echo',
    'else',
    'elseif',
    'empty',
    'enddeclare',
    'endfor',
    'endforeach',
    'endif',
    'endswitch',
    'endwhile',
    'enum',
    'eval',
    'exit',
    'extends',
    'false',
    'final',
    'finally',
    'float',
    'fn',
    'for',
    'foreach',
    'function',
    'global',
    'goto',
    'if',
    'implements',
    'include',
    'include_once',
    'instanceof',
    'insteadof',
    'int',
    'interface',
    'isset',
    'iterable',
    'list',
    'match',
    'mixed',
    'namespace',
    'never',
    'new',
    'null',
    'numeric',
    'object',
    'or',
    'parent',
    'print',
    'private',
    'protected',
    'public',
    'readonly',
    'require',
    'require_once',
    'resource',
    'return',
    'self',
    'static',
    'string',
    'switch',
    'throw',
    'trait',
    'true',
    'try',
    'unset',
    'use',
    'var',
    'void',
    'while',
    'xor',
    'yield',
]);

/** Nome de classe no PHP: um `_` no fim quando a reservada pega. */
export function phpClassName(name: string): string {
    return PHP_RESERVED.has(name.toLowerCase()) ? `${name}_` : name;
}
