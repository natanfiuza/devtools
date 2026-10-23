/**
 * Tipos da area da conta (painel do usuario).
 *
 * Mesma regra do painel administrativo: o que o controlador ja formatou (data,
 * dinheiro) chega como string pronta para a tela; o que a tela precisa comparar
 * (unidade da cota, situacao do pagamento) chega cru.
 */

// `Paginator` e a forma da paginacao do Laravel, nao uma coisa do painel
// administrativo; mora em `./admin` por ter nascido la.
import type { Paginator } from './admin';

/** Plano vigente da conta (`User::effectivePlan`). */
export type AccountPlan = {
    name: string;
    interval: string | null;
    isFree: boolean;
    price: string;
    /** So a visao geral manda a moeda - as faturas ja mostram valor formatado. */
    currency?: string;
};

/** Cota da conta: franquia do plano, consumo e janela. */
export type AccountQuota = {
    unit: 'tokens' | 'uses';
    limit: number;
    used: number;
    remaining: number;
    exhausted: boolean;
    windowStart: string | null;
    windowEnd: string | null;
    nextCreditAt?: string | null;
};

/** Assinatura vigente; `null` quando a conta esta no plano gratuito. */
export type AccountSubscription = {
    plan: string;
    interval: string | null;
    gateway: string;
    status: string;
    start: string | null;
    end: string | null;
    current: boolean;
    /** Quando a conta encerrou a renovacao automatica (F6; so nas faturas). */
    renewalCanceledAt?: string | null;
    /** Primeira cobranca de renovacao recusada no periodo (F6; so nas faturas). */
    renewalFailedAt?: string | null;
    /** Ha preapproval viva para encerrar pelo botao de cancelar. */
    renewable?: boolean;
};

/** Periodo ja pago que ainda nao comecou (empilhado). */
export type AccountNextSubscription = {
    plan: string;
    start: string | null;
    end: string | null;
};

/** Checkout pendente ou recorrencia aguardando a primeira cobranca. */
export type AccountLastCheckout = {
    ulid: string;
    mode: 'one_time' | 'recurring';
    status: string;
    planName: string;
    planSlug: string;
    processing: boolean;
    createdAt: string | null;
};

export type AccountPaymentRow = {
    id: number;
    plan: string | null;
    amount: string;
    method: string;
    status: 'paid' | 'refunded';
    paidAt: string | null;
    note: string | null;
};

/** Props da Visao geral (`/dashboard`). */
export type AccountOverviewProps = {
    plan: AccountPlan;
    quota: AccountQuota;
    subscription: AccountSubscription | null;
    /** Quantidade de pagamentos registrados, e nao a lista deles. */
    payments: number;
};

/** Intencao de assinatura em analise exibida nas faturas (ADR-034). */
export type AccountPendingIntent = {
    id: number;
    planName: string;
    planSlug: string;
    status: string;
    paymentMethod: string;
    createdAt: string | null;
};

/** Props das Faturas (`/dashboard/billing`). */
export type AccountBillingProps = {
    plan: AccountPlan;
    subscription: AccountSubscription | null;
    nextSubscription?: AccountNextSubscription | null;
    lastCheckout?: AccountLastCheckout | null;
    pendingIntent?: AccountPendingIntent | null;
    payments: Paginator<AccountPaymentRow>;
    total: number;
};

/** Linha do historico de execucao/consultas da conta. */
export type AccountHistoryItem = {
    id: string;
    tool: string;
    dialect: string;
    status: 'queued' | 'processing' | 'done' | 'failed';
    quotaUnit: 'tokens' | 'uses';
    tokensUsed: number;
    tokensIn: number | null;
    tokensOut: number | null;
    cacheHit: boolean;
    createdAt: string;
    sql: string | null;
    summary: string | null;
    errorCode: string | null;
};

/** Props do Historico (`/dashboard/history`). */
export type AccountHistoryProps = {
    history?: Paginator<AccountHistoryItem>;
    total?: number;
    /** Toggle do opt-in: grava (ou nao) as proximas consultas. */
    enabled?: boolean;
    /** Conta no plano gratuito: a tela mostra o CTA em vez da lista. */
    locked?: boolean;
    /** Dias de retencao configurados no servidor. */
    retentionDays?: number;
    /** Link para a pagina de planos (Wayfinder nao gera rota fora do grupo). */
    upgradeUrl?: string;
};
