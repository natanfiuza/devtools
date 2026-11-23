<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import admin from '@/routes/admin';
import type { AdminPaymentRow } from '@/types';

const props = defineProps<{
    payment: AdminPaymentRow | null;
}>();

const emit = defineEmits<{
    close: [];
}>();

const { t } = useI18n();
const isRefunding = ref(false);

function setOpen(value: boolean): void {
    if (!value && !isRefunding.value) {
        emit('close');
    }
}

function executeRefund(): void {
    if (!props.payment || isRefunding.value) {
        return;
    }

    isRefunding.value = true;
    router.post(
        admin.payments.refund.url(props.payment.id),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                isRefunding.value = false;
                emit('close');
            },
        },
    );
}
</script>

<template>
  <Dialog
    :open="payment !== null"
    @update:open="setOpen"
  >
    <DialogContent class="sm:max-w-md">
      <DialogHeader>
        <DialogTitle>
          {{ t('admin.payments.refund') }}
        </DialogTitle>
        <DialogDescription>
          {{ t('admin.payments.refund_confirm') }}
        </DialogDescription>
      </DialogHeader>

      <dl
        v-if="payment"
        class="grid gap-2 rounded-md border border-border bg-muted/40 p-3 text-xs"
      >
        <div class="flex items-center justify-between gap-2">
          <dt class="text-subtle">
            {{ t('admin.payments.account') }}
          </dt>
          <dd class="font-medium">
            {{ payment.account }}
          </dd>
        </div>
        <div class="flex items-center justify-between gap-2">
          <dt class="text-subtle">
            {{ t('admin.payments.plan') }}
          </dt>
          <dd class="font-medium">
            {{ payment.plan ?? t('admin.payments.plan_none') }}
          </dd>
        </div>
        <div class="flex items-center justify-between gap-2">
          <dt class="text-subtle">
            {{ t('admin.payments.amount') }}
          </dt>
          <dd class="font-mono font-medium">
            {{ payment.amount }}
          </dd>
        </div>
        <div class="flex items-center justify-between gap-2">
          <dt class="text-subtle">
            {{ t('admin.payments.method') }}
          </dt>
          <dd class="font-medium">
            {{ t(`admin.payments.methods.${payment.method}`) }}
          </dd>
        </div>
      </dl>

      <DialogFooter class="gap-2">
        <Button
          type="button"
          variant="ghost"
          size="sm"
          :disabled="isRefunding"
          @click="emit('close')"
        >
          {{ t('admin.common.cancel') }}
        </Button>
        <Button
          type="button"
          variant="destructive"
          size="sm"
          data-test="confirm-refund-submit"
          :disabled="isRefunding"
          @click="executeRefund"
        >
          {{ t('admin.payments.refund') }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
