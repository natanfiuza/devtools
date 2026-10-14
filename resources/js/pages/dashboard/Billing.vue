<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { billing } from '@/routes/dashboard';
import type { AccountBillingProps, AccountPaymentRow } from '@/types';

/**
 * Faturas: a assinatura vigente e o que ja foi pago.
 *
 * O modelo do bloco e a venda manual (ADR-027), entao nao ha botao de assinar,
 * trocar de plano ou cancelar - isso chega com o gateway, na F6. O que existe e
 * a leitura do proprio historico, na mesma lingua do painel administrativo.
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

            <dl v-else class="divide-y divide-border" data-test="assinatura">
                <div class="flex items-center justify-between gap-4 px-4 py-2">
                    <dt class="text-sm text-subtle">
                        {{ t('account.billing.plan') }}
                    </dt>
                    <dd class="text-sm">{{ subscription.plan }}</dd>
                </div>

                <div class="flex items-center justify-between gap-4 px-4 py-2">
                    <dt class="text-sm text-subtle">
                        {{ t('account.billing.period') }}
                    </dt>
                    <dd class="font-mono text-[13px]">{{ periodo }}</dd>
                </div>

                <div class="flex items-center justify-between gap-4 px-4 py-2">
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

                <div class="flex items-center justify-between gap-4 px-4 py-2">
                    <dt class="text-sm text-subtle">
                        {{ t('account.billing.gateway') }}
                    </dt>
                    <dd class="text-sm">{{ cobranca }}</dd>
                </div>
            </dl>
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
                                <td class="px-3 py-1">{{ row.plan ?? '' }}</td>
                                <td
                                    class="px-3 py-1 text-right font-mono text-[13px]"
                                >
                                    {{ row.amount }}
                                </td>
                                <td class="px-3 py-1">{{ metodo(row) }}</td>
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
