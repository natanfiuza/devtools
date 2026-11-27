/**
 * O texto do comando virando literal de codigo.
 *
 * Duas linguagens, duas regras: o JavaScript usa aspas simples e precisa
 * escapar a barra e a quebra de linha; o PHP com aspas simples escapa so a
 * barra e a propria aspa, porque nao interpreta `\n` - escrever `\n` ali
 * mudaria o corpo da requisicao.
 */

export function jsString(value: string): string {
    return `'${value
        .replace(/\\/g, '\\\\')
        .replace(/'/g, "\\'")
        .replace(/\r/g, '\\r')
        .replace(/\n/g, '\\n')}'`;
}

export function phpString(value: string): string {
    return `'${value.replace(/\\/g, '\\\\').replace(/'/g, "\\'")}'`;
}
