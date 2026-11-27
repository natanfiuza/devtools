/**
 * O que a caixa de arquivo do `.env` aceita.
 *
 * `.env` nao tem extensao propria - o nome inteiro e o arquivo -, e o mesmo
 * vale para as variantes `.env.local`, `.env.production` e afins. A lista
 * cobre os nomes usuais (o teste da tool confirma o `.env` e as variantes) e o
 * `text/plain` e a segunda chance para os navegadores que informam o tipo.
 */

import type { FileRules } from '@/lib/file';

export const ENV_FILE_RULES: Pick<FileRules, 'extensions' | 'types'> = {
    extensions: [
        '.env',
        '.env.example',
        '.env.local',
        '.env.development',
        '.env.production',
        '.env.staging',
        '.env.testing',
        '.env.test',
    ],
    types: ['text/plain'],
};
