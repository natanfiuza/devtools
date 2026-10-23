<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AdminTable from '@/components/admin/AdminTable.vue';
import ConfirmIntentPaymentDialog from '@/components/admin/ConfirmIntentPaymentDialog.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
    AdminDeletedAccountRow,
    AdminIntentRow,
    AdminPaymentOption,
    AdminPaymentRow,
    AdminSubscriptionRow,
    AdminSubscriptionsStats,
    Paginator,
} from '@/types';

const props = defineProps<{
    tab: 'active' | 'intents' | 'payments' | 'deleted';
    filters: { q: string; status: string; plan: number | null };
    stats: AdminSubscriptionsStats;
    plans: Array<{
        id: number;
        name: string;
        interval: string | null;
        price: string;
    }>;
    subscriptions: Paginator<AdminSubscriptionRow> | null;
    intents: Paginator<AdminIntentRow> | null;
    payments: Paginator<AdminPaymentRow> | null;
    paymentOptions: { plans: AdminPaymentOption[]; methods: string[] } | null;
    deletedAccounts: Paginator<AdminDeletedAccountRow> | null;
}>();

const { t } = useI18n();

const ALL = 'all';
const NO_PLAN = 'none';

/** Metricas superiores do Hub. */
const cards = computed<Array<{ label: string; value: string }>>(() => [
    {
        label: t('admin.subscriptions.stats.active'),
        value: String(props.stats.active),
    },
    {
        label: t('admin.subscriptions.stats.pending_intents'),
        value: String(props.stats.pendingIntents),
    },
    {
        label: t('admin.subscriptions.stats.revenue_month'),
        value: props.stats.revenueMonth.formatted,
    },
    {
        label: t('admin.subscriptions.stats.grace_period'),
        value: String(props.stats.gracePeriod),
    },
]);

/** Filtros reativos locais. */
const filterForm = reactive({
    q: props.filters.q,
    status: props.filters.status === '' ? ALL : props.filters.status,
    plan: props.filters.plan ? String(props.filters.plan) : ALL,
});

function switchTab(
    newTab: 'active' | 'intents' | 'payments' | 'deleted',
): void {
    router.get(
        admin.subscriptions.index.url(),
        { tab: newTab },
        { preserveState: false, preserveScroll: true },
    );
}

function query(extra: Record<string, string | number> = {}) {
    return {
        tab: props.tab,
        q: filterForm.q,
        status: filterForm.status === ALL ? '' : filterForm.status,
        plan: filterForm.plan === ALL ? '' : filterForm.plan,
        ...extra,
    };
}

function apply(): void {
    router.get(admin.subscriptions.index.url(), query(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function clear(): void {
    filterForm.q = '';
    filterForm.status = ALL;
    filterForm.plan = ALL;
    apply();
}

function goToPage(page: number): void {
    router.get(admin.subscriptions.index.url(), query({ page }), {
        preserveState: true,
        preserveScroll: true,
    });
}

/* -------------------------------------------------------------------------- */
/* Aba 1: Assinaturas Vigentes                                                */
/* -------------------------------------------------------------------------- */
const activeColumns = computed<AdminColumn[]>(() => [
    { key: 'account', label: t('admin.subscriptions.active_tab.account') },
    { key: 'plan', label: t('admin.subscriptions.active_tab.plan') },
    {
        key: 'period',
        label: t('admin.subscriptions.active_tab.period'),
        mono: true,
    },
    { key: 'gateway', label: t('admin.subscriptions.active_tab.gateway') },
    { key: 'status', label: t('admin.payments.status') },
]);

/* -------------------------------------------------------------------------- */
/* Aba 2: Solicitacoes (Intencoes Pro)                                        */
/* -------------------------------------------------------------------------- */
const intentColumns = computed<AdminColumn[]>(() => [
    { key: 'account', label: t('admin.intents.account') },
    { key: 'username', label: t('admin.intents.username'), mono: true },
    { key: 'plan', label: t('admin.intents.plan') },
    { key: 'contact', label: t('admin.intents.contact') },
    { key: 'paymentMethod', label: t('admin.intents.payment_method') },
    { key: 'status', label: t('admin.intents.status') },
    { key: 'createdAt', label: t('admin.intents.created'), mono: true },
]);

const confirmIntentTarget = ref<AdminIntentRow | null>(null);

function openConfirmIntentPayment(intent: AdminIntentRow): void {
    confirmIntentTarget.value = intent;
}

function cancelIntent(intent: AdminIntentRow): void {
    router.post(
        admin.intents.cancel.url(intent.id),
        {},
        { preserveScroll: true },
    );
}

/* -------------------------------------------------------------------------- */
/* Aba 3: Pagamentos                                                          */
/* -------------------------------------------------------------------------- */
const paymentColumns = computed<AdminColumn[]>(() => [
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

const newPaymentOpen = ref(false);
const paymentForm = useForm({
    email: '',
    plan: NO_PLAN,
    amount: '',
    method: 'pix',
    paid_at: '',
    note: '',
});

function submitPayment(): void {
    paymentForm
        .transform((data) => ({
            ...data,
            plan: data.plan === NO_PLAN ? null : Number(data.plan),
            paid_at: data.paid_at === '' ? null : data.paid_at,
            note: data.note === '' ? null : data.note,
        }))
        .post(admin.payments.store.url(), {
            preserveScroll: true,
            onSuccess: () => {
                paymentForm.reset();
                paymentForm.plan = NO_PLAN;
                paymentForm.method = 'pix';
                newPaymentOpen.value = false;
            },
        });
}

const refundTarget = ref<AdminPaymentRow | null>(null);

function confirmRefund(payment: AdminPaymentRow): void {
    refundTarget.value = payment;
}

function executeRefund(): void {
    if (!refundTarget.value) return;
    router.post(
        admin.payments.refund.url(refundTarget.value.id),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                refundTarget.value = null;
            },
        },
    );
}

/* -------------------------------------------------------------------------- */
/* Aba 4: Contas Excluidas / Carencia                                         */
/* -------------------------------------------------------------------------- */
const deletedColumns = computed<AdminColumn[]>(() => [
    { key: 'account', label: t('admin.subscriptions.deleted_tab.account') },
    { key: 'situation', label: t('admin.subscriptions.deleted_tab.situation') },
    {
        key: 'deletedAt',
        label: t('admin.subscriptions.deleted_tab.deleted_at'),
        mono: true,
    },
]);

const restoreTarget = ref<AdminDeletedAccountRow | null>(null);

function confirmRestore(account: AdminDeletedAccountRow): void {
    restoreTarget.value = account;
}

function executeRestore(): void {
    if (!restoreTarget.value) return;
    router.post(
        admin.users.restore.url(restoreTarget.value.id),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                restoreTarget.value = null;
            },
        },
    );
}
</script>

<template>
  <Head :title="t('admin.subscriptions.title')" />

  <div class="space-y-6">
    <header>
      <h2 class="text-lg font-medium">
        {{ t('admin.subscriptions.title') }}
      </h2>
      <p class="mt-1 text-sm text-subtle">
        {{ t('admin.subscriptions.description') }}
      </p>
    </header>

    <!-- Indicadores Superiores -->
    <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <div
        v-for="card in cards"
        :key="card.label"
        class="rounded-lg border border-border p-4"
      >
        <dt class="text-[12px] tracking-wide text-subtle uppercase">
          {{ card.label }}
        </dt>
        <dd class="mt-2 font-mono text-2xl">
          {{ card.value }}
        </dd>
      </div>
    </dl>

    <!-- Abas do Hub -->
    <div class="border-b border-border">
      <nav
        class="-mb-px flex space-x-6"
        aria-label="Tabs"
      >
        <button
          type="button"
          class="border-b-2 py-3 text-sm font-medium transition-colors"
          :class="
            tab === 'active'
              ? 'border-accent text-foreground'
              : 'border-transparent text-subtle hover:border-border hover:text-foreground'
          "
          @click="switchTab('active')"
        >
          {{ t('admin.subscriptions.tabs.active') }}
        </button>
        <button
          type="button"
          class="border-b-2 py-3 text-sm font-medium transition-colors"
          :class="
            tab === 'intents'
              ? 'border-accent text-foreground'
              : 'border-transparent text-subtle hover:border-border hover:text-foreground'
          "
          @click="switchTab('intents')"
        >
          {{ t('admin.subscriptions.tabs.intents') }}
        </button>
        <button
          type="button"
          class="border-b-2 py-3 text-sm font-medium transition-colors"
          :class="
            tab === 'payments'
              ? 'border-accent text-foreground'
              : 'border-transparent text-subtle hover:border-border hover:text-foreground'
          "
          @click="switchTab('payments')"
        >
          {{ t('admin.subscriptions.tabs.payments') }}
        </button>
        <button
          type="button"
          class="border-b-2 py-3 text-sm font-medium transition-colors"
          :class="
            tab === 'deleted'
              ? 'border-accent text-foreground'
              : 'border-transparent text-subtle hover:border-border hover:text-foreground'
          "
          @click="switchTab('deleted')"
        >
          {{ t('admin.subscriptions.tabs.deleted') }}
        </button>
      </nav>
    </div>

    <!-- ================================================================ -->
    <!-- ABA 1: ASSINATURAS VIGENTES                                      -->
    <!-- ================================================================ -->
    <section
      v-if="tab === 'active'"
      class="space-y-4"
    >
      <form
        class="flex flex-wrap items-center gap-2"
        @submit.prevent="apply"
      >
        <Input
          v-model="filterForm.q"
          class="h-9 max-w-xs"
          type="search"
          :placeholder="
            t('admin.subscriptions.active_tab.search_placeholder')
          "
          :aria-label="t('admin.common.search')"
        />

        <Select v-model="filterForm.plan">
          <SelectTrigger class="h-9 w-48">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem :value="ALL">
              {{ t('admin.subscriptions.active_tab.all_plans') }}
            </SelectItem>
            <SelectItem
              v-for="plan in plans"
              :key="plan.id"
              :value="String(plan.id)"
            >
              {{ plan.name }}
            </SelectItem>
          </SelectContent>
        </Select>

        <Button
          type="submit"
          variant="outline"
          size="sm"
        >
          {{ t('admin.common.search') }}
        </Button>
        <Button
          type="button"
          variant="ghost"
          size="sm"
          @click="clear"
        >
          {{ t('admin.common.clear') }}
        </Button>
      </form>

      <AdminTable
        v-if="subscriptions"
        :columns="activeColumns"
        :rows="subscriptions"
        :empty="t('admin.subscriptions.active_tab.empty')"
        @page="goToPage"
      >
        <template #cell-account="{ row }">
          <div class="flex flex-col">
            <Link
              :href="admin.users.show.url(row.userId)"
              class="font-medium hover:underline"
            >
              {{ row.userName }}
            </Link>
            <span class="text-xs text-subtle">
              {{ row.userEmail }}
            </span>
            <Badge
              v-if="row.isUserTrashed"
              variant="destructive"
              class="mt-1 w-fit text-[10px]"
            >
              {{ t('admin.users.anonymized') }}
            </Badge>
          </div>
        </template>

        <template #cell-plan="{ row }">
          <div class="flex flex-col">
            <span class="font-medium">{{ row.planName }}</span>
            <span
              v-if="row.interval"
              class="text-xs text-subtle"
            >
              {{ row.interval }}
            </span>
          </div>
        </template>

        <template #cell-period="{ row }">
          <span v-if="row.end">{{ row.start }} — {{ row.end }}</span>
          <span v-else>{{ row.start }} (vitalícia)</span>
        </template>

        <template #cell-gateway="{ row }">
          <Badge variant="outline">
            {{ row.gateway }}
          </Badge>
        </template>

        <template #cell-status="{ row }">
          <Badge variant="default">
            {{ row.status }}
          </Badge>
        </template>

        <template #actions="{ row }">
          <Link
            :href="admin.users.show.url(row.userId)"
            class="text-xs text-subtle hover:text-foreground hover:underline"
          >
            {{ t('admin.subscriptions.active_tab.view_account') }}
          </Link>
        </template>
      </AdminTable>
    </section>

    <!-- ================================================================ -->
    <!-- ABA 2: SOLICITACOES DE ASSINATURA (INTENCOES)                    -->
    <!-- ================================================================ -->
    <section
      v-if="tab === 'intents'"
      class="space-y-4"
    >
      <form
        class="flex flex-wrap items-center gap-2"
        @submit.prevent="apply"
      >
        <Input
          v-model="filterForm.q"
          class="h-9 max-w-xs"
          type="search"
          :placeholder="
            t('admin.subscriptions.active_tab.search_placeholder')
          "
          :aria-label="t('admin.common.search')"
        />

        <Select v-model="filterForm.status">
          <SelectTrigger class="h-9 w-44">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem :value="ALL">
              {{ t('admin.intents.status_all') }}
            </SelectItem>
            <SelectItem value="pending">
              {{ t('admin.intents.status_pending') }}
            </SelectItem>
            <SelectItem value="fulfilled">
              {{ t('admin.intents.status_fulfilled') }}
            </SelectItem>
            <SelectItem value="canceled">
              {{ t('admin.intents.status_canceled') }}
            </SelectItem>
          </SelectContent>
        </Select>

        <Button
          type="submit"
          variant="outline"
          size="sm"
        >
          {{ t('admin.common.search') }}
        </Button>
        <Button
          type="button"
          variant="ghost"
          size="sm"
          @click="clear"
        >
          {{ t('admin.common.clear') }}
        </Button>
      </form>

      <AdminTable
        v-if="intents"
        :columns="intentColumns"
        :rows="intents"
        :empty="t('admin.intents.empty')"
        @page="goToPage"
      >
        <template #cell-account="{ row }">
          <Link
            :href="admin.users.show.url(row.userId)"
            class="font-medium hover:underline"
          >
            {{ row.userName }}
          </Link>
          <div class="text-xs text-subtle">
            {{ row.userEmail }}
          </div>
        </template>

        <template #cell-username="{ row }">
          @{{ row.username }}
        </template>

        <template #cell-plan="{ row }">
          <span>{{ row.planName }}</span>
          <span
            v-if="row.interval"
            class="text-xs text-subtle"
          >
            ({{ row.planSlug }})
          </span>
        </template>

        <template #cell-contact="{ row }">
          <div class="font-mono text-xs">
            {{ row.phone }}
          </div>
          <div class="font-mono text-xs text-subtle">
            {{ row.document }}
          </div>
          <div
            v-if="row.notes"
            class="mt-0.5 max-w-[14rem] truncate text-xs text-subtle"
            :title="row.notes"
          >
            {{ row.notes }}
          </div>
        </template>

        <template #cell-paymentMethod="{ row }">
          {{
            t(`home.subscribe.payment_methods.${row.paymentMethod}`)
          }}
        </template>

        <template #cell-status="{ row }">
          <Badge
            :variant="
              row.status === 'fulfilled'
                ? 'secondary'
                : row.status === 'canceled'
                  ? 'outline'
                  : 'default'
            "
          >
            {{ t(`admin.intents.status_${row.status}`) }}
          </Badge>
        </template>

        <template #cell-createdAt="{ row }">
          {{ row.createdAt }}
        </template>

        <template #actions="{ row }">
          <div class="flex items-center justify-end gap-1">
            <Button
              v-if="row.status === 'pending'"
              variant="outline"
              size="sm"
              :data-test="`hub-intent-confirm-${row.id}`"
              @click="openConfirmIntentPayment(row)"
            >
              {{ t('admin.intents.confirm_payment') }}
            </Button>
            <Button
              v-if="row.status !== 'canceled'"
              variant="ghost"
              size="sm"
              @click="cancelIntent(row)"
            >
              {{ t('admin.intents.cancel') }}
            </Button>
          </div>
        </template>
      </AdminTable>
    </section>

    <!-- ================================================================ -->
    <!-- ABA 3: PAGAMENTOS                                                -->
    <!-- ================================================================ -->
    <section
      v-if="tab === 'payments'"
      class="space-y-4"
    >
      <div class="flex items-center justify-between">
        <form
          class="flex flex-wrap items-center gap-2"
          @submit.prevent="apply"
        >
          <Input
            v-model="filterForm.q"
            class="h-9 max-w-xs"
            type="search"
            :placeholder="t('admin.payments.search_placeholder')"
            :aria-label="t('admin.common.search')"
          />

          <Select v-model="filterForm.status">
            <SelectTrigger class="h-9 w-44">
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

          <Button
            type="submit"
            variant="outline"
            size="sm"
          >
            {{ t('admin.common.search') }}
          </Button>
          <Button
            type="button"
            variant="ghost"
            size="sm"
            @click="clear"
          >
            {{ t('admin.common.clear') }}
          </Button>
        </form>

        <Button
          variant="default"
          size="sm"
          @click="newPaymentOpen = !newPaymentOpen"
        >
          {{
            newPaymentOpen
              ? t('admin.payments.cancel')
              : t('admin.payments.register')
          }}
        </Button>
      </div>

      <!-- Form de Cadastro Manual de Pagamento -->
      <form
        v-if="newPaymentOpen && paymentOptions"
        class="grid gap-3 rounded-lg border border-border p-4 sm:grid-cols-2 lg:grid-cols-3"
        @submit.prevent="submitPayment"
      >
        <div class="grid gap-2">
          <Label for="hub-payment-email">
            {{ t('admin.payments.email') }}
          </Label>
          <Input
            id="hub-payment-email"
            v-model="paymentForm.email"
            type="email"
            placeholder="usuario@exemplo.com"
            required
          />
          <InputError :message="paymentForm.errors.email" />
        </div>

        <div class="grid gap-2">
          <Label for="hub-payment-amount">
            {{ t('admin.payments.amount') }}
          </Label>
          <Input
            id="hub-payment-amount"
            v-model="paymentForm.amount"
            type="text"
            placeholder="29,90"
            required
          />
          <InputError :message="paymentForm.errors.amount" />
        </div>

        <div class="grid gap-2">
          <Label for="hub-payment-method">
            {{ t('admin.payments.method') }}
          </Label>
          <Select v-model="paymentForm.method">
            <SelectTrigger id="hub-payment-method">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem
                v-for="method in paymentOptions.methods"
                :key="method"
                :value="method"
              >
                {{ t(`admin.payments.methods.${method}`) }}
              </SelectItem>
            </SelectContent>
          </Select>
          <InputError :message="paymentForm.errors.method" />
        </div>

        <div class="grid gap-2">
          <Label for="hub-payment-plan">
            {{ t('admin.payments.plan') }}
          </Label>
          <Select v-model="paymentForm.plan">
            <SelectTrigger id="hub-payment-plan">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem :value="NO_PLAN">
                {{ t('admin.payments.plan_none') }}
              </SelectItem>
              <SelectItem
                v-for="plan in paymentOptions.plans"
                :key="plan.id"
                :value="String(plan.id)"
              >
                {{ plan.name }} · {{ plan.price }}
              </SelectItem>
            </SelectContent>
          </Select>
          <InputError :message="paymentForm.errors.plan" />
        </div>

        <div class="grid gap-2">
          <Label for="hub-payment-paid-at">
            {{ t('admin.payments.paid_at') }}
          </Label>
          <Input
            id="hub-payment-paid-at"
            v-model="paymentForm.paid_at"
            type="date"
          />
          <InputError :message="paymentForm.errors.paid_at" />
        </div>

        <div class="grid gap-2">
          <Label for="hub-payment-note">
            {{ t('admin.payments.note') }}
          </Label>
          <Input
            id="hub-payment-note"
            v-model="paymentForm.note"
            type="text"
          />
          <InputError :message="paymentForm.errors.note" />
        </div>

        <p class="text-xs text-subtle sm:col-span-2 lg:col-span-3">
          {{ t('admin.payments.grant_plan_help') }}
        </p>

        <div class="flex gap-2 sm:col-span-2 lg:col-span-3">
          <Button
            type="submit"
            size="sm"
            :disabled="paymentForm.processing"
          >
            {{ t('admin.payments.submit') }}
          </Button>
          <Button
            type="button"
            variant="ghost"
            size="sm"
            @click="newPaymentOpen = false"
          >
            {{ t('admin.payments.cancel') }}
          </Button>
        </div>
      </form>

      <AdminTable
        v-if="payments"
        :columns="paymentColumns"
        :rows="payments"
        :empty="t('admin.payments.empty')"
        @page="goToPage"
      >
        <template #cell-paidAt="{ row }">
          {{ row.paidAt }}
        </template>

        <template #cell-account="{ row }">
          <span>{{ row.account }}</span>
        </template>

        <template #cell-plan="{ row }">
          <span v-if="row.plan">{{ row.plan }}</span>
          <span
            v-else
            class="text-subtle"
          >
            {{ t('admin.payments.plan_none') }}
          </span>
        </template>

        <template #cell-method="{ row }">
          {{ t(`admin.payments.methods.${row.method}`) }}
        </template>

        <template #cell-amount="{ row }">
          {{ row.amount }}
        </template>

        <template #cell-status="{ row }">
          <Badge
            :variant="row.status === 'paid' ? 'default' : 'outline'"
          >
            {{ t(`admin.payments.statuses.${row.status}`) }}
          </Badge>
        </template>

        <template #actions="{ row }">
          <Button
            v-if="row.status === 'paid'"
            variant="ghost"
            size="sm"
            @click="confirmRefund(row)"
          >
            {{ t('admin.payments.refund') }}
          </Button>
        </template>
      </AdminTable>
    </section>

    <!-- ================================================================ -->
    <!-- ABA 4: CONTAS EXCLUIDAS / CARENCIA                               -->
    <!-- ================================================================ -->
    <section
      v-if="tab === 'deleted'"
      class="space-y-4"
    >
      <form
        class="flex flex-wrap items-center gap-2"
        @submit.prevent="apply"
      >
        <Input
          v-model="filterForm.q"
          class="h-9 max-w-xs"
          type="search"
          :placeholder="
            t('admin.subscriptions.deleted_tab.search_placeholder')
          "
          :aria-label="t('admin.common.search')"
        />

        <Select v-model="filterForm.status">
          <SelectTrigger class="h-9 w-48">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem :value="ALL">
              {{
                t('admin.subscriptions.deleted_tab.status_all')
              }}
            </SelectItem>
            <SelectItem value="grace">
              {{
                t(
                  'admin.subscriptions.deleted_tab.status_grace',
                )
              }}
            </SelectItem>
            <SelectItem value="anonymized">
              {{
                t(
                  'admin.subscriptions.deleted_tab.status_anonymized',
                )
              }}
            </SelectItem>
          </SelectContent>
        </Select>

        <Button
          type="submit"
          variant="outline"
          size="sm"
        >
          {{ t('admin.common.search') }}
        </Button>
        <Button
          type="button"
          variant="ghost"
          size="sm"
          @click="clear"
        >
          {{ t('admin.common.clear') }}
        </Button>
      </form>

      <AdminTable
        v-if="deletedAccounts"
        :columns="deletedColumns"
        :rows="deletedAccounts"
        :empty="t('admin.subscriptions.deleted_tab.empty')"
        @page="goToPage"
      >
        <template #cell-account="{ row }">
          <div class="flex flex-col">
            <span class="font-medium">{{ row.name }}</span>
            <span class="text-xs text-subtle">{{ row.email }}</span>
          </div>
        </template>

        <template #cell-situation="{ row }">
          <div class="flex items-center gap-2">
            <Badge
              v-if="row.isAnonymized"
              variant="secondary"
            >
              {{
                t(
                  'admin.subscriptions.deleted_tab.anonymized_badge',
                )
              }}
            </Badge>
            <div
              v-else
              class="flex items-center gap-2"
            >
              <Badge variant="destructive">
                {{
                  t(
                    'admin.subscriptions.deleted_tab.grace_badge',
                  )
                }}
              </Badge>
              <span class="text-xs text-subtle">
                {{
                  t(
                    'admin.subscriptions.deleted_tab.days_remaining',
                    { days: row.daysRemaining },
                  )
                }}
              </span>
            </div>
          </div>
        </template>

        <template #cell-deletedAt="{ row }">
          {{ row.deletedAt }}
        </template>

        <template #actions="{ row }">
          <Button
            v-if="row.canRestore"
            variant="outline"
            size="sm"
            @click="confirmRestore(row)"
          >
            {{ t('admin.subscriptions.deleted_tab.restore') }}
          </Button>
        </template>
      </AdminTable>
    </section>

    <!-- Dialog de Confirmacao de Estorno -->
    <Dialog
      :open="refundTarget !== null"
      @update:open="(val) => !val && (refundTarget = null)"
    >
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{{ t('admin.payments.refund') }}</DialogTitle>
          <DialogDescription>
            {{ t('admin.payments.refund_confirm') }}
          </DialogDescription>
        </DialogHeader>
        <DialogFooter>
          <Button
            variant="ghost"
            size="sm"
            @click="refundTarget = null"
          >
            {{ t('admin.common.cancel') }}
          </Button>
          <Button
            variant="destructive"
            size="sm"
            @click="executeRefund"
          >
            {{ t('admin.payments.refund') }}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- Dialog de Confirmacao de Restauracao de Conta -->
    <Dialog
      :open="restoreTarget !== null"
      @update:open="(val) => !val && (restoreTarget = null)"
    >
      <DialogContent>
        <DialogHeader>
          <DialogTitle>
            {{
              t(
                'admin.subscriptions.deleted_tab.restore_confirm_title',
              )
            }}
          </DialogTitle>
          <DialogDescription>
            {{
              t(
                'admin.subscriptions.deleted_tab.restore_confirm_description',
              )
            }}
          </DialogDescription>
        </DialogHeader>
        <DialogFooter>
          <Button
            variant="ghost"
            size="sm"
            @click="restoreTarget = null"
          >
            {{ t('admin.subscriptions.deleted_tab.cancel') }}
          </Button>
          <Button
            variant="default"
            size="sm"
            @click="executeRestore"
          >
            {{
              t(
                'admin.subscriptions.deleted_tab.restore_confirm_button',
              )
            }}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <ConfirmIntentPaymentDialog
      :intent="confirmIntentTarget"
      @close="confirmIntentTarget = null"
    />
  </div>
</template>
