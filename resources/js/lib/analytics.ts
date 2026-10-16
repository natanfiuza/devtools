import { setCookie } from '@/lib/cookies';
import type { ConsentDecision } from '@/types';

/**
 * Medicao (GA4) sob consentimento (ADR-013).
 *
 * A tag so entra no <head> depois do aceite e so no cliente: nem o HTML do
 * servidor nem o SSR tem uma linha de GA. A injecao e idempotente - o rodape
 * reabre o banner e o aceite pode se repetir -, mas a tag continua sendo uma.
 */

/** Marca a tag ja injetada, para a injecao ser unica. */
const TAG_ATTRIBUTE = 'data-analytics';

const GTAG_SRC = 'https://www.googletagmanager.com/gtag/js';

/**
 * Google Consent Mode v2. Aqui so ha medicao, entao publicidade fica negada
 * nos dois estados e `analytics_storage` e o que o aceite libera.
 */
const DENIED_BY_DEFAULT = {
    ad_storage: 'denied',
    ad_user_data: 'denied',
    ad_personalization: 'denied',
    analytics_storage: 'denied',
} as const;

/**
 * Opcoes do `config`: o banner promete "Nada de anuncios" e a Politica de
 * Privacidade promete "Nada de cookie de publicidade". O Consent Mode ja nega
 * publicidade nos dois estados - isto e o cinto alem do suspensorio, para a
 * promessa nao depender so do consentimento (ADR-032).
 */
const NO_ADS = {
    allow_google_signals: false,
    allow_ad_personalization_signals: false,
} as const;

/**
 * Um item da fila do gtag: um comando (objeto `arguments`) ou um evento
 * (objeto simples, como o `gtm.dom` que o proprio gtag.js enfileira).
 *
 * `IArguments` no tipo nao e capricho: e o contrato do gtag.js. Um Array nao
 * corresponde a ele, entao o erro de tipo aqui e o que barra a regressao.
 */
type GtagQueueItem = IArguments | Record<string, unknown>;

declare global {
    interface Window {
        /** Fila de comandos do gtag; existe antes da tag carregar. */
        dataLayer?: GtagQueueItem[];
    }
}

/**
 * Enfileira um comando do gtag (o gtag.js le a fila quando carrega).
 *
 * O `arguments` e obrigatorio, nao estilo antigo. O gtag.js so despacha o item
 * da fila que for um objeto `arguments` (`[object Arguments]` ou um objeto com
 * `callee`); um Array comum cai no ramo dos "campos de modelo" e o comando e
 * ignorado em silencio - sem erro no console, sem hit nenhum, mas com a tag
 * detectada. Foi exatamente esse o defeito de 2026-10-16. O snippet oficial do
 * Google faz o mesmo (`dataLayer.push(arguments)`).
 */
function gtag(..._command: unknown[]): void {
    window.dataLayer = window.dataLayer ?? [];

    // O `arguments` e o contrato do gtag.js (ver o docblock acima), entao a
    // regra que pede rest parameters nao vale aqui: o rest seria justamente o
    // Array que o gtag.js ignora.
    // eslint-disable-next-line prefer-rest-params
    window.dataLayer.push(arguments);
}

/** Grava a decisao no cookie que o servidor le (mesmo nome, mesma validade). */
export function writeConsent(
    cookie: string,
    decision: ConsentDecision,
    days: number,
): void {
    setCookie(cookie, decision, days);
}

/**
 * Injeta a tag do GA4, uma unica vez.
 *
 * Quem chama ja decidiu: o aceite e o unico caminho ate aqui. Ainda assim o
 * `consent default` negado vai na frente de todos os comandos - nada e medido
 * antes do `update` que o aceite libera.
 *
 * Aceite repetido (o rodape reabre e o visitante volta atras) nao cria uma
 * segunda tag: reafirma o `granted` no Consent Mode, que e quem liga e desliga
 * a medicao depois que o gtag.js ja rodou.
 */
export function loadAnalytics(id: string): void {
    if (typeof document === 'undefined' || id === '') {
        return;
    }

    if (document.querySelector(`script[${TAG_ATTRIBUTE}]`) !== null) {
        gtag('consent', 'update', { analytics_storage: 'granted' });

        return;
    }

    gtag('consent', 'default', DENIED_BY_DEFAULT);
    gtag('consent', 'update', { analytics_storage: 'granted' });
    gtag('js', new Date());
    gtag('config', id, NO_ADS);

    const script = document.createElement('script');
    script.async = true;
    script.src = `${GTAG_SRC}?id=${encodeURIComponent(id)}`;
    script.setAttribute(TAG_ATTRIBUTE, id);
    document.head.append(script);
}

/**
 * Para a medicao de uma tag que ja esta na pagina.
 *
 * Tirar o <script> do <head> nao resolveria: o gtag.js ja rodou. Quem para o
 * envio e o Consent Mode - o mesmo caminho do aceite, ao contrario. Sem tag
 * nao ha o que negar.
 */
export function denyAnalytics(): void {
    if (
        typeof document === 'undefined' ||
        document.querySelector(`script[${TAG_ATTRIBUTE}]`) === null
    ) {
        return;
    }

    gtag('consent', 'update', { analytics_storage: 'denied' });
}
