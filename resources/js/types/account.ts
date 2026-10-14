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

/** Props das Faturas (`/dashboard/billing`). */
export type AccountBillingProps = {
    plan: AccountPlan;
    subscription: AccountSubscription | null;
    payments: Paginator<AccountPaymentRow>;
    total: number;
};
