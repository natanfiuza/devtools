import { FORMATS, type DataFormat } from '../types';

/**
 * O que a tela sabe de cada formato sem perguntar nada a ninguem.
 *
 * A escolha e **explicita**: a tela nao adivinha o formato do texto colado.
 * Um YAML `a: 1` e um JSON invalido, um XML e um JSON invalido, e um arquivo
 * sem extensao nao diz nada - adivinhar erraria em silencio no caso em que o
 * erro nao aparece (um formato valido lido como outro). Os dois seletores da
 * tela sao a resposta.
 */

/** O formato de quem abre a tela. */
export const DEFAULT_FROM: DataFormat = 'json';

/** O destino de quem abre a tela: e a conversao mais pedida. */
export const DEFAULT_TO: DataFormat = 'yaml';

/** A extensao do arquivo baixado e do exemplo de cada formato. */
export const FORMAT_EXTENSIONS: Record<DataFormat, string> = {
    json: 'json',
    yaml: 'yaml',
    toml: 'toml',
    xml: 'xml',
};

/** O tipo declarado no download; o navegador so usa como sugestao. */
export const FORMAT_MEDIA_TYPES: Record<DataFormat, string> = {
    json: 'application/json',
    yaml: 'text/yaml',
    toml: 'application/toml',
    xml: 'application/xml',
};

/** O que a caixa de arquivo aceita, com todas as extensoes dos quatro formatos. */
export const FILE_EXTENSIONS = [
    '.json',
    '.yaml',
    '.yml',
    '.toml',
    '.xml',
    '.txt',
];

export const FILE_TYPES = [
    'application/json',
    'application/xml',
    'text/xml',
    'application/yaml',
    'text/yaml',
    'application/toml',
    'text/plain',
];

/** A extensao do arquivo sugere o formato de entrada (so a sugestao). */
export function formatFromFilename(name: string): DataFormat | null {
    const lower = name.toLowerCase();

    if (lower.endsWith('.yml') || lower.endsWith('.yaml')) {
        return 'yaml';
    }

    for (const format of FORMATS) {
        if (lower.endsWith(`.${FORMAT_EXTENSIONS[format]}`)) {
            return format;
        }
    }

    return null;
}
