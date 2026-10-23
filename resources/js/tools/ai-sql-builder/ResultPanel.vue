<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Copy as CopyIcon, Download, Lightbulb } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useCopyFeedback } from '@/composables/useCopyFeedback';
import { downloadText } from '@/lib/download';
import { formatDownloadContent } from './formatDownload';
import StateNotice from './StateNotice.vue';
import type { AiQueryResult, QueryViewStatus, TokenUsage } from './types';

/**
 * O painel da resposta.
 *
 * O servidor devolve uma saida por alvo em `result.outputs` - normalmente so o
 * alvo pedido, mas o contrato permite mais de um. A aba mostra o que veio, sem
 * assumir que a chave e a do alvo escolhido. Copiar e baixar trabalham sobre a
 * saida visivel: a pessoa escolhe o que levar.
 *
 * Enquanto a fila anda, o painel explica a espera; no timeout, oferece tentar
 * de novo; numa falha terminal, mostra o texto do estado - o formulario continua
 * ao lado para uma nova descricao.
 */

const props = defineProps<{
    status: QueryViewStatus;
    result: AiQueryResult | null;
    tokens: TokenUsage;
    cacheHit: boolean;
    /** Ja traduzido pela tela a partir de `error_code`/envelope. */
    failureMessage: string | null;
}>();

const emit = defineEmits<{ retry: [] }>();

const { t } = useI18n();
const { message: copyMessage, copy } = useCopyFeedback();

const activeSlug = ref('');

const outputSlugs = computed<string[]>(() =>
    props.result === null ? [] : Object.keys(props.result.outputs),
);

watch(
    outputSlugs,
    (slugs) => {
        if (!slugs.includes(activeSlug.value)) {
            activeSlug.value = slugs[0] ?? '';
        }
    },
    { immediate: true },
);

const activeText = computed<string>(
    () => props.result?.outputs[activeSlug.value] ?? '',
);

const hasOutput = computed<boolean>(
    () => outputSlugs.value.length > 0 && activeText.value !== '',
);

const pending = computed<boolean>(
    () =>
        props.status === 'submitting' ||
        props.status === 'queued' ||
        props.status === 'processing',
);

const pendingLabel = computed<string>(() =>
    props.status === 'submitting'
        ? t('ai.form.submitting')
        : t(`ai.states.${props.status}`),
);

const hasMeta = computed<boolean>(() => {
    const result = props.result;

    return (
        result !== null &&
        (Boolean(result.explanation) ||
            Boolean(activeTip.value) ||
            result.assumptions.length > 0 ||
            result.warnings.length > 0 ||
            result.tables_used.length > 0)
    );
});

const activeTip = computed<string | null>(() => {
    if (!activeSlug.value) {
        return null;
    }

    const key = `ai.tips.${activeSlug.value}`;
    const message = t(key);

    return message !== key ? message : null;
});

/** Extensao do arquivo baixado, pelo alvo da aba. */
const EXTENSIONS: Record<string, string> = {
    sql: '.sql',
    laravel_query_builder: '.php',
    eloquent: '.php',
    sqlmodel: '.py',
    sqlalchemy: '.py',
    django_orm: '.py',
    prisma: '.prisma',
    drizzle: '.ts',
    typeorm: '.ts',
};

function copyOutput(): void {
    if (activeText.value === '') {
        return;
    }

    void copy(activeText.value, t('ai.result.copied'));
}

function downloadOutput(): void {
    if (activeText.value === '') {
        return;
    }

    const extension = EXTENSIONS[activeSlug.value] ?? '.txt';

    const content = formatDownloadContent({
        target: activeSlug.value,
        code: activeText.value,
        explanation: props.result?.explanation ?? null,
        tip: activeTip.value,
        assumptions: props.result?.assumptions ?? [],
        warnings: props.result?.warnings ?? [],
        tablesUsed: props.result?.tables_used ?? [],
        labels: {
            explanation: t('ai.result.explanation'),
            tip: t('ai.result.tip'),
            assumptions: t('ai.result.assumptions'),
            warnings: t('ai.result.warnings'),
            tables: t('ai.result.tables'),
        },
    });

    downloadText(
        `${t('ai.result.download_name')}${extension}`,
        content,
        'text/plain;charset=utf-8',
    );
}
</script>

<template>
  <section
    class="flex min-h-56 flex-col gap-4 rounded-lg border p-4"
    data-test="ai-result"
    :data-status="status"
  >
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <h2 class="text-sm font-medium">
          {{ t('ai.result.title') }}
        </h2>
        <p
          v-if="result?.title"
          class="mt-0.5 text-xs font-semibold text-brand"
          data-test="ai-result-title"
        >
          {{ result.title }}
        </p>
      </div>

      <div
        v-if="hasOutput"
        class="flex flex-wrap items-center gap-2"
      >
        <span
          v-if="copyMessage !== null"
          class="text-xs text-subtle"
          role="status"
          data-test="ai-copy-message"
        >
          {{ copyMessage }}
        </span>
        <Button
          type="button"
          size="sm"
          variant="outline"
          data-test="ai-copy"
          @click="copyOutput"
        >
          <CopyIcon aria-hidden="true" />
          {{ t('ai.result.copy') }}
        </Button>
        <Button
          type="button"
          size="sm"
          variant="outline"
          data-test="ai-download"
          @click="downloadOutput"
        >
          <Download aria-hidden="true" />
          {{ t('ai.result.download') }}
        </Button>
      </div>
    </div>

    <div
      v-if="pending"
      class="flex flex-1 flex-col items-center justify-center gap-2 py-10 text-center"
      role="status"
      data-test="ai-pending"
    >
      <Spinner class="size-5 text-brand" />
      <p class="text-sm font-medium">
        {{ pendingLabel }}
      </p>
      <p class="max-w-xs text-xs text-subtle">
        {{ t('ai.states.poll_hint') }}
      </p>
    </div>

    <StateNotice
      v-else-if="status === 'timeout'"
      kind="timeout"
      @retry="emit('retry')"
    />

    <StateNotice
      v-else-if="status === 'failed'"
      kind="error"
      :message="failureMessage"
    />

    <template v-else-if="status === 'done' && result !== null">
      <div
        v-if="result.needs_clarification"
        class="rounded-md border border-brand/40 bg-brand/5 p-3"
        data-test="ai-clarification"
      >
        <h3 class="text-sm font-medium">
          {{ t('ai.result.clarification') }}
        </h3>
        <p
          v-if="result.clarification !== null"
          class="mt-1 text-sm text-muted-foreground"
        >
          {{ result.clarification }}
        </p>
      </div>

      <template v-if="hasOutput">
        <Tabs
          v-if="outputSlugs.length > 1"
          v-model="activeSlug"
        >
          <TabsList class="w-full justify-start overflow-x-auto">
            <TabsTrigger
              v-for="slug in outputSlugs"
              :key="slug"
              :value="slug"
            >
              {{ t(`ai.targets.${slug}`) }}
            </TabsTrigger>
          </TabsList>
        </Tabs>
        <p
          v-else
          class="text-xs font-medium text-subtle"
        >
          {{ t(`ai.targets.${activeSlug}`) }}
        </p>

        <pre
          class="max-h-[32rem] overflow-auto rounded-md border bg-muted/40 p-3 font-mono text-sm whitespace-pre"
          data-test="ai-output"
        >{{ activeText }}</pre>
      </template>

      <p
        v-else-if="!result.needs_clarification"
        class="text-sm text-subtle"
      >
        {{ t('ai.result.empty') }}
      </p>

      <div
        v-if="hasMeta"
        class="flex max-h-72 flex-col gap-3.5 overflow-y-auto rounded-md border bg-muted/20 p-3.5 text-sm"
        data-test="ai-meta"
      >
        <div
          v-if="result.explanation"
          data-test="ai-explanation"
        >
          <h4 class="font-medium text-foreground">
            {{ t('ai.result.explanation') }}
          </h4>
          <p class="mt-1 text-xs leading-relaxed text-muted-foreground whitespace-pre-line">
            {{ result.explanation }}
          </p>
        </div>

        <div
          v-if="activeTip"
          class="flex items-start gap-2.5 rounded-md border border-brand/20 bg-brand/5 p-2.5 text-xs text-foreground"
          data-test="ai-target-tip"
        >
          <Lightbulb
            class="mt-0.5 size-4 shrink-0 text-brand"
            aria-hidden="true"
          />
          <div>
            <span class="font-semibold text-brand">
              {{ t('ai.result.tip') }}:
            </span>
            <span class="ml-1 text-muted-foreground">
              {{ activeTip }}
            </span>
          </div>
        </div>

        <div v-if="result.assumptions.length > 0">
          <h4 class="font-medium text-foreground">
            {{ t('ai.result.assumptions') }}
          </h4>
          <ul
            class="mt-1 list-disc space-y-1 pl-5 text-xs text-muted-foreground"
          >
            <li
              v-for="(item, index) in result.assumptions"
              :key="index"
            >
              {{ item }}
            </li>
          </ul>
        </div>

        <div v-if="result.warnings.length > 0">
          <h4 class="font-medium text-foreground">
            {{ t('ai.result.warnings') }}
          </h4>
          <ul
            class="mt-1 list-disc space-y-1 pl-5 text-xs text-muted-foreground"
          >
            <li
              v-for="(item, index) in result.warnings"
              :key="index"
            >
              {{ item }}
            </li>
          </ul>
        </div>

        <div v-if="result.tables_used.length > 0">
          <h4 class="font-medium text-foreground">
            {{ t('ai.result.tables') }}
          </h4>
          <p class="mt-1 font-mono text-xs text-muted-foreground">
            {{ result.tables_used.join(', ') }}
          </p>
        </div>
      </div>

      <div
        class="mt-auto flex flex-wrap items-center gap-3 border-t pt-3 text-xs text-subtle"
      >
        <Badge
          v-if="cacheHit"
          variant="secondary"
          data-test="ai-cache-hit"
        >
          {{ t('ai.result.cache_hit') }}
        </Badge>
        <span
          v-if="tokens.in !== null"
          data-test="ai-tokens-in"
        >
          {{ t('ai.result.tokens_in', { count: tokens.in }) }}
        </span>
        <span
          v-if="tokens.out !== null"
          data-test="ai-tokens-out"
        >
          {{ t('ai.result.tokens_out', { count: tokens.out }) }}
        </span>
        <span class="text-subtle">{{ t('ai.warnings.review') }}</span>
      </div>
    </template>

    <p
      v-else
      class="text-sm text-subtle"
    >
      {{ t('ai.result.empty') }}
    </p>
  </section>
</template>
