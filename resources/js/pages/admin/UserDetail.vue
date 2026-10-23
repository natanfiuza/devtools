<script setup lang="ts">
import type { FormDataConvertible } from '@inertiajs/core';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import InputError from '@/components/InputError.vue';
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
    AdminAccount,
    AdminAccountCan,
    AdminAccountPayment,
    AdminPlanOption,
    AdminQuota,
    AdminSession,
    AdminSocialAccount,
    AdminSubscription,
} from '@/types';

const props = defineProps<{
    account: AdminAccount;
    can: AdminAccountCan;
    subscription: AdminSubscription | null;
    quota: AdminQuota;
    socialAccounts: AdminSocialAccount[];
    sessions: AdminSession[];
    payments: AdminAccountPayment[];
    plans: AdminPlanOption[];
}>();

const { t } = useI18n();

const id = computed(() => props.account.id);

/**
 * As nove acoes (ADR-025). Todas passam por router.post na rota propria: o que
 * muda de uma para a outra e a autorizacao, que vive na policy.
 */
const actions = admin.users;

function post(
    url: string,
    data: Record<string, FormDataConvertible> = {},
): void {
    router.post(url, data, { preserveScroll: true });
}

/** Confirmacao simples das acoes que tiram acesso (DESIGN). */
function confirmRun(message: string, run: () => void): void {
    if (window.confirm(message)) {
        run();
    }
}

const planForm = useForm({ plan: '' });
const quotaForm = useForm({ amount: '' });
const deleteForm = useForm({ email: '' });

const deleteOpen = ref(false);

const quotaPercent = computed<number>(() => {
    if (props.quota.limit <= 0) {
        return 0;
    }

    return Math.min(
        100,
        Math.round((props.quota.used / props.quota.limit) * 100),
    );
});

const quotaUnit = computed<string>(() =>
    t(`admin.user.quota.${props.quota.unit}`),
);

function submitPlan(): void {
    planForm.post(actions.plan.url(id.value), { preserveScroll: true });
}

function submitQuota(): void {
    quotaForm.post(actions.quota.url(id.value), { preserveScroll: true });
}

function submitDelete(): void {
    deleteForm.delete(actions.destroy.url(id.value), {
        preserveScroll: true,
        onSuccess: () => {
            deleteOpen.value = false;
        },
    });
}
</script>

<template>
  <Head :title="`${t('admin.user.title')} - ${account.email}`" />

  <div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <Link
          :href="actions.index.url()"
          class="text-sm text-subtle hover:text-foreground"
        >
          {{ t('admin.user.back') }}
        </Link>
        <h2 class="mt-1 text-lg font-medium">
          {{ account.name }}
        </h2>
        <p class="text-sm text-subtle">
          {{ account.email }}
        </p>
      </div>

      <div class="flex flex-wrap gap-2">
        <Button
          v-if="!account.verified"
          variant="outline"
          size="sm"
          @click="post(actions.verifyEmail.url(id))"
        >
          {{ t('admin.user.actions.verify_email') }}
        </Button>
        <Button
          v-if="!account.verified"
          variant="outline"
          size="sm"
          @click="post(actions.resendVerification.url(id))"
        >
          {{ t('admin.user.actions.resend_verification') }}
        </Button>
        <Button
          variant="outline"
          size="sm"
          @click="post(actions.sendPasswordReset.url(id))"
        >
          {{ t('admin.user.actions.send_password_reset') }}
        </Button>

        <Button
          v-if="can.changeRole"
          variant="outline"
          size="sm"
          @click="
            confirmRun(t('admin.user.actions.confirm_role'), () =>
              post(actions.role.url(id), {
                role: account.isAdmin ? 'user' : 'admin',
              }),
            )
          "
        >
          {{
            account.isAdmin
              ? t('admin.user.actions.demote')
              : t('admin.user.actions.promote')
          }}
        </Button>

        <Button
          v-if="can.manage && !account.suspended"
          variant="outline"
          size="sm"
          @click="
            confirmRun(
              t('admin.user.actions.confirm_suspend'),
              () => post(actions.suspend.url(id)),
            )
          "
        >
          {{ t('admin.user.actions.suspend') }}
        </Button>
        <Button
          v-if="can.manage && account.suspended"
          variant="outline"
          size="sm"
          @click="post(actions.unsuspend.url(id))"
        >
          {{ t('admin.user.actions.unsuspend') }}
        </Button>

        <Button
          v-if="can.impersonate"
          variant="outline"
          size="sm"
          @click="
            confirmRun(
              t('admin.user.actions.impersonate_help'),
              () => post(actions.impersonate.url(id)),
            )
          "
        >
          {{ t('admin.user.actions.impersonate') }}
        </Button>

        <Dialog
          v-if="can.manage"
          v-model:open="deleteOpen"
        >
          <DialogTrigger as-child>
            <Button
              variant="destructive"
              size="sm"
            >
              {{ t('admin.user.actions.delete') }}
            </Button>
          </DialogTrigger>
          <DialogContent>
            <DialogHeader>
              <DialogTitle>
                {{ t('admin.user.delete_form.title') }}
              </DialogTitle>
              <DialogDescription>
                {{ t('admin.user.delete_form.description') }}
              </DialogDescription>
            </DialogHeader>

            <div class="grid gap-2">
              <Label for="confirm-email">
                {{ t('admin.user.delete_form.email') }}
              </Label>
              <Input
                id="confirm-email"
                v-model="deleteForm.email"
                type="email"
                autocomplete="off"
              />
              <InputError :message="deleteForm.errors.email" />
            </div>

            <DialogFooter class="gap-2">
              <DialogClose as-child>
                <Button variant="secondary">
                  {{ t('admin.user.delete_form.cancel') }}
                </Button>
              </DialogClose>
              <Button
                variant="destructive"
                :disabled="deleteForm.processing"
                @click="submitDelete"
              >
                {{ t('admin.user.delete_form.submit') }}
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      </div>
    </div>

    <p
      v-if="account.isAdmin && !can.manage"
      class="rounded-lg border border-border bg-muted px-4 py-3 text-sm text-subtle"
    >
      {{ t('admin.user.admin_notice') }}
    </p>

    <div class="grid gap-4 lg:grid-cols-2">
      <section class="rounded-lg border border-border p-4">
        <h3 class="text-sm font-medium">
          {{ t('admin.user.identity') }}
        </h3>
        <dl class="mt-3 space-y-2 text-sm">
          <div class="flex justify-between gap-4">
            <dt class="text-subtle">
              {{ t('admin.user.name') }}
            </dt>
            <dd>{{ account.name }}</dd>
          </div>
          <div class="flex justify-between gap-4">
            <dt class="text-subtle">
              {{ t('admin.user.email') }}
            </dt>
            <dd>{{ account.email }}</dd>
          </div>
          <div class="flex justify-between gap-4">
            <dt class="text-subtle">
              {{ t('admin.user.role') }}
            </dt>
            <dd>{{ t(`admin.users.role_${account.role}`) }}</dd>
          </div>
          <div class="flex justify-between gap-4">
            <dt class="text-subtle">
              {{ t('admin.user.locale') }}
            </dt>
            <dd>
              {{ account.locale ?? t('admin.user.no_value') }}
            </dd>
          </div>
          <div class="flex justify-between gap-4">
            <dt class="text-subtle">
              {{ t('admin.user.password') }}
            </dt>
            <dd>
              {{
                account.hasPassword
                  ? t('admin.user.password_set')
                  : t('admin.user.password_social')
              }}
            </dd>
          </div>
          <div class="flex justify-between gap-4">
            <dt class="text-subtle">
              {{ t('admin.user.created') }}
            </dt>
            <dd class="font-mono text-[13px]">
              {{ account.createdAt }}
            </dd>
          </div>
        </dl>
      </section>

      <section class="rounded-lg border border-border p-4">
        <h3 class="text-sm font-medium">
          {{ t('admin.user.status') }}
        </h3>
        <div class="mt-3 flex flex-wrap items-center gap-2">
          <Badge
            v-if="account.suspended"
            variant="destructive"
          >
            {{ t('admin.users.status_suspended') }}
          </Badge>
          <Badge
            v-else
            variant="secondary"
          >
            {{ t('admin.users.status_active') }}
          </Badge>
          <Badge
            :variant="account.verified ? 'secondary' : 'outline'"
          >
            {{
              account.verified
                ? t('admin.users.verified')
                : t('admin.users.unverified')
            }}
          </Badge>
        </div>
        <p
          v-if="account.suspendedAt !== null"
          class="mt-2 text-sm text-subtle"
        >
          {{ t('admin.user.since', { date: account.suspendedAt }) }}
        </p>
        <p
          v-if="account.verifiedAt !== null"
          class="mt-2 text-sm text-subtle"
        >
          {{
            t('admin.user.email_verified_at', {
              date: account.verifiedAt,
            })
          }}
        </p>
      </section>

      <section class="rounded-lg border border-border p-4">
        <h3 class="text-sm font-medium">
          {{ t('admin.user.subscription.title') }}
        </h3>

        <p
          v-if="subscription === null"
          class="mt-3 text-sm text-subtle"
        >
          {{ t('admin.user.subscription.none') }}
        </p>
        <dl
          v-else
          class="mt-3 space-y-2 text-sm"
        >
          <div class="flex justify-between gap-4">
            <dt class="text-subtle">
              {{ t('admin.user.subscription.plan') }}
            </dt>
            <dd>{{ subscription.plan }}</dd>
          </div>
          <div class="flex justify-between gap-4">
            <dt class="text-subtle">
              {{ t('admin.user.subscription.status') }}
            </dt>
            <dd>
              <Badge
                :variant="
                  subscription.current
                    ? 'secondary'
                    : 'outline'
                "
              >
                {{
                  subscription.current
                    ? t(
                      'admin.user.subscription.status_active',
                    )
                    : t('admin.user.subscription.expired')
                }}
              </Badge>
            </dd>
          </div>
          <div class="flex justify-between gap-4">
            <dt class="text-subtle">
              {{ t('admin.user.subscription.source') }}
            </dt>
            <dd>{{ subscription.gateway }}</dd>
          </div>
          <div class="flex justify-between gap-4">
            <dt class="text-subtle">
              {{ t('admin.user.subscription.period') }}
            </dt>
            <dd class="font-mono text-[13px]">
              {{
                subscription.end === null
                  ? t('admin.user.subscription.from', {
                    start: subscription.start,
                  })
                  : t('admin.user.subscription.from_to', {
                    start: subscription.start,
                    end: subscription.end,
                  })
              }}
            </dd>
          </div>
        </dl>

        <form
          class="mt-4 space-y-2"
          @submit.prevent="submitPlan"
        >
          <Label for="grant-plan">
            {{ t('admin.user.plan_form.title') }}
          </Label>
          <div class="flex gap-2">
            <Select v-model="planForm.plan">
              <SelectTrigger
                id="grant-plan"
                class="flex-1"
              >
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
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
              :disabled="planForm.processing"
            >
              {{ t('admin.user.plan_form.submit') }}
            </Button>
          </div>
          <p class="text-xs leading-relaxed text-subtle">
            {{ t('admin.user.plan_form.help') }}
          </p>
          <InputError :message="planForm.errors.plan" />
        </form>
      </section>

      <section class="rounded-lg border border-border p-4">
        <h3 class="text-sm font-medium">
          {{ t('admin.user.quota.title') }}
        </h3>

        <p class="mt-3 text-sm">
          <span class="font-mono">{{
            t('admin.user.quota.of', {
              used: quota.used,
              limit: quota.limit,
            })
          }}</span>
          <span class="ml-2 text-subtle">{{ quotaUnit }}</span>
        </p>

        <div
          class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-muted"
          role="presentation"
        >
          <div
            class="h-full bg-accent"
            :style="{ width: `${quotaPercent}%` }"
          />
        </div>

        <p class="mt-2 text-sm text-subtle">
          {{
            quota.windowEnd === null
              ? t('admin.user.quota.lifetime')
              : t('admin.user.quota.window', {
                start: quota.windowStart,
                end: quota.windowEnd,
              })
          }}
        </p>

        <form
          class="mt-4 space-y-2"
          @submit.prevent="submitQuota"
        >
          <Label for="grant-quota">
            {{ t('admin.user.quota_form.title') }}
          </Label>
          <div class="flex gap-2">
            <Input
              id="grant-quota"
              v-model="quotaForm.amount"
              type="number"
              min="1"
              class="flex-1"
            />
            <Button
              type="submit"
              variant="outline"
              size="sm"
              :disabled="quotaForm.processing"
            >
              {{ t('admin.user.quota_form.submit') }}
            </Button>
          </div>
          <p class="text-xs leading-relaxed text-subtle">
            {{
              t('admin.user.quota_form.help', { unit: quotaUnit })
            }}
          </p>
          <InputError :message="quotaForm.errors.amount" />
        </form>
      </section>

      <section class="rounded-lg border border-border p-4 lg:col-span-2">
        <h3 class="text-sm font-medium">
          {{ t('admin.user.payments.title') }}
        </h3>

        <p
          v-if="payments.length === 0"
          class="mt-3 text-sm text-subtle"
        >
          {{ t('admin.user.payments.empty') }}
        </p>
        <table
          v-else
          class="mt-3 w-full border-collapse text-sm"
        >
          <thead>
            <tr class="border-b border-border">
              <th
                class="py-2 text-left text-[12px] tracking-wide text-subtle uppercase"
              >
                {{ t('admin.user.payments.paid_at') }}
              </th>
              <th
                class="py-2 text-left text-[12px] tracking-wide text-subtle uppercase"
              >
                {{ t('admin.user.payments.method') }}
              </th>
              <th
                class="py-2 text-right text-[12px] tracking-wide text-subtle uppercase"
              >
                {{ t('admin.user.payments.amount') }}
              </th>
              <th
                class="py-2 text-right text-[12px] tracking-wide text-subtle uppercase"
              >
                {{ t('admin.user.payments.status') }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="payment in payments"
              :key="payment.id"
              class="border-b border-border last:border-b-0"
            >
              <td class="py-2 font-mono text-[13px]">
                {{ payment.paidAt }}
              </td>
              <td class="py-2">
                {{
                  t(
                    `admin.payments.methods.${payment.method}`,
                  )
                }}
              </td>
              <td class="py-2 text-right font-mono text-[13px]">
                {{ payment.amount }}
              </td>
              <td class="py-2 text-right">
                <Badge
                  :variant="
                    payment.status === 'paid'
                      ? 'secondary'
                      : 'outline'
                  "
                >
                  {{
                    t(
                      `admin.payments.statuses.${payment.status}`,
                    )
                  }}
                </Badge>
              </td>
            </tr>
          </tbody>
        </table>
      </section>

      <section class="rounded-lg border border-border p-4">
        <h3 class="text-sm font-medium">
          {{ t('admin.user.social.title') }}
        </h3>

        <p
          v-if="socialAccounts.length === 0"
          class="mt-3 text-sm text-subtle"
        >
          {{ t('admin.user.social.empty') }}
        </p>
        <ul
          v-else
          class="mt-3 space-y-2 text-sm"
        >
          <li
            v-for="social in socialAccounts"
            :key="social.provider"
            class="flex justify-between gap-4"
          >
            <span>{{ social.provider }}</span>
            <span class="font-mono text-[13px] text-subtle">
              {{ social.createdAt }}
            </span>
          </li>
        </ul>
      </section>

      <section class="rounded-lg border border-border p-4">
        <h3 class="text-sm font-medium">
          {{ t('admin.user.sessions.title') }}
        </h3>

        <p
          v-if="sessions.length === 0"
          class="mt-3 text-sm text-subtle"
        >
          {{ t('admin.user.sessions.empty') }}
        </p>
        <ul
          v-else
          class="mt-3 space-y-2 text-sm"
        >
          <li
            v-for="session in sessions"
            :key="`${session.ip}-${session.lastActivity}`"
            class="flex justify-between gap-4"
          >
            <span class="truncate">
              {{ session.agent ?? t('admin.user.no_value') }}
            </span>
            <span
              class="shrink-0 font-mono text-[13px] text-subtle"
            >
              {{ session.ip }} · {{ session.lastActivity }}
            </span>
          </li>
        </ul>
      </section>
    </div>
  </div>
</template>
