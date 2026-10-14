/**
 * Baixar o documento como arquivo.
 *
 * O `Blob` fica na memoria da pagina e o link e um `<a download>` de verdade:
 * nenhuma requisicao sai daqui, que e a mesma regra do resto da tool. A ancora
 * entra e sai do documento porque um clique em elemento solto e ignorado por
 * parte dos navegadores.
 */

/** Tempo de espera antes de liberar a URL, em milissegundos. */
const RELEASE_DELAY = 1000;

export function downloadText(
    filename: string,
    text: string,
    type = 'application/json',
): void {
    const url = URL.createObjectURL(new Blob([text], { type }));
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
