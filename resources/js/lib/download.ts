/**
 * Baixar o documento como arquivo.
 *
 * O `Blob` fica na memoria da pagina e o link e um `<a download>` de verdade:
 * nenhuma requisicao sai daqui, que e a mesma regra do resto das tools. A
 * ancora entra e sai do documento porque um clique em elemento solto e ignorado
 * por parte dos navegadores.
 */

/** Tempo de espera antes de liberar a URL, em milissegundos. */
const RELEASE_DELAY = 1000;

export function downloadText(
    filename: string,
    text: string,
    type = 'application/json',
): void {
    downloadBlob(filename, new Blob([text], { type }));
}

/**
 * Baixa um `Blob` ja montado (PNG dos prototipos e da OG, por exemplo).
 *
 * Mesma ancora de `downloadText`; o `Blob` continua so na memoria da pagina.
 */
export function downloadBlob(filename: string, blob: Blob): void {
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement('a');

    anchor.href = url;
    anchor.download = filename;
    anchor.rel = 'noopener';
    anchor.style.display = 'none';

    document.body.appendChild(anchor);
    anchor.click();
    anchor.remove();

    // Revogar na mesma linha cancela o download em parte dos navegadores: a
    // URL precisa sobreviver ate o download comecar.
    window.setTimeout(() => URL.revokeObjectURL(url), RELEASE_DELAY);
}
