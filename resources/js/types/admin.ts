/**
 * Tipos do painel administrativo (ADR-025).
 *
 * Sao os formatos que os controladores de App\Http\Controllers\Admin entregam
 * as telas: o que ja vem formatado (data, dinheiro) chega como string, e o
 * que a tela precisa comparar (status, papel, unidade) chega cru.
 */

/** Coluna da tabela densa (DESIGN: AdminTable). */
export type AdminColumn = {
    key: string;
    label: string;
    align?: 'left' | 'right';
    mono?: boolean;
};

/** Paginacao do Laravel ja serializada pelo Inertia. */
export type Paginator<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
};

export type AdminOverviewStats = {
    accounts: number;
    subscribers: number;
    revenue: { cents: number; formatted: string };
    suspended: number;
};

export type AdminRecentAccount = {
    id: number;
    name: string;
    email: string;
    createdAt: string | null;
    suspended: boolean;
};

export type AdminUserRow = {
    id: number;
    name: string;
    email: string;
    role: string;
    isAdmin: boolean;
    suspended: boolean;
    verified: boolean;
    plan: string;
    createdAt: string | null;
};

export type AdminSubscriberRow = {
    id: number;
    email: string;
    locale: string | null;
    source: string | null;
    status: 'confirmed' | 'pending' | 'unsubscribed';
    createdAt: string | null;
};

export type AdminPaymentRow = {
    id: number;
    account: string;
    plan: string | null;
    amount: string;
    method: string;
    status: 'paid' | 'refunded';
    paidAt: string | null;
    note: string | null;
};

export type AdminPaymentOption = {
    id: number;
    name: string;
    price: string;
};

export type AdminPlanRow = {
    id: number;
    slug: string;
    name: string;
    priceCents: number;
    price: string;
    currency: string;
    interval: string | null;
    monthlyTokens: number;
    maxSchemaBytes: number;
    maxPromptChars: number;
    lifetimeFreeUses: number;
    active: boolean;
    sort: number;
    isFree: boolean;
    subscribers: number;
};

export type AdminAuditRow = {
    id: number;
    action: string;
    actor: string | null;
    target: string | null;
    targetId: number | null;
    meta: Record<string, unknown> | null;
    ip: string | null;
    at: string | null;
};

export type AdminAccount = {
    id: number;
    name: string;
    email: string;
    role: string;
    isAdmin: boolean;
    suspended: boolean;
    suspendedAt: string | null;
    verified: boolean;
    verifiedAt: string | null;
    locale: string | null;
    hasPassword: boolean;
    createdAt: string | null;
};

/** O que a policy autoriza nesta conta (UserPolicy). */
export type AdminAccountCan = {
    manage: boolean;
    changeRole: boolean;
    impersonate: boolean;
};

export type AdminSubscription = {
    plan: string;
    interval: string | null;
    gateway: string;
    status: string;
    start: string | null;
    end: string | null;
    current: boolean;
};

export type AdminQuota = {
    plan: string;
    unit: 'tokens' | 'uses';
    limit: number;
    used: number;
    remaining: number;
    exhausted: boolean;
    windowStart: string | null;
    windowEnd: string | null;
};

export type AdminSocialAccount = {
    provider: string;
    createdAt: string | null;
};

export type AdminSession = {
    ip: string | null;
    agent: string | null;
    lastActivity: string | null;
};

export type AdminAccountPayment = {
    id: number;
    amount: string;
    method: string;
    status: string;
    paidAt: string | null;
    note: string | null;
};

export type AdminPlanOption = {
    id: number;
    name: string;
    interval?: string | null;
    isFree?: boolean;
    price?: string;
};
