<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AdminTable from '@/components/admin/AdminTable.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
    AdminDonationRow,
    AdminPaymentOption,
    AdminPaymentRow,
    Paginator,
} from '@/types';

const props = defineProps<{
    tab: 'payments' | 'donations';
    payments: Paginator<AdminPaymentRow> | null;
    donations: Paginator<AdminDonationRow> | null;
    filters: { q: string; status: string };
    total: number;
    options: { plans: AdminPaymentOption[]; methods: string[] };
}>();

const { t } = useI18n();

function switchTab(newTab: 'payments' | 'donations'): void {
    router.get(
        admin.payments.index.url(),
        { tab: newTab },
        { preserveState: false, preserveScroll: true },
    );
}

const pageTitle = computed(() =>
    props.tab === 'payments'
        ? t('admin.payments.title')
        : t('admin.donations.title'),
);

const pageDescription = computed(() =>
    props.tab === 'payments'
        ? t('admin.payments.description')
        : t('admin.donations.description'),
);

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
        tab: props.tab,
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

/* -------------------------------------------------------------------------- */
/* Aba Doacoes: leitura, com o detalhe que a pagina publica nao mostra.       */
/* -------------------------------------------------------------------------- */
const donationColumns = computed<AdminColumn[]>(() => [
    { key: 'createdAt', label: t('admin.donations.created'), mono: true },
    { key: 'donor', label: t('admin.donations.donor') },
    {
        key: 'amount',
        label: t('admin.donations.amount'),
        align: 'right',
        mono: true,
    },
    { key: 'gateway', label: t('admin.donations.gateway') },
    { key: 'status', label: t('admin.donations.status') },
]);

const selectedDonation = ref<AdminDonationRow | null>(null);

function openDonation(row: AdminDonationRow): void {
    selectedDonation.value = row;
}

function setDonationOpen(value: boolean): void {
    if (!value) {
        selectedDonation.value = null;
    }
}

function donationBadgeVariant(
    status: AdminDonationRow['status'],
): 'secondary' | 'outline' | 'destructive' {
    if (status === 'paid') {
        return 'secondary';
    }

    return status === 'failed' ? 'destructive' : 'outline';
}
</script>

<template>
  <Head :title="pageTitle" />

  <div class="space-y-4">
    <header class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <h2 class="text-lg font-medium">
          {{ pageTitle }}
        </h2>
        <p class="mt-1 text-sm text-subtle">
          {{ pageDescription }}
        </p>
      </div>

      <Button
        v-if="tab === 'payments'"
        variant="outline"
        size="sm"
        @click="open = !open"
      >
        {{ t('admin.payments.register') }}
      </Button>
    </header>

    <div class="border-b border-border">
      <nav
        class="-mb-px flex space-x-6"
        aria-label="Tabs"
      >
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
          {{ t('admin.payments.tabs.payments') }}
        </button>
        <button
          type="button"
          class="border-b-2 py-3 text-sm font-medium transition-colors"
          :class="
            tab === 'donations'
              ? 'border-accent text-foreground'
              : 'border-transparent text-subtle hover:border-border hover:text-foreground'
          "
          @click="switchTab('donations')"
        >
          {{ t('admin.payments.tabs.donations') }}
        </button>
      </nav>
    </div>

    <section
      v-if="tab === 'payments' && payments"
      class="space-y-4"
    >
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
          <Label for="payment-plan">{{
            t('admin.payments.plan')
          }}</Label>
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
          <Label for="payment-note">{{
            t('admin.payments.note')
          }}</Label>
          <Input
            id="payment-note"
            v-model="form.note"
            type="text"
          />
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
          <Button
            type="submit"
            size="sm"
            :disabled="form.processing"
          >
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

      <form
        class="flex flex-wrap items-center gap-2"
        @submit.prevent="apply"
      >
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
            :variant="
              row.status === 'paid' ? 'secondary' : 'outline'
            "
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
    </section>

    <!-- ================================================================== -->
    <!-- ABA DOACOES: leitura so; o QR e o comprovante ficam no detalhe.    -->
    <!-- ================================================================== -->
    <section
      v-else-if="donations"
      class="space-y-4"
    >
      <form
        class="flex flex-wrap items-center gap-2"
        @submit.prevent="apply"
      >
        <Input
          v-model="filters.q"
          class="h-9 max-w-xs"
          type="search"
          :placeholder="t('admin.donations.search_placeholder')"
          :aria-label="t('admin.common.search')"
        />

        <Select v-model="filters.status">
          <SelectTrigger
            class="h-9 w-44"
            :aria-label="t('admin.donations.status')"
          >
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem :value="ALL">
              {{ t('admin.donations.status_all') }}
            </SelectItem>
            <SelectItem value="pending">
              {{ t('admin.donations.statuses.pending') }}
            </SelectItem>
            <SelectItem value="paid">
              {{ t('admin.donations.statuses.paid') }}
            </SelectItem>
            <SelectItem value="expired">
              {{ t('admin.donations.statuses.expired') }}
            </SelectItem>
            <SelectItem value="refunded">
              {{ t('admin.donations.statuses.refunded') }}
            </SelectItem>
            <SelectItem value="failed">
              {{ t('admin.donations.statuses.failed') }}
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
        :columns="donationColumns"
        :rows="donations"
        :empty="t('admin.donations.empty')"
        @page="goToPage"
      >
        <template #cell-createdAt="{ row }">
          {{ row.createdAt }}
        </template>

        <template #cell-donor="{ row }">
          <span v-if="row.donor ?? row.email">
            <span class="block">{{ row.donor ?? row.email }}</span>
            <span
              v-if="row.donor && row.email"
              class="block text-xs text-subtle"
            >
              {{ row.email }}
            </span>
          </span>
          <span
            v-else
            class="text-subtle"
          >
            {{ t('admin.donations.anonymous') }}
          </span>
        </template>

        <template #cell-amount="{ row }">
          {{ row.amount }}
        </template>

        <template #cell-gateway="{ row }">
          {{ t(`admin.donations.gateways.${row.gateway}`) }}
        </template>

        <template #cell-status="{ row }">
          <Badge :variant="donationBadgeVariant(row.status)">
            {{ t(`admin.donations.statuses.${row.status}`) }}
          </Badge>
        </template>

        <template #actions="{ row }">
          <Button
            variant="outline"
            size="sm"
            @click="openDonation(row)"
          >
            {{ t('admin.donations.detail') }}
          </Button>
        </template>
      </AdminTable>
    </section>

    <Dialog
      :open="selectedDonation !== null"
      @update:open="setDonationOpen"
    >
      <DialogContent class="sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle>
            {{
              t('admin.donations.detail_title', {
                ulid: selectedDonation?.ulid ?? '',
              })
            }}
          </DialogTitle>
          <DialogDescription>
            {{ t('admin.donations.detail_description') }}
          </DialogDescription>
        </DialogHeader>

        <dl
          v-if="selectedDonation"
          class="grid gap-3 text-sm sm:grid-cols-2"
        >
          <div>
            <dt class="text-xs tracking-wide text-subtle uppercase">
              {{ t('admin.donations.identifier') }}
            </dt>
            <dd class="font-mono text-[13px]">
              {{ selectedDonation.ulid }}
            </dd>
          </div>

          <div>
            <dt class="text-xs tracking-wide text-subtle uppercase">
              {{ t('admin.donations.donor') }}
            </dt>
            <dd>
              {{
                selectedDonation.donor ??
                  t('admin.donations.anonymous')
              }}
            </dd>
          </div>

          <div>
            <dt class="text-xs tracking-wide text-subtle uppercase">
              {{ t('admin.donations.email') }}
            </dt>
            <dd>
              {{ selectedDonation.email ?? '—' }}
            </dd>
          </div>

          <div>
            <dt class="text-xs tracking-wide text-subtle uppercase">
              {{ t('admin.donations.account') }}
            </dt>
            <dd>
              {{ selectedDonation.account ?? '—' }}
            </dd>
          </div>

          <div>
            <dt class="text-xs tracking-wide text-subtle uppercase">
              {{ t('admin.donations.amount') }}
            </dt>
            <dd class="font-mono text-[13px]">
              {{ selectedDonation.amount }}
            </dd>
          </div>

          <div>
            <dt class="text-xs tracking-wide text-subtle uppercase">
              {{ t('admin.donations.status') }}
            </dt>
            <dd>
              <Badge
                :variant="
                  donationBadgeVariant(
                    selectedDonation.status,
                  )
                "
              >
                {{
                  t(
                    `admin.donations.statuses.${selectedDonation.status}`,
                  )
                }}
              </Badge>
            </dd>
          </div>

          <div>
            <dt class="text-xs tracking-wide text-subtle uppercase">
              {{ t('admin.donations.gateway') }}
            </dt>
            <dd>
              {{
                t(
                  `admin.donations.gateways.${selectedDonation.gateway}`,
                )
              }}
            </dd>
          </div>

          <div>
            <dt class="text-xs tracking-wide text-subtle uppercase">
              {{ t('admin.donations.locale') }}
            </dt>
            <dd>{{ selectedDonation.locale }}</dd>
          </div>

          <div>
            <dt class="text-xs tracking-wide text-subtle uppercase">
              {{ t('admin.donations.created') }}
            </dt>
            <dd class="font-mono text-[13px]">
              {{ selectedDonation.createdAt ?? '—' }}
            </dd>
          </div>

          <div>
            <dt class="text-xs tracking-wide text-subtle uppercase">
              {{ t('admin.donations.paid_at') }}
            </dt>
            <dd class="font-mono text-[13px]">
              {{ selectedDonation.paidAt ?? '—' }}
            </dd>
          </div>

          <div>
            <dt class="text-xs tracking-wide text-subtle uppercase">
              {{ t('admin.donations.expires_at') }}
            </dt>
            <dd class="font-mono text-[13px]">
              {{ selectedDonation.expiresAt ?? '—' }}
            </dd>
          </div>

          <div v-if="selectedDonation.ticketUrl">
            <dt class="text-xs tracking-wide text-subtle uppercase">
              {{ t('admin.donations.ticket') }}
            </dt>
            <dd>
              <a
                :href="selectedDonation.ticketUrl"
                class="text-accent underline-offset-4 hover:underline"
                target="_blank"
                rel="noopener noreferrer"
              >
                {{ t('admin.donations.ticket') }}
              </a>
            </dd>
          </div>
        </dl>

        <div
          v-if="selectedDonation?.qrCode"
          class="space-y-1"
        >
          <p class="text-xs tracking-wide text-subtle uppercase">
            {{ t('admin.donations.pix_code') }}
          </p>
          <pre
            class="max-h-32 overflow-auto rounded-md border border-border bg-muted p-2 font-mono text-[12px] break-all whitespace-pre-wrap"
          >{{ selectedDonation.qrCode }}</pre>
        </div>

        <DialogFooter>
          <Button
            variant="ghost"
            size="sm"
            @click="selectedDonation = null"
          >
            {{ t('admin.donations.close') }}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </div>
</template>
