<script setup lang="ts">
import { reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { ColorsApiError, colorsApi } from '@/lib/colors/api';

/**
 * Denuncia de um comentario (F7, ADR-141).
 *
 * O motivo e uma lista fechada, igual ao enum do servidor. A recusa volta com
 * a mensagem que o proprio servidor traduziu (duplicada, proprio comentario);
 * se nao houver mensagem, o texto generico do cliente cobre rede e 429.
 */
const props = defineProps<{ commentId: string }>();

const emit = defineEmits<{ close: [] }>();

const { t } = useI18n();

const reasons = ['spam', 'harassment', 'hate', 'sexual', 'other'] as const;

const form = reactive({ reason: reasons[0] as string });
const busy = ref(false);
const sent = ref(false);
const error = ref<string | null>(null);

function serverMessage(payload: unknown): string | null {
    const detail = (
        payload as {
            error?: { details?: { errors?: Record<string, unknown> } };
        }
    )?.error?.details?.errors?.reason;

    if (Array.isArray(detail) && typeof detail[0] === 'string') {
        return detail[0];
    }

    return null;
}

async function submit(): Promise<void> {
    if (busy.value) {
        return;
    }

    busy.value = true;
    error.value = null;

    try {
        await colorsApi(`/comments/${props.commentId}/report`, {
            method: 'POST',
            body: { reason: form.reason },
        });

        sent.value = true;
    } catch (cause) {
        error.value =
            cause instanceof ColorsApiError
                ? serverMessage(cause.payload)
                : null;
        error.value ??= t('colors.reports.errors.generic');
    } finally {
        busy.value = false;
    }
}
</script>

<template>
  <div
    class="flex flex-col gap-2 rounded-lg border border-border bg-muted/30 p-2"
    data-test="comment-report-dialog"
  >
    <template v-if="sent">
      <p
        class="text-xs"
        data-test="comment-report-sent"
      >
        {{ t('colors.reports.sent') }}
      </p>
      <div>
        <Button
          type="button"
          variant="ghost"
          size="sm"
          data-test="comment-report-close"
          @click="emit('close')"
        >
          {{ t('colors.reports.close') }}
        </Button>
      </div>
    </template>

    <template v-else>
      <p class="text-xs font-medium">
        {{ t('colors.reports.title') }}
      </p>

      <label class="flex flex-wrap items-center gap-2 text-xs">
        {{ t('colors.reports.reason_label') }}
        <select
          v-model="form.reason"
          class="rounded-md border border-border bg-background px-2 py-1 text-sm"
          data-test="comment-report-reason"
        >
          <option
            v-for="reason in reasons"
            :key="reason"
            :value="reason"
          >
            {{ t(`colors.reports.reasons.${reason}`) }}
          </option>
        </select>
      </label>

      <p
        v-if="error !== null"
        class="text-xs text-destructive"
        data-test="comment-report-error"
      >
        {{ error }}
      </p>

      <div class="flex flex-wrap items-center gap-2">
        <Button
          type="button"
          size="sm"
          :disabled="busy"
          data-test="comment-report-submit"
          @click="submit"
        >
          {{ t('colors.reports.submit') }}
        </Button>
        <Button
          type="button"
          variant="ghost"
          size="sm"
          data-test="comment-report-cancel"
          @click="emit('close')"
        >
          {{ t('colors.reports.cancel') }}
        </Button>
      </div>
    </template>
  </div>
</template>
