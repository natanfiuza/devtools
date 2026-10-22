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

/** Aba Doacoes: o registro publico visto por inteiro pelo admin (F6). */
export type AdminDonationRow = {
    id: number;
    ulid: string;
    donor: string | null;
    email: string | null;
    account: string | null;
    amount: string;
    status: 'pending' | 'paid' | 'expired' | 'refunded' | 'failed';
    gateway: string;
    locale: string;
    createdAt: string | null;
    paidAt: string | null;
    expiresAt: string | null;
    qrCode: string | null;
    ticketUrl: string | null;
};

/** Evento cru do gateway, retido por 30 dias (ADR-047). */
export type AdminWebhookEventRow = {
    id: number;
    gateway: string;
    eventId: string;
    topic: string | null;
    action: string | null;
    resourceId: string | null;
    requestId: string | null;
    status: 'pending' | 'processing' | 'processed' | 'failed' | 'ignored';
    attempts: number;
    error: string | null;
    processedAt: string | null;
    createdAt: string | null;
    payload: Record<string, unknown>;
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

export type AdminIntentRow = {
    id: number;
    userId: number;
    userName: string;
    userEmail: string;
    username: string;
    planSlug: string;
    planName: string;
    interval: string | null;
    phone: string;
    document: string;
    paymentMethod: string;
    notes: string | null;
    status: 'pending' | 'fulfilled' | 'canceled';
    createdAt: string | null;
};

export type AdminSubscriptionRow = {
    id: number;
    userId: number;
    userName: string;
    userEmail: string;
    isUserTrashed: boolean;
    planId: number;
    planName: string;
    interval: string | null;
    gateway: string;
    status: string;
    start: string | null;
    end: string | null;
    createdAt: string | null;
};

export type AdminDeletedAccountRow = {
    id: number;
    name: string;
    email: string;
    isAnonymized: boolean;
    isTrashed: boolean;
    deletedAt: string | null;
    anonymizedAt: string | null;
    daysRemaining: number;
    canRestore: boolean;
};

export type AdminSubscriptionsStats = {
    active: number;
    pendingIntents: number;
    revenueMonth: { cents: number; formatted: string };
    gracePeriod: number;
};

/** Alvo de saida do AI SQL Builder (F7): so o estado ligado/desligado edita. */
export type AdminOutputTargetRow = {
    id: number;
    slug: string;
    label: string;
    language: string;
    dialects: string[];
    plan: string | null;
    enabled: boolean;
    sort: number;
};

/** Metadados do cache de IA; prompt, schema e resposta nunca saem do servidor. */
export type AdminAiCacheRow = {
    id: number;
    hash: string;
    model: string;
    promptVersion: number;
    tokensIn: number;
    tokensOut: number;
    hits: number;
    createdAt: string | null;
    expiresAt: string | null;
    expired: boolean;
};
