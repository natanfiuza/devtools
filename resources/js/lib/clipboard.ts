/**
 * Copiar texto para a area de transferencia.
 *
 * Fora de contexto seguro o navegador nem expoe a API, e ela pode ser negada
 * por permissao - por isso a funcao **rejeita** em vez de devolver `false`:
 * quem chama trata a falha com o recado "selecione e copie", que e o caminho
 * que sobra para quem nao tem area de transferencia.
 */
export async function writeClipboard(value: string): Promise<void> {
    if (navigator.clipboard === undefined) {
        throw new Error('sem area de transferencia');
    }

    await navigator.clipboard.writeText(value);
}
