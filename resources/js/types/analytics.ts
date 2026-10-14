/**
 * Medicao e consentimento (docs/adr/ADR-013-consentimento.md).
 *
 * O servidor decide pelo cookie e manda a decisao pronta; o cliente so a
 * executa (grava o mesmo cookie e injeta a tag).
 */

/** Decisao do visitante sobre cookies de medicao. */
export type ConsentDecision = 'granted' | 'denied';

/** Props compartilhadas de `analytics` (App\Http\Middleware\HandleInertiaRequests). */
export type AnalyticsProps = {
    /** Id do GA4, ou nulo quando a medicao esta desligada (`config/analytics.php`). */
    ga4Id: string | null;
    /** Decisao ja registrada, ou nulo quando o visitante ainda nao respondeu. */
    consent: ConsentDecision | null;
    /** Nome do cookie da decisao (fonte unica: o servidor). */
    cookie: string;
    /** Validade do cookie, em dias. */
    days: number;
};
