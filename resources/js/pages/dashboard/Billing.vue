<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Clock, RefreshCw, TriangleAlert } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { subscribe } from '@/routes';
import { billing } from '@/routes/dashboard';
import { cancelRecurring } from '@/routes/dashboard/billing';
import type { AccountBillingProps, AccountPaymentRow } from '@/types';

/**
 * Faturas: a assinatura vigente, o que ja foi pago e o estado da renovacao.
 *
 * O modelo do bloco continua sendo a venda manual (ADR-027), mas com o gateway
 * (F6) a conta pode encerrar a propria renovacao automatica: o periodo pago
 * segue ate o fim e o que para e a cobranca seguinte. A tela tambem mostra o
 * proximo periodo empilhado, a falha de renovacao e o checkout em andamento.
 */
const props = defineProps<AccountBillingProps>();

const { t } = useI18n();

/** A assinatura da conta so aparece aqui enquanto esta vigente. */
const periodo = computed<string>(() => {
    const { start, end } = props.subscription ?? {};

    return start != null && end != null
        ? `${start} – ${end}`
        : t('account.billing.no_period');
});

const cobranca = computed<string>(() =>
    props.subscription?.gateway === 'manual'
        ? t('account.billing.gateway_manual')
        : (props.subscription?.gateway ?? ''),
);

/** Estado da renovacao automatica; `manual` nunca teve preapproval. */
const renovacao = computed<string | null>(() => {
    const subscription = props.subscription;

    if (subscription == null) {
        return null;
    }

    if (subscription.renewalCanceledAt != null) {
        return t('account.billing.renewal.status_canceled');
    }

    if (subscription.renewalFailedAt != null) {
        return t('account.billing.renewal.status_failed');
    }

    if (subscription.renewable) {
        return t('account.billing.renewal.status_active');
    }

    return t('account.billing.renewal.status_none');
});

function cancelarRenovacao(): void {
    if (!window.confirm(t('account.billing.renewal.cancel_confirm'))) {
        return;
    }

    router.post(cancelRecurring.url(), {}, { preserveScroll: true });
}

/** Mesmo rotulo do painel administrativo, pela mesma chave. */
function metodo(row: AccountPaymentRow): string {
    return t(`account.billing.methods.${row.method}`);
}

function situacao(row: AccountPaymentRow): string {
    return t(`account.billing.statuses.${row.status}`);
}

function urlDaPagina(page: number): string {
    return billing.url({ query: { page } });
}
</script>

<template>
    <Head :title="t('account.billing.title')" />

    <div class="space-y-6">
        <header>
            <h2 class="text-lg font-medium">
                {{ t('account.billing.title') }}
            </h2>
            <p class="mt-1 text-sm text-subtle">
                {{ t('account.billing.description') }}
            </p>
        </header>

        <section
            v-if="subscription?.renewalFailedAt"
            data-test="renovacao-falhou"
            class="flex items-start gap-3 rounded-lg border border-destructive/40 bg-destructive/5 p-4"
        >
            <TriangleAlert
                class="mt-0.5 size-5 shrink-0 text-destructive"
                aria-hidden="true"
            />
            <div class="space-y-1">
                <p class="font-medium text-foreground">
                    {{ t('account.billing.renewal.failed_title') }}
                </p>
                <p class="text-sm text-muted-foreground">
                    {{
                        t('account.billing.renewal.failed_description', {
                            date: subscription.renewalFailedAt,
                            end: subscription.end ?? '',
                        })
                    }}
                </p>
            </div>
        </section>

        <section
            v-if="subscription?.renewalCanceledAt"
            data-test="renovacao-cancelada"
            class="rounded-lg border border-border bg-muted/40 p-4"
        >
            <p class="font-medium text-foreground">
                {{ t('account.billing.renewal.canceled_title') }}
            </p>
            <p class="mt-1 text-sm text-muted-foreground">
                {{
                    t('account.billing.renewal.canceled_description', {
                        end: subscription.end ?? '',
                    })
                }}
            </p>
        </section>

        <section
            v-if="lastCheckout"
            data-test="checkout-pendente"
            class="flex flex-col gap-3 rounded-lg border border-amber-500/40 bg-amber-500/5 p-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-start gap-3">
                <RefreshCw
                    class="mt-0.5 size-5 shrink-0 text-amber-600"
                    aria-hidden="true"
                />
                <div class="space-y-1">
                    <p class="font-medium text-foreground">
                        {{
                            lastCheckout.processing
                                ? t('account.billing.checkout.processing_title')
                                : t('account.billing.checkout.pending_title')
                        }}
                    </p>
                    <p class="text-sm text-muted-foreground">
                        {{
                            t('account.billing.checkout.description', {
                                plan: lastCheckout.planName,
                                date: lastCheckout.createdAt ?? '',
                            })
                        }}
                    </p>
                    <p
                        v-if="lastCheckout.processing"
                        class="text-sm text-muted-foreground"
                    >
                        {{ t('account.billing.checkout.processing_hint') }}
                    </p>
                </div>
            </div>

            <Button
                variant="outline"
                size="sm"
                as-child
                class="shrink-0 self-start sm:self-center"
            >
                <Link
                    :href="
                        subscribe.url({
                            query: {
                                plan: lastCheckout.planSlug,
                                checkout: lastCheckout.ulid,
                            },
                        })
                    "
                >
                    {{ t('account.billing.checkout.view_details') }}
                </Link>
            </Button>
        </section>

        <section
            v-if="pendingIntent"
            data-test="intencao-pendente"
            class="flex flex-col gap-3 rounded-lg border border-brand/40 bg-brand/5 p-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-start gap-3">
                <Clock
                    class="mt-0.5 size-5 shrink-0 text-brand"
                    aria-hidden="true"
                />
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <span class="font-medium text-foreground">
                            {{ t('account.billing.pending_intent.title') }}
                        </span>
                        <Badge
                            variant="outline"
                            class="border-brand/40 text-brand"
                        >
                            {{ t('account.billing.pending_intent.badge') }}
                        </Badge>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        {{
                            t('account.billing.pending_intent.description', {
                                plan: pendingIntent.planName,
                                date: pendingIntent.createdAt ?? '',
                            })
                        }}
                    </p>
                </div>
            </div>

            <Button
                variant="outline"
                size="sm"
                as-child
                class="shrink-0 self-start sm:self-center"
            >
                <Link
                    :href="
                        subscribe.url({
                            query: { plan: pendingIntent.planSlug },
                        })
                    "
                >
                    {{ t('account.billing.pending_intent.view_details') }}
                </Link>
            </Button>
        </section>

        <section class="rounded-lg border border-border">
            <h3 class="border-b border-border px-4 py-2 text-sm font-medium">
                {{ t('account.billing.subscription') }}
            </h3>

            <div
                v-if="subscription === null"
                class="px-4 py-6"
                data-test="assinatura-ausente"
            >
                <p class="text-sm">
                    {{ t('account.billing.no_subscription') }}
                </p>
                <p class="mt-1 text-sm text-subtle">
                    {{ t('account.billing.free_hint') }}
                </p>
            </div>

            <div v-else data-test="assinatura">
                <dl class="divide-y divide-border">
                    <div
                        class="flex items-center justify-between gap-4 px-4 py-2"
                    >
                        <dt class="text-sm text-subtle">
                            {{ t('account.billing.plan') }}
                        </dt>
                        <dd class="text-sm">
                            {{ subscription.plan }}
                        </dd>
                    </div>

                    <div
                        class="flex items-center justify-between gap-4 px-4 py-2"
                    >
                        <dt class="text-sm text-subtle">
                            {{ t('account.billing.period') }}
                        </dt>
                        <dd class="font-mono text-[13px]">
                            {{ periodo }}
                        </dd>
                    </div>

                    <div
                        class="flex items-center justify-between gap-4 px-4 py-2"
                    >
                        <dt class="text-sm text-subtle">
                            {{ t('account.billing.status') }}
                        </dt>
                        <dd>
                            <Badge
                                :variant="
                                    subscription.status === 'active'
                                        ? 'secondary'
                                        : 'outline'
                                "
                            >
                                {{
                                    t(
                                        `account.billing.subscription_statuses.${subscription.status}`,
                                    )
                                }}
                            </Badge>
                        </dd>
                    </div>

                    <div
                        class="flex items-center justify-between gap-4 px-4 py-2"
                    >
                        <dt class="text-sm text-subtle">
                            {{ t('account.billing.gateway') }}
                        </dt>
                        <dd class="text-sm">
                            {{ cobranca }}
                        </dd>
                    </div>

                    <div
                        class="flex items-center justify-between gap-4 px-4 py-2"
                    >
                        <dt class="text-sm text-subtle">
                            {{ t('account.billing.renewal.label') }}
                        </dt>
                        <dd class="text-sm">
                            {{ renovacao }}
                        </dd>
                    </div>

                    <div
                        v-if="nextSubscription"
                        class="flex items-center justify-between gap-4 px-4 py-2"
                        data-test="proximo-periodo"
                    >
                        <dt class="text-sm text-subtle">
                            {{ t('account.billing.next_period') }}
                        </dt>
                        <dd class="text-sm">
                            {{
                                t('account.billing.next_period_value', {
                                    plan: nextSubscription.plan,
                                    start: nextSubscription.start ?? '',
                                    end: nextSubscription.end ?? '',
                                })
                            }}
                        </dd>
                    </div>
                </dl>

                <div
                    v-if="subscription.renewable"
                    class="border-t border-border px-4 py-3"
                >
                    <Button
                        variant="outline"
                        size="sm"
                        data-test="cancelar-renovacao"
                        @click="cancelarRenovacao"
                    >
                        {{ t('account.billing.renewal.cancel') }}
                    </Button>
                    <p class="mt-2 text-xs text-subtle">
                        {{
                            t('account.billing.renewal.cancel_hint', {
                                end: subscription.end ?? '',
                            })
                        }}
                    </p>
                </div>
            </div>
        </section>

        <section class="space-y-2">
            <h3 class="text-sm font-medium">
                {{ t('account.billing.payments') }}
            </h3>

            <div class="rounded-lg border border-border">
                <div
                    v-if="payments.data.length === 0"
                    class="px-4 py-10 text-center text-sm text-subtle"
                    data-test="pagamentos-vazio"
                >
                    {{ t('account.billing.empty') }}
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full border-collapse text-sm">
                        <thead>
                            <tr class="border-b border-border">
                                <th
                                    scope="col"
                                    class="px-3 py-2 text-left text-[12px] font-medium tracking-wide text-subtle uppercase"
                                >
                                    {{ t('account.billing.paid_at') }}
                                </th>
                                <th
                                    scope="col"
                                    class="px-3 py-2 text-left text-[12px] font-medium tracking-wide text-subtle uppercase"
                                >
                                    {{ t('account.billing.plan') }}
                                </th>
                                <th
                                    scope="col"
                                    class="px-3 py-2 text-right text-[12px] font-medium tracking-wide text-subtle uppercase"
                                >
                                    {{ t('account.billing.amount') }}
                                </th>
                                <th
                                    scope="col"
                                    class="px-3 py-2 text-left text-[12px] font-medium tracking-wide text-subtle uppercase"
                                >
                                    {{ t('account.billing.method') }}
                                </th>
                                <th
                                    scope="col"
                                    class="px-3 py-2 text-left text-[12px] font-medium tracking-wide text-subtle uppercase"
                                >
                                    {{ t('account.billing.status') }}
                                </th>
                                <th
                                    scope="col"
                                    class="px-3 py-2 text-left text-[12px] font-medium tracking-wide text-subtle uppercase"
                                >
                                    {{ t('account.billing.note') }}
                                </th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr
                                v-for="row in payments.data"
                                :key="row.id"
                                class="h-10 border-b border-border last:border-b-0"
                            >
                                <td class="px-3 py-1 font-mono text-[13px]">
                                    {{ row.paidAt }}
                                </td>
                                <td class="px-3 py-1">
                                    {{ row.plan ?? '' }}
                                </td>
                                <td
                                    class="px-3 py-1 text-right font-mono text-[13px]"
                                >
                                    {{ row.amount }}
                                </td>
                                <td class="px-3 py-1">
                                    {{ metodo(row) }}
                                </td>
                                <td class="px-3 py-1">
                                    <Badge
                                        :variant="
                                            row.status === 'paid'
                                                ? 'secondary'
                                                : 'outline'
                                        "
                                    >
                                        {{ situacao(row) }}
                                    </Badge>
                                </td>
                                <td
                                    class="max-w-[16rem] truncate px-3 py-1 text-subtle"
                                >
                                    {{ row.note ?? '' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-if="payments.data.length > 0"
                    class="flex items-center justify-between border-t border-border px-3 py-2 text-sm text-subtle"
                >
                    <span>
                        {{
                            t('account.billing.total', {
                                total: payments.total,
                            })
                        }}
                    </span>

                    <div class="flex items-center gap-3">
                        <span>
                            {{
                                t('account.billing.page_of', {
                                    page: payments.current_page,
                                    last: payments.last_page,
                                })
                            }}
                        </span>

                        <div class="flex gap-1">
                            <Button
                                v-if="payments.current_page > 1"
                                variant="outline"
                                size="sm"
                                as-child
                            >
                                <Link
                                    :href="
                                        urlDaPagina(payments.current_page - 1)
                                    "
                                    preserve-scroll
                                >
                                    {{ t('account.billing.previous') }}
                                </Link>
                            </Button>
                            <Button v-else variant="outline" size="sm" disabled>
                                {{ t('account.billing.previous') }}
                            </Button>

                            <Button
                                v-if="
                                    payments.current_page < payments.last_page
                                "
                                variant="outline"
                                size="sm"
                                as-child
                            >
                                <Link
                                    :href="
                                        urlDaPagina(payments.current_page + 1)
                                    "
                                    preserve-scroll
                                >
                                    {{ t('account.billing.next') }}
                                </Link>
                            </Button>
                            <Button v-else variant="outline" size="sm" disabled>
                                {{ t('account.billing.next') }}
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
