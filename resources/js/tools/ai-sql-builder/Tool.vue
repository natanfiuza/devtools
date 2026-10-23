<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoutes } from '@/composables/useRoutes';
import { formatBytes } from '@/lib/bytes';
import QueryHistory from './QueryHistory.vue';
import ResultPanel from './ResultPanel.vue';
import SchemaForm from './SchemaForm.vue';
import StateNotice from './StateNotice.vue';
import { useAiQuery } from './useAiQuery';
import type { AiApiError } from './api';
import type { AiHistoryItem, AiQueryInput, AiToolProps, BlockReason } from './types';

/**
 * O AI SQL Builder (docs/SPEC.md secao 6, ADR-039 a ADR-043).
 *
 * A pagina generica entrega o pacote `toolProps` e repassa os quatro limites
 * das tools de navegador. Aqui as props sao declaradas uma a uma, entao os
 * limites que nao dizem respeito a esta tool ficam nos atributos herdados; o
 * `inheritAttrs: false` impede que eles caiam no DOM.
 *
 * A tela nao decide autorizacao: ela desenha o estado que veio do servidor e,
 * quando ha um bloqueio, explica o motivo e o caminho de saida. Toda regra de
 * cota, verificacao e provider e conferida de novo no POST.
 */

defineOptions({ inheritAttrs: false });

const props = defineProps<AiToolProps>();

const { t, locale } = useI18n();
const routes = useRoutes();

const {
    status,
    result,
    tokens,
    cacheHit,
    quota: liveQuota,
    error,
    errorCode,
    busy,
    submit,
    retry,
    restore,
} = useAiQuery({
    endpoints: props.endpoints,
    poll: props.poll,
    initialQuota: props.quota,
});

const schemaFormRef = ref<InstanceType<typeof SchemaForm> | null>(null);
const queryHistoryRef = ref<InstanceType<typeof QueryHistory> | null>(null);

/** O motivo do servidor no vocabulario do `StateNotice`. */
const NOTICE_KINDS: Record<
    BlockReason,
    'guest' | 'unverified' | 'suspended' | 'unavailable' | 'quota'
> = {
    guest: 'guest',
    email_unverified: 'unverified',
    account_suspended: 'suspended',
    quota_exhausted: 'quota',
    unavailable: 'unavailable',
};

const blockedKind = computed<
    'guest' | 'unverified' | 'suspended' | 'unavailable' | 'quota' | null
>(() => {
    if (props.canUse.allowed) {
        return null;
    }

    return NOTICE_KINDS[props.canUse.reason ?? 'unavailable'];
});

/** Codigos que tem texto proprio em `ai.errors.*`. */
const TRANSLATED_ERRORS = new Set([
    'generic',
    'network',
    'validation',
    'unauthenticated',
    'forbidden',
    'email_unverified',
    'account_suspended',
    'not_found',
    'csrf',
    'rate_limited',
    'quota_exhausted',
    'unavailable',
    'timeout',
    'provider_unavailable',
    'invalid_response',
    'unsafe_sql',
    'invalid_contract',
    'internal',
    'schema_too_large',
    'description_too_long',
    'unsupported_dialect',
]);

function translateError(code: string, retryAfter: number | null): string {
    const known = TRANSLATED_ERRORS.has(code) ? code : 'generic';

    if (known === 'schema_too_large') {
        return t('ai.errors.schema_too_large', {
            limit: formatBytes(props.limits.schema, locale.value),
        });
    }

    if (known === 'description_too_long') {
        return t('ai.errors.description_too_long', {
            limit: props.limits.description,
        });
    }

    if (known === 'rate_limited') {
        return t('ai.errors.rate_limited', { seconds: retryAfter ?? 60 });
    }

    return t(`ai.errors.${known}`);
}

/**
 * A mensagem de uma falha terminal: primeiro o transporte/envelope, depois o
 * `error_code` que o job gravou. Sem nenhum dos dois, o `StateNotice` cai no
 * texto generico.
 */
const failureMessage = computed<string | null>(() => {
    const cause: AiApiError | null = error.value;

    if (cause !== null) {
        return translateError(cause.code, cause.retryAfterSeconds);
    }

    return errorCode.value === null
        ? null
        : translateError(errorCode.value, null);
});

function onSubmit(input: AiQueryInput): void {
    void submit(input).then(() => {
        queryHistoryRef.value?.reload();
    });
}

function onSelectHistory(item: AiHistoryItem): void {
    schemaFormRef.value?.populate({
        source: item.schema_source,
        dialect: item.dialect,
        target: item.output_target,
        schema: item.schema ?? '',
        description: item.description ?? '',
    });

    restore(item.result);
}
</script>

<template>
  <div class="space-y-6">
    <div
      :class="
        blockedKind === null
          ? 'grid gap-6 lg:grid-cols-2 lg:items-start'
          : 'max-w-2xl'
      "
    >
      <section class="rounded-lg border p-4 sm:p-5">
        <h2 class="text-base font-medium">
          {{ t('ai.form.title') }}
        </h2>

        <StateNotice
          v-if="blockedKind !== null"
          class="mt-4"
          :kind="blockedKind"
          :quota="liveQuota"
          :login-href="routes.login.value"
          :pricing-href="routes.pricing.value"
          :resend-href="routes.form.resendVerification.value.action"
        />

        <SchemaForm
          v-else
          ref="schemaFormRef"
          class="mt-4"
          :sources="sources"
          :dialects="dialects"
          :targets="targets"
          :limits="limits"
          :quota="liveQuota"
          :busy="busy"
          @submit="onSubmit"
        />
      </section>

      <ResultPanel
        v-if="blockedKind === null"
        :status="status"
        :result="result"
        :tokens="tokens"
        :cache-hit="cacheHit"
        :failure-message="failureMessage"
        @retry="retry"
      />
    </div>

    <QueryHistory
      v-if="props.endpoints.history"
      ref="queryHistoryRef"
      :history-endpoint="props.endpoints.history"
      @select="onSelectHistory"
    />
  </div>
</template>
