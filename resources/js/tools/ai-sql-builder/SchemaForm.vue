<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { json } from '@codemirror/lang-json';
import { sql } from '@codemirror/lang-sql';
import type { Extension } from '@codemirror/state';
import { useI18n } from 'vue-i18n';
import CodeEditor from '@/components/tool/CodeEditor.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { byteLength, formatBytes } from '@/lib/bytes';
import type {
    AiQueryInput,
    OutputTarget,
    Quota,
    SchemaSource,
    SqlDialect,
} from './types';

/**
 * O formulario do AI SQL Builder.
 *
 * As abas trocam a **fonte** do schema, e com ela a linguagem do editor (SQL,
 * JSON ou texto puro). O par fonte + dialeto + alvo e o que o servidor
 * canonicaliza; o cliente so impede o envio obviamente invalido - os limites
 * de verdade sao os do plano, e quem os cobra e a API.
 */

const props = defineProps<{
    sources: SchemaSource[];
    dialects: SqlDialect[];
    targets: OutputTarget[];
    limits: { schema: number; description: number };
    quota: Quota | null;
    busy: boolean;
}>();

const emit = defineEmits<{ submit: [input: AiQueryInput] }>();

const { t, locale } = useI18n();

const source = ref<SchemaSource>(props.sources[0] ?? 'ddl');
const schema = ref('');
const description = ref('');
const target = ref<string>(props.targets[0]?.slug ?? '');
const dialect = ref<SqlDialect>(props.dialects[0] ?? 'mysql');
const touched = ref(false);

const activeTarget = computed<OutputTarget | null>(
    () => props.targets.find((item) => item.slug === target.value) ?? null,
);

/** O alvo escolhido pode nao cobrir os quatro dialetos. */
const availableDialects = computed<SqlDialect[]>(() => {
    const supported = activeTarget.value?.dialects;

    return supported === undefined
        ? [...props.dialects]
        : props.dialects.filter((item) => supported.includes(item));
});

watch(
    availableDialects,
    (list) => {
        if (!list.includes(dialect.value)) {
            dialect.value = list[0] ?? props.dialects[0] ?? 'mysql';
        }
    },
    { immediate: true },
);

const extensions = computed<Extension[]>(() => {
    if (source.value === 'ddl') {
        return [sql()];
    }

    if (source.value === 'json') {
        return [json()];
    }

    // Migration Laravel e models SQLModel sao codigo de outra linguagem; o
    // editor fica em texto puro em vez de fingir um realce que nao existe.
    return [];
});

const schemaBytes = computed<number>(() => byteLength(schema.value));

// `[...]` conta code points, como o `mb_strlen` do servidor: um emoji ocupa
// uma posicao para o limite do plano, e nao duas como em `String.length`.
const descriptionLength = computed<number>(() => [...description.value].length);

const overSchema = computed<boolean>(
    () => schemaBytes.value > props.limits.schema,
);
const overDescription = computed<boolean>(
    () => descriptionLength.value > props.limits.description,
);

const canSubmit = computed<boolean>(
    () =>
        !props.busy &&
        target.value !== '' &&
        schema.value.trim() !== '' &&
        description.value.trim() !== '' &&
        !overSchema.value &&
        !overDescription.value,
);

const showRequired = computed<boolean>(
    () =>
        touched.value &&
        (schema.value.trim() === '' || description.value.trim() === ''),
);

function onSubmit(): void {
    touched.value = true;

    if (!canSubmit.value) {
        return;
    }

    emit('submit', {
        schema_source: source.value,
        dialect: dialect.value,
        output_target: target.value,
        schema: schema.value,
        description: description.value,
    });
}

function populate(input: {
    source: SchemaSource;
    dialect: SqlDialect;
    target: string;
    schema: string;
    description: string;
}): void {
    source.value = input.source;
    dialect.value = input.dialect;
    target.value = input.target;
    schema.value = input.schema;
    description.value = input.description;
    touched.value = false;
}

defineExpose({
    populate,
});
</script>

<template>
  <form
    class="flex flex-col gap-4"
    @submit.prevent="onSubmit"
  >
    <div class="grid gap-1.5">
      <Label>{{ t('ai.form.schema_source') }}</Label>
      <Tabs v-model="source">
        <TabsList class="w-full">
          <TabsTrigger
            v-for="item in sources"
            :key="item"
            :value="item"
            class="flex-1"
          >
            {{ t(`ai.sources.${item}`) }}
          </TabsTrigger>
        </TabsList>
      </Tabs>
    </div>

    <div class="grid gap-1.5">
      <Label>{{ t('ai.form.schema') }}</Label>
      <div class="h-56 overflow-hidden rounded-md border">
        <CodeEditor
          :key="source"
          v-model="schema"
          class="h-full"
          testid="ai-schema-editor"
          :placeholder="t('ai.form.schema_placeholder')"
          :extensions="extensions"
        />
      </div>
      <p
        class="text-xs"
        :class="overSchema ? 'text-destructive' : 'text-subtle'"
        data-test="ai-schema-counter"
      >
        {{
          t('ai.form.schema_limit', {
            used: formatBytes(schemaBytes, locale),
            limit: formatBytes(limits.schema, locale),
          })
        }}
      </p>
    </div>

    <div class="grid grid-cols-2 gap-3">
      <div class="grid gap-1.5">
        <Label>{{ t('ai.form.dialect') }}</Label>
        <Select v-model="dialect">
          <SelectTrigger data-test="ai-dialect">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="item in availableDialects"
              :key="item"
              :value="item"
            >
              {{ t(`ai.dialects.${item}`) }}
            </SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div class="grid gap-1.5">
        <Label>{{ t('ai.form.target') }}</Label>
        <Select v-model="target">
          <SelectTrigger data-test="ai-target">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="item in targets"
              :key="item.slug"
              :value="item.slug"
            >
              {{ t(`ai.targets.${item.slug}`) }}
            </SelectItem>
          </SelectContent>
        </Select>
      </div>
    </div>

    <div class="grid gap-1.5">
      <Label for="ai-description">{{ t('ai.form.description') }}</Label>
      <textarea
        id="ai-description"
        v-model="description"
        rows="3"
        :placeholder="t('ai.form.description_placeholder')"
        class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-colors placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
        data-test="ai-description"
      />
      <p
        class="text-xs"
        :class="overDescription ? 'text-destructive' : 'text-subtle'"
        data-test="ai-description-counter"
      >
        {{
          t('ai.form.description_limit', {
            used: descriptionLength,
            limit: limits.description,
          })
        }}
      </p>
    </div>

    <p
      v-if="showRequired"
      class="text-sm text-destructive"
      role="alert"
      data-test="ai-required"
    >
      {{ t('ai.form.required') }}
    </p>

    <p
      v-if="overSchema || overDescription"
      class="text-sm text-destructive"
      role="alert"
      data-test="ai-over-limit"
    >
      {{ t('ai.form.over_limit') }}
    </p>

    <div class="flex flex-wrap items-center justify-between gap-3">
      <div class="min-w-0">
        <p
          v-if="quota !== null"
          class="text-xs text-subtle"
        >
          {{
            quota.unit === 'tokens'
              ? t('ai.quota.remaining_tokens', {
                remaining: quota.remaining,
                limit: quota.limit,
              })
              : t('ai.quota.remaining_uses', {
                remaining: quota.remaining,
                limit: quota.limit,
              })
          }}
        </p>
        <p class="text-xs text-subtle">
          {{ t('ai.warnings.sensitive') }}
        </p>
      </div>

      <Button
        type="submit"
        :disabled="!canSubmit"
        data-test="ai-submit"
      >
        <Spinner v-if="busy" />
        {{ busy ? t('ai.form.submitting') : t('ai.form.submit') }}
      </Button>
    </div>
  </form>
</template>
