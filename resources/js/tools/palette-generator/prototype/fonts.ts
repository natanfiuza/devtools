// A mesma familia que o app ja carrega (`resources/css/app.css`); aqui ela
// entra como data URI para o SVG exportado nao depender de rede.
import bold from '../../../../badgeforge/fonts/libre-franklin/LibreFranklin-Bold.ttf?inline';
import regular from '../../../../badgeforge/fonts/libre-franklin/LibreFranklin-Regular.ttf?inline';

/**
 * Fonte embutida no SVG de exportacao (F4, ADR-126).
 *
 * So Regular e Bold: sao os dois pesos do prototipo, e cada arquivo ja pesa
 * ~108 KB em base64. O `font-display: block` evita que o rasterizador desenhe
 * a primeira pintura com a fonte de fallback. Quem so olha o preview no app
 * nao carrega este modulo: a fonte ja esta no CSS global.
 */

let cached: string | null = null;

export function prototypeFontCss(): string {
    if (cached !== null) {
        return cached;
    }

    cached = [
        `@font-face{font-family:'Libre Franklin';src:url("${regular}") format('truetype');font-weight:400;font-style:normal;font-display:block;}`,
        `@font-face{font-family:'Libre Franklin';src:url("${bold}") format('truetype');font-weight:700;font-style:normal;font-display:block;}`,
    ].join('');

    return cached;
}
