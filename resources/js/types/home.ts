/**
 * Tipos das telas publicas.
 *
 * Mesma regra da area da conta: o que o controlador ja formatou (preco,
 * numero, tamanho) chega pronto para a tela; o que a tela precisa comparar
 * (plano gratuito, intervalo) chega cru.
 */

/** Um card da secao de precos da home (linha da tabela `plans`). */
export type PublicPlan = {
    slug: string;
    /** Nome do plano: e coluna de dado, aparece igual nos 5 idiomas. */
    name: string;
    /** Preco ja formatado no idioma da tela (ex.: `R$ 29,90`). */
    price: string;
    isFree: boolean;
    /** `month`, `year` ou nulo no plano gratuito. */
    interval: string | null;
    /** Franquia do periodo, com o separador de milhar do idioma. */
    monthlyTokens: string;
    /** Usos vitalicios do plano gratuito. */
    lifetimeFreeUses: string;
    /** Limite de caracteres da descricao (o prompt da IA). */
    promptChars: string;
    /** Limite de schema ja legivel (ex.: `8 KB`). */
    schemaSize: string;
};

/** Sessao de checkout online devolvida em `?checkout=<ulid>` (F6). */
export type CheckoutSummary = {
    ulid: string;
    /** Estado efetivo: pendente vencido ja chega como `expired`. */
    status: 'pending' | 'approved' | 'failed' | 'expired' | 'canceled';
    mode: 'one_time' | 'recurring';
    planSlug: string;
    createdAt: string | null;
};

/** Pedido de intencao de assinatura ja registrado pelo usuario (ADR-033). */
export type PendingSubscriptionIntent = {
    id: number;
    planSlug: string;
    username: string;
    phone: string;
    document: string | null;
    paymentMethod: 'pix' | 'credit_card' | 'boleto';
    notes: string;
    createdAt: string | null;
};
