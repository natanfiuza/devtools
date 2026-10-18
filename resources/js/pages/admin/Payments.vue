<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AdminTable from '@/components/admin/AdminTable.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import admin from '@/routes/admin';
import type {
    AdminColumn,
    AdminPaymentOption,
    AdminPaymentRow,
    Paginator,
} from '@/types';

const props = defineProps<{
    payments: Paginator<AdminPaymentRow>;
    filters: { q: string; status: string };
    total: number;
    options: { plans: AdminPaymentOption[]; methods: string[] };
}>();

const { t } = useI18n();

const columns = computed<AdminColumn[]>(() => [
    { key: 'paidAt', label: t('admin.payments.paid_at'), mono: true },
    { key: 'account', label: t('admin.payments.account') },
    { key: 'plan', label: t('admin.payments.plan') },
    { key: 'method', label: t('admin.payments.method') },
    {
        key: 'amount',
        label: t('admin.payments.amount'),
        align: 'right',
        mono: true,
    },
    { key: 'status', label: t('admin.payments.status') },
]);

const ALL = 'all';
const NO_PLAN = 'none';

const filters = reactive({
    q: props.filters.q,
    status: props.filters.status === '' ? ALL : props.filters.status,
});

function query(extra: Record<string, string | number> = {}) {
    return {
        q: filters.q,
        status: filters.status === ALL ? '' : filters.status,
        ...extra,
    };
}

function apply(): void {
    router.get(admin.payments.index.url(), query(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function clear(): void {
    filters.q = '';
    filters.status = ALL;

    apply();
}

function goToPage(page: number): void {
    router.get(admin.payments.index.url(), query({ page }), {
        preserveState: true,
        preserveScroll: true,
    });
}

/** Formulario de registro manual (ADR-027). */
const open = ref(false);
const firstMethod = props.options.methods[0] ?? 'other';

const form = useForm({
    email: '',
    plan: NO_PLAN,
    amount: '',
    method: firstMethod,
    paid_at: '',
    note: '',
    grant_plan: false,
});

function submit(): void {
    form.transform((data) => ({
        ...data,
        plan: data.plan === NO_PLAN ? '' : data.plan,
    })).post(admin.payments.store.url(), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            open.value = false;
        },
    });
}

function refund(row: AdminPaymentRow): void {
    if (!window.confirm(t('admin.payments.refund_confirm'))) {
        return;
    }

    router.post(
        admin.payments.refund.url(row.id),
        {},
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head :title="t('admin.payments.title')" />

    <div class="space-y-4">
        <header class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-lg font-medium">
                    {{ t('admin.payments.title') }}
                </h2>
                <p class="mt-1 text-sm text-subtle">
                    {{ t('admin.payments.description') }}
                </p>
            </div>

            <Button variant="outline" size="sm" @click="open = !open">
                {{ t('admin.payments.register') }}
            </Button>
        </header>

        <form
            v-if="open"
            class="grid gap-4 rounded-lg border border-border p-4 sm:grid-cols-2 lg:grid-cols-3"
            @submit.prevent="submit"
        >
            <h3 class="text-sm font-medium sm:col-span-2 lg:col-span-3">
                {{ t('admin.payments.form_title') }}
            </h3>

            <div class="grid gap-2">
                <Label for="payment-email">{{
                    t('admin.payments.email')
                }}</Label>
                <Input
                    id="payment-email"
                    v-model="form.email"
                    type="email"
                    autocomplete="off"
                />
                <p class="text-xs text-subtle">
                    {{ t('admin.payments.email_help') }}
                </p>
                <InputError :message="form.errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="payment-amount">
                    {{ t('admin.payments.amount') }}
                </Label>
                <Input
                    id="payment-amount"
                    v-model="form.amount"
                    type="text"
                    inputmode="decimal"
                />
                <p class="text-xs text-subtle">
                    {{ t('admin.payments.amount_help') }}
                </p>
                <InputError :message="form.errors.amount" />
            </div>

            <div class="grid gap-2">
                <Label for="payment-method">
                    {{ t('admin.payments.method') }}
                </Label>
                <Select v-model="form.method">
                    <SelectTrigger id="payment-method">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="method in options.methods"
                            :key="method"
                            :value="method"
                        >
                            {{ t(`admin.payments.methods.${method}`) }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.method" />
            </div>

            <div class="grid gap-2">
                <Label for="payment-plan">{{ t('admin.payments.plan') }}</Label>
                <Select v-model="form.plan">
                    <SelectTrigger id="payment-plan">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="NO_PLAN">
                            {{ t('admin.payments.plan_none') }}
                        </SelectItem>
                        <SelectItem
                            v-for="plan in options.plans"
                            :key="plan.id"
                            :value="String(plan.id)"
                        >
                            {{ plan.name }} · {{ plan.price }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.plan" />
            </div>

            <div class="grid gap-2">
                <Label for="payment-paid-at">
                    {{ t('admin.payments.paid_at') }}
                </Label>
                <Input
                    id="payment-paid-at"
                    v-model="form.paid_at"
                    type="date"
                />
                <p class="text-xs text-subtle">
                    {{ t('admin.payments.paid_at_help') }}
                </p>
                <InputError :message="form.errors.paid_at" />
            </div>

            <div class="grid gap-2">
                <Label for="payment-note">{{ t('admin.payments.note') }}</Label>
                <Input id="payment-note" v-model="form.note" type="text" />
                <InputError :message="form.errors.note" />
            </div>

            <div class="flex items-start gap-2 sm:col-span-2 lg:col-span-3">
                <Checkbox
                    id="payment-grant"
                    :model-value="form.grant_plan"
                    @update:model-value="
                        (value) => (form.grant_plan = value === true)
                    "
                />
                <div>
                    <Label for="payment-grant">
                        {{ t('admin.payments.grant_plan') }}
                    </Label>
                    <p class="text-xs text-subtle">
                        {{ t('admin.payments.grant_plan_help') }}
                    </p>
                </div>
            </div>

            <div class="flex gap-2 sm:col-span-2 lg:col-span-3">
                <Button type="submit" size="sm" :disabled="form.processing">
                    {{ t('admin.payments.submit') }}
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    @click="open = false"
                >
                    {{ t('admin.payments.cancel') }}
                </Button>
            </div>
        </form>

        <form class="flex flex-wrap items-center gap-2" @submit.prevent="apply">
            <Input
                v-model="filters.q"
                class="h-9 max-w-xs"
                type="search"
                :placeholder="t('admin.payments.search_placeholder')"
                :aria-label="t('admin.common.search')"
            />

            <Select v-model="filters.status">
                <SelectTrigger
                    class="h-9 w-44"
                    :aria-label="t('admin.payments.status')"
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem :value="ALL">
                        {{ t('admin.payments.status_all') }}
                    </SelectItem>
                    <SelectItem value="paid">
                        {{ t('admin.payments.statuses.paid') }}
                    </SelectItem>
                    <SelectItem value="refunded">
                        {{ t('admin.payments.statuses.refunded') }}
                    </SelectItem>
                </SelectContent>
            </Select>

            <Button type="submit" variant="outline" size="sm">
                {{ t('admin.common.search') }}
            </Button>
            <Button type="button" variant="ghost" size="sm" @click="clear">
                {{ t('admin.common.clear') }}
            </Button>
        </form>

        <AdminTable
            :columns="columns"
            :rows="payments"
            :empty="t('admin.payments.empty')"
            @page="goToPage"
        >
            <template #cell-paidAt="{ row }">
                {{ row.paidAt }}
            </template>
            <template #cell-account="{ row }">
                {{ row.account }}
            </template>
            <template #cell-plan="{ row }">
                {{ row.plan ?? t('admin.payments.plan_none') }}
            </template>
            <template #cell-method="{ row }">
                {{ t(`admin.payments.methods.${row.method}`) }}
            </template>
            <template #cell-amount="{ row }">
                {{ row.amount }}
            </template>

            <template #cell-status="{ row }">
                <Badge
                    :variant="row.status === 'paid' ? 'secondary' : 'outline'"
                >
                    {{ t(`admin.payments.statuses.${row.status}`) }}
                </Badge>
            </template>

            <template #actions="{ row }">
                <Button
                    v-if="row.status === 'paid'"
                    variant="outline"
                    size="sm"
                    @click="refund(row)"
                >
                    {{ t('admin.payments.refund') }}
                </Button>
            </template>
        </AdminTable>
    </div>
</template>
