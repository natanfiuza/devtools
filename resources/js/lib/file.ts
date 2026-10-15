/**
 * Ler um arquivo de texto escolhido por quem usa.
 *
 * O arquivo nunca sai do navegador: ele e lido com `FileReader` e o texto vai
 * para o mesmo lugar onde a pessoa colaria a mao. Nenhuma tool envia nada ao
 * servidor (CLAUDE.md), entao nao existe upload - so leitura local.
 *
 * O que a tool aceita (extensoes, tipos e o teto de tamanho) e regra de cada
 * ferramenta e chega aqui por parametro; o que este modulo decide e a **ordem**
 * das checagens, que e a mesma em toda tool: tamanho antes de tipo, porque um
 * arquivo grande demais e recusado pelo tamanho mesmo que a extensao esteja
 * certa - e o recado na tela precisa dizer o motivo que realmente barrou.
 */

/** Por que um arquivo nao foi aceito - cada motivo tem um recado traduzido. */
export type FileErrorKind = 'file_too_large' | 'file_type' | 'file_unreadable';

/** O que a tool aceita. */
export interface FileRules {
    /** Teto em bytes: o mesmo limite de entrada que a tool recebe do servidor. */
    maxBytes: number;
    /** Extensoes com ponto, em minusculas (ex.: `['.json', '.txt']`). */
    extensions: string[];
    /** Tipos MIME aceitos, quando o navegador informa algum. */
    types: string[];
}

/** O texto lido, ou o motivo da recusa. */
export type FileRead =
    | { ok: true; text: string }
    | { ok: false; kind: FileErrorKind; size: number };

/**
 * O arquivo passa pelas regras da tool?
 *
 * A extensao decide primeiro e o tipo MIME e a segunda chance: o navegador
 * manda `type` vazio para muita coisa (e um `.sql` costuma vir assim), entao
 * exigir o MIME recusaria arquivos legitimos.
 */
export function acceptsFile(file: File, rules: FileRules): boolean {
    const name = file.name.toLowerCase();

    return (
        rules.extensions.some((extension) => name.endsWith(extension)) ||
        rules.types.includes(file.type)
    );
}

/**
 * O `accept` do seletor de arquivo, montado das mesmas regras.
 *
 * Uma fonte so: o que o seletor do navegador oferece e o que a tool aceita
 * depois de ler sao a mesma lista, entao nao ha como as duas divergirem.
 */
export function acceptAttribute(
    rules: Pick<FileRules, 'extensions' | 'types'>,
): string {
    return [...rules.extensions, ...rules.types].join(',');
}

function readAsText(file: File): Promise<string> {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();

        reader.onload = () => resolve(String(reader.result ?? ''));
        // O texto do erro do navegador nao vai para a tela - ele so decide que
        // a leitura falhou, e o recado sai traduzido por quem chamou.
        reader.onerror = () => reject(new Error('leitura falhou'));
        reader.readAsText(file);
    });
}

/** Le o arquivo (aplicando as regras) e devolve o texto ou o motivo da recusa. */
export async function readTextFile(
    file: File,
    rules: FileRules,
): Promise<FileRead> {
    if (file.size > rules.maxBytes) {
        return { ok: false, kind: 'file_too_large', size: file.size };
    }

    if (!acceptsFile(file, rules)) {
        return { ok: false, kind: 'file_type', size: file.size };
    }

    try {
        return { ok: true, text: await readAsText(file) };
    } catch {
        return { ok: false, kind: 'file_unreadable', size: file.size };
    }
}
