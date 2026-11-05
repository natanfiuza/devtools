<script setup lang="ts">
import { Copy, Download } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useCopyFeedback } from '@/composables/useCopyFeedback';
import {
    buildCustomBadgeUrl,
    findCustomDefinition,
    findCustomProvider,
    type CustomProviderFormState,
} from './lib/customProviderUrl';
import {
    imageSnippet,
    markdownSnippet,
    urlSnippet,
} from './lib/snippets';
import type { BadgeFormat } from './lib/url';
import type { BadgeForgeToolProps } from './types';

/**
 * Previa do modo provider customizado (F7, ADR-087).
 *
 * A imagem publica e o contrato, como no modo provider: a previa e a propria
 * `<img>` da URL opaca. A origem nao existe no cliente; um path ou provider
 * desabilitado deixa a previa vazia em vez de montar snippet.
 */

const DEBOUNCE_MS = 250;

const props = defineProps<{
    form: CustomProviderFormState;
    toolProps: BadgeForgeToolProps;
}>();

const { t } = useI18n();

const format = ref<BadgeFormat>('svg');
const snippetTab = ref<'url' | 'markdown' | 'html'>('markdown');

const { message: copyMessage, copy: copySnippet } = useCopyFeedback();

const origin = typeof window === 'undefined' ? '' : window.location.origin;

const provider = computed(() =>
    findCustomProvider(props.toolProps, props.form.provider),
);

const definition = computed(() =>
    findCustomDefinition(provider.value, props.form.definition),
);

const altText = computed(() => {
    if (provider.value === undefined || definition.value === undefined) {
        return t('tools.badgeforge.custom_providers.title');
    }

    return `${provider.value.name}: ${definition.value.label}`;
});

const url = computed(() => {
    const currentProvider = provider.value;
    const currentDefinition = definition.value;

    if (currentProvider === undefined || currentDefinition === undefined) {
        return '';
    }

    return buildCustomBadgeUrl(
        origin,
        props.toolProps.customEndpoint,
        currentProvider,
        currentDefinition,
        props.form.params,
        props.form,
        props.toolProps,
        { format: format.value },
    );
});

const downloadUrl = computed(() => {
    const currentProvider = provider.value;
    const currentDefinition = definition.value;

    if (currentProvider === undefined || currentDefinition === undefined) {
        return '';
    }

    return buildCustomBadgeUrl(
        origin,
        props.toolProps.customEndpoint,
        currentProvider,
        currentDefinition,
        props.form.params,
        props.form,
        props.toolProps,
        { format: format.value, download: true },
    );
});

const debouncedUrl = ref(url.value);

let timer: number | undefined;

watch(url, (value) => {
    if (typeof window === 'undefined') {
        debouncedUrl.value = value;

        return;
    }

    window.clearTimeout(timer);
    timer = window.setTimeout(() => {
        debouncedUrl.value = value;
    }, DEBOUNCE_MS);
});

onBeforeUnmount(() => {
    if (typeof window !== 'undefined') {
        window.clearTimeout(timer);
    }
});

const snippets = computed<Record<'url' | 'markdown' | 'html', string>>(() => ({
    url: urlSnippet(url.value),
    markdown: markdownSnippet(url.value, altText.value),
    html: imageSnippet(url.value, altText.value),
}));
</script>

<template>
  <section class="space-y-6">
    <div class="flex items-center justify-between gap-2">
      <h2 class="text-sm font-semibold">
        {{ t('tools.badgeforge.preview.title') }}
      </h2>

      <div class="w-24">
        <Select v-model="format">
          <SelectTrigger
            id="badge-custom-format"
            class="w-full"
            :aria-label="t('tools.badgeforge.fields.format')"
            data-test="badge-custom-format"
          >
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="value in ['svg', 'png'] as const"
              :key="value"
              :value="value"
            >
              {{ t(`tools.badgeforge.formats.${value}`) }}
            </SelectItem>
          </SelectContent>
        </Select>
      </div>
    </div>

    <div
      class="flex min-h-[140px] items-center justify-center rounded-md border bg-neutral-100 p-6"
      data-test="badge-custom-stage"
    >
      <img
        v-if="debouncedUrl !== ''"
        :src="debouncedUrl"
        :alt="altText"
        class="max-w-full"
        data-test="badge-custom-preview"
      >
      <p
        v-else
        class="text-sm text-muted-foreground"
        data-test="badge-custom-invalid-preview"
      >
        {{ t('tools.badgeforge.custom_providers.builder.preview_invalid') }}
      </p>
    </div>

    <p class="text-xs text-muted-foreground">
      {{ t('tools.badgeforge.custom_providers.builder.cache_note') }}
    </p>

    <div
      v-if="url !== ''"
      class="space-y-2"
    >
      <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 class="text-sm font-semibold">
          {{ t('tools.badgeforge.snippets.title') }}
        </h2>

        <Button
          as-child
          variant="outline"
          size="sm"
          data-test="badge-custom-download"
        >
          <a :href="downloadUrl">
            <Download class="size-4" />
            {{ t('tools.ui.actions.download') }}
          </a>
        </Button>
      </div>

      <Tabs v-model="snippetTab">
        <TabsList class="grid w-full grid-cols-3">
          <TabsTrigger value="url">
            {{ t('tools.badgeforge.snippets.url') }}
          </TabsTrigger>
          <TabsTrigger value="markdown">
            {{ t('tools.badgeforge.snippets.markdown') }}
          </TabsTrigger>
          <TabsTrigger value="html">
            {{ t('tools.badgeforge.snippets.html') }}
          </TabsTrigger>
        </TabsList>

        <TabsContent
          v-for="tab in ['url', 'markdown', 'html'] as const"
          :key="tab"
          :value="tab"
          class="space-y-2"
        >
          <pre
            class="overflow-x-auto rounded-md bg-muted p-3 text-xs break-all whitespace-pre-wrap"
            data-test="badge-custom-snippet"
          >{{ snippets[tab] }}</pre>
          <div class="flex items-center gap-2">
            <Button
              variant="outline"
              size="sm"
              data-test="badge-custom-copy"
              @click="copySnippet(snippets[tab])"
            >
              <Copy class="size-4" />
              {{ t('tools.ui.actions.copy') }}
            </Button>
            <span
              v-if="copyMessage"
              class="text-xs text-muted-foreground"
              data-test="badge-custom-copy-feedback"
            >
              {{ copyMessage }}
            </span>
          </div>
        </TabsContent>
      </Tabs>
    </div>
  </section>
</template>
