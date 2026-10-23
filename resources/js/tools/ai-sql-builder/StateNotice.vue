<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import { TriangleAlert } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import type { Quota } from './types';

/**
 * O recado de um estado que impede o formulario: visitante, e-mail nao
 * verificado, conta suspensa, provider desligado, cota esgotada - e as duas
 * falhas do pedido que a pessoa pode tentar de novo (erro e timeout).
 *
 * Um componente so porque o formato e o mesmo e a diferenca e o texto e o
 * caminho de saida. O CTA de reenviar a confirmacao usa o `Form` do Inertia: a
 * rota e a mesma da tela de verificacao, com a sessao e o CSRF do site.
 */

type NoticeKind =
    | 'guest'
    | 'unverified'
    | 'suspended'
    | 'unavailable'
    | 'quota'
    | 'error'
    | 'timeout';

const props = defineProps<{
    kind: NoticeKind;
    quota?: Quota | null;
    /** Ja traduzido pelo dono do estado (a tela monta o texto do erro). */
    message?: string | null;
    loginHref?: string;
    pricingHref?: string;
    resendHref?: string;
}>();

const emit = defineEmits<{ retry: [] }>();

const { t } = useI18n();
const form = useForm({});
const sent = ref(false);
const resendFailed = ref(false);

const title = computed<string>(() => {
    switch (props.kind) {
        case 'quota':
            return t('ai.quota.title');
        case 'error':
            return t('ai.states.failed');
        case 'timeout':
            return t('ai.states.timeout');
        default:
            return t(`ai.${props.kind}.title`);
    }
});

const body = computed<string | null>(() => {
    switch (props.kind) {
        case 'quota':
            if (props.quota === null || props.quota === undefined) {
                return t('ai.errors.quota_exhausted');
            }

            return props.quota.unit === 'tokens'
                ? t('ai.quota.body_tokens', { limit: props.quota.limit })
                : t('ai.quota.body_uses', { limit: props.quota.limit });
        case 'error':
            return props.message ?? t('ai.errors.generic');
        case 'timeout':
            // O titulo ja diz o que aconteceu; repetir seria ruido.
            return null;
        default:
            return t(`ai.${props.kind}.body`);
    }
});

const isFailure = computed(
    () => props.kind === 'error' || props.kind === 'timeout',
);

const hasAction = computed(
    () =>
        (props.kind === 'guest' && props.loginHref !== undefined) ||
        (props.kind === 'unverified' && props.resendHref !== undefined) ||
        (props.kind === 'quota' && props.pricingHref !== undefined) ||
        props.kind === 'timeout',
);

function resend(): void {
    if (props.resendHref === undefined) {
        return;
    }

    resendFailed.value = false;

    form.post(props.resendHref, {
        preserveScroll: true,
        onSuccess: () => {
            sent.value = true;
        },
        onError: () => {
            resendFailed.value = true;
        },
    });
}
</script>

<template>
  <div
    class="rounded-lg border p-4"
    :class="
      isFailure ? 'border-destructive/40 bg-destructive/5' : 'bg-muted/40'
    "
    role="status"
    data-test="ai-state-notice"
    :data-kind="kind"
  >
    <div class="flex items-start gap-3">
      <TriangleAlert
        v-if="isFailure"
        class="mt-0.5 size-4 shrink-0 text-destructive"
        aria-hidden="true"
      />
      <div class="min-w-0">
        <h3 class="text-sm font-medium">
          {{ title }}
        </h3>
        <p
          v-if="body !== null"
          class="mt-1 text-sm text-muted-foreground"
        >
          {{ body }}
        </p>
        <p
          v-if="sent"
          class="mt-2 text-sm text-brand"
          role="status"
        >
          {{ t('ai.unverified.sent') }}
        </p>
        <p
          v-if="resendFailed"
          class="mt-2 text-sm text-destructive"
          role="status"
        >
          {{ t('ai.unverified.failed') }}
        </p>
      </div>
    </div>

    <div
      v-if="hasAction"
      class="mt-3 flex flex-wrap gap-2"
    >
      <Button
        v-if="kind === 'guest' && loginHref !== undefined"
        as-child
        size="sm"
      >
        <Link :href="loginHref">
          {{ t('ai.guest.cta') }}
        </Link>
      </Button>

      <Button
        v-else-if="kind === 'unverified' && resendHref !== undefined"
        type="button"
        size="sm"
        variant="secondary"
        :disabled="form.processing"
        data-test="ai-resend"
        @click="resend"
      >
        <Spinner v-if="form.processing" />
        {{ t('ai.unverified.cta') }}
      </Button>

      <Button
        v-else-if="kind === 'quota' && pricingHref !== undefined"
        as-child
        size="sm"
      >
        <Link :href="pricingHref">
          {{ t('ai.quota.cta') }}
        </Link>
      </Button>

      <Button
        v-else-if="kind === 'timeout'"
        type="button"
        size="sm"
        variant="secondary"
        data-test="ai-retry"
        @click="emit('retry')"
      >
        {{ t('ai.states.retry') }}
      </Button>
    </div>
  </div>
</template>
