<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
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
import admin from '@/routes/admin';
import type { AdminIntentRow } from '@/types';

/**
 * Dialogo de confirmacao de pagamento de uma solicitacao de assinatura
 * (ADR-054).
 *
 * Pre-preenche o valor do plano pedido, mostra a forma escolhida pela pessoa e
 * envia uma unica requisicao que registra o pagamento, concede o plano
 * vinculado a ele e marca a solicitacao como atendida.
 */
const props = defineProps<{
    intent: AdminIntentRow | null;
}>();

const emit = defineEmits<{
    close: [];
}>();

const { t } = useI18n();

const form = useForm({
    amount: '',
    paid_at: '',
    note: '',
});

watch(
    () => props.intent,
    (intent) => {
        if (intent) {
            form.clearErrors();
            form.amount = intent.planAmount ?? '';
            form.paid_at = '';
            form.note = '';
        }
    },
    { immediate: true },
);

function setOpen(value: boolean): void {
    if (!value) {
        emit('close');
    }
}

function submit(): void {
    if (!props.intent) {
        return;
    }

    form.transform((data) => ({
        ...data,
        paid_at: data.paid_at === '' ? null : data.paid_at,
        note: data.note === '' ? null : data.note,
    })).post(admin.intents.confirmPayment.url(props.intent.id), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            emit('close');
        },
    });
}
</script>

<template>
  <Dialog
    :open="intent !== null"
    @update:open="setOpen"
  >
    <DialogContent class="sm:max-w-md">
      <DialogHeader>
        <DialogTitle>
          {{ t('admin.intents.confirm_dialog.title') }}
        </DialogTitle>
        <DialogDescription>
          {{ t('admin.intents.confirm_dialog.description') }}
        </DialogDescription>
      </DialogHeader>

      <form
        v-if="intent"
        class="grid gap-4"
        @submit.prevent="submit"
      >
        <dl class="grid gap-2 rounded-md border border-border bg-muted/40 p-3 text-xs">
          <div class="flex items-center justify-between gap-2">
            <dt class="text-subtle">
              {{ t('admin.intents.account') }}
            </dt>
            <dd class="font-medium">
              {{ intent.userName }} ({{ intent.userEmail }})
            </dd>
          </div>
          <div class="flex items-center justify-between gap-2">
            <dt class="text-subtle">
              {{ t('admin.intents.plan') }}
            </dt>
            <dd class="font-medium">
              {{ intent.planName }}
              <span
                v-if="intent.planPrice"
                class="font-mono text-subtle"
              >
                &middot; {{ intent.planPrice }}
              </span>
            </dd>
          </div>
          <div class="flex items-center justify-between gap-2">
            <dt class="text-subtle">
              {{ t('admin.intents.payment_method') }}
            </dt>
            <dd class="font-medium">
              {{
                t(
                  `home.subscribe.payment_methods.${intent.paymentMethod}`,
                )
              }}
            </dd>
          </div>
        </dl>

        <div class="grid gap-2">
          <Label for="confirm-intent-amount">
            {{ t('admin.payments.amount') }}
          </Label>
          <Input
            id="confirm-intent-amount"
            v-model="form.amount"
            type="text"
            inputmode="decimal"
            data-test="confirm-intent-amount"
            required
          />
          <InputError :message="form.errors.amount" />
        </div>

        <div class="grid gap-2">
          <Label for="confirm-intent-paid-at">
            {{ t('admin.payments.paid_at') }}
          </Label>
          <Input
            id="confirm-intent-paid-at"
            v-model="form.paid_at"
            type="date"
            data-test="confirm-intent-paid-at"
          />
          <p class="text-xs text-subtle">
            {{ t('admin.payments.paid_at_help') }}
          </p>
          <InputError :message="form.errors.paid_at" />
        </div>

        <div class="grid gap-2">
          <Label for="confirm-intent-note">
            {{ t('admin.payments.note') }}
          </Label>
          <Input
            id="confirm-intent-note"
            v-model="form.note"
            type="text"
            data-test="confirm-intent-note"
          />
          <InputError :message="form.errors.note" />
        </div>

        <DialogFooter class="gap-2">
          <Button
            type="button"
            variant="ghost"
            size="sm"
            :disabled="form.processing"
            @click="emit('close')"
          >
            {{ t('admin.payments.cancel') }}
          </Button>
          <Button
            type="submit"
            size="sm"
            data-test="confirm-intent-submit"
            :disabled="form.processing"
          >
            {{ t('admin.intents.confirm_dialog.submit') }}
          </Button>
        </DialogFooter>
      </form>
    </DialogContent>
  </Dialog>
</template>
