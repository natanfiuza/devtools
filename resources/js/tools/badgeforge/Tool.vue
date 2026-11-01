<script setup lang="ts">
import { Copy, Eraser, Sparkles } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
    defaultForm,
    locksColors,
    NO_LOGO,
    normalizeBadge,
    sampleForm,
    type BadgeFormState,
} from './builder';
import { badgeDimensions, renderBadge } from './lib/render';
import {
    htmlSnippet,
    markdownSnippet,
    rstSnippet,
    urlSnippet,
} from './lib/snippets';
import { buildBadgeUrl } from './lib/url';
import type { BadgeForgeToolProps } from './types';

/**
 * BadgeForge (ADR-072 a ADR-076).
 *
 * Formulario a esquerda, preview e snippets a direita. A pagina nao faz
 * requisicao nenhuma (ADR-002): o SVG e montado no navegador pelo espelho
 * TypeScript do renderer PHP e vira data URI no `<img>`. O endpoint publico
 * so e usado por quem consome o snippet copiado.
 */

defineOptions({ inheritAttrs: false });

const props = defineProps<BadgeForgeToolProps>();

const { t } = useI18n();

/** Paleta fixa de atalhos; o campo livre aceita qualquer cor valida. */
const SWATCHES = [
    '#44cc11',
    '#97ca00',
    '#a4a61d',
    '#dfb317',
    '#fe7d37',
    '#e05d44',
    '#007ec6',
    '#9f9f9f',
    '#555555',
    '#000000',
];

const form = ref<BadgeFormState>(defaultForm(props));
const darkBackground = ref(false);
const snippetTab = ref<'url' | 'markdown' | 'html' | 'rst'>('markdown');

const { message: copyMessage, copy: copySnippet } = useCopyFeedback();

const spec = computed(() => normalizeBadge(form.value, props));
const svg = computed(() => renderBadge(spec.value));
const dimensions = computed(() => badgeDimensions(spec.value));
const dataUri = computed(
    () => `data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg.value)}`,
);
const altText = computed(() => `${spec.value.label}: ${spec.value.message}`);

const hasContent = computed(
    () => form.value.label.trim() !== '' && form.value.message.trim() !== '',
);

const forcesColors = computed(() => locksColors(spec.value.style));

const origin = typeof window === 'undefined' ? '' : window.location.origin;

const url = computed(() =>
    buildBadgeUrl(origin, props.endpoint, spec.value, props),
);

const snippets = computed<Record<'url' | 'markdown' | 'html' | 'rst', string>>(
    () => ({
        url: urlSnippet(url.value),
        markdown: markdownSnippet(url.value, altText.value),
        html: htmlSnippet(
            url.value,
            altText.value,
            dimensions.value.width,
            dimensions.value.height,
        ),
        rst: rstSnippet(url.value, altText.value),
    }),
);

function loadSample(): void {
    form.value = sampleForm(props);
}

function reset(): void {
    form.value = defaultForm(props);
}
</script>

<template>
  <div class="mx-auto max-w-6xl space-y-6 py-4">
    <div class="grid gap-6 lg:grid-cols-2">
      <!-- Formulario -->
      <section class="space-y-6">
        <div class="flex items-center justify-between gap-2">
          <h2 class="text-sm font-semibold">
            {{ t('tools.badgeforge.sections.content') }}
          </h2>
          <div class="flex items-center gap-2">
            <Button
              variant="outline"
              size="sm"
              data-test="badge-sample"
              @click="loadSample"
            >
              <Sparkles class="size-4" />
              {{ t('tools.badgeforge.actions.sample') }}
            </Button>
            <Button
              variant="ghost"
              size="sm"
              data-test="badge-reset"
              @click="reset"
            >
              <Eraser class="size-4" />
              {{ t('tools.badgeforge.actions.reset') }}
            </Button>
          </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <div class="space-y-2">
            <Label for="badge-label">
              {{ t('tools.badgeforge.fields.label') }}
            </Label>
            <Input
              id="badge-label"
              v-model="form.label"
              :placeholder="
                t('tools.badgeforge.placeholders.label')
              "
              :maxlength="props.limits.maxTextLength"
              data-test="badge-label"
            />
          </div>
          <div class="space-y-2">
            <Label for="badge-message">
              {{ t('tools.badgeforge.fields.message') }}
            </Label>
            <Input
              id="badge-message"
              v-model="form.message"
              :placeholder="
                t('tools.badgeforge.placeholders.message')
              "
              :maxlength="props.limits.maxTextLength"
              data-test="badge-message"
            />
          </div>
        </div>

        <div class="space-y-2">
          <Label for="badge-style">
            {{ t('tools.badgeforge.fields.style') }}
          </Label>
          <Select v-model="form.style">
            <SelectTrigger
              id="badge-style"
              class="w-full"
              data-test="badge-style"
            >
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem
                v-for="style in props.styles"
                :key="style"
                :value="style"
              >
                {{ style }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>

        <!-- Cores -->
        <div class="space-y-3">
          <h2 class="text-sm font-semibold">
            {{ t('tools.badgeforge.sections.colors') }}
          </h2>

          <p
            v-if="forcesColors"
            class="text-xs text-muted-foreground"
            data-test="badge-social-note"
          >
            {{ t('tools.badgeforge.social_note') }}
          </p>

          <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
              <Label for="badge-label-color">
                {{ t('tools.badgeforge.fields.label_color') }}
              </Label>
              <Input
                id="badge-label-color"
                v-model="form.labelColor"
                :placeholder="
                  t(
                    'tools.badgeforge.placeholders.label_color',
                  )
                "
                :disabled="forcesColors"
                data-test="badge-label-color"
              />
            </div>
            <div class="space-y-2">
              <Label for="badge-color">
                {{ t('tools.badgeforge.fields.color') }}
              </Label>
              <Input
                id="badge-color"
                v-model="form.color"
                :placeholder="
                  t('tools.badgeforge.placeholders.color')
                "
                :disabled="forcesColors"
                data-test="badge-color"
              />
            </div>
          </div>

          <div class="flex flex-wrap items-center gap-1.5">
            <button
              v-for="swatch in SWATCHES"
              :key="`label-${swatch}`"
              type="button"
              class="size-5 rounded-sm border"
              :style="{ backgroundColor: swatch }"
              :title="swatch"
              :aria-label="swatch"
              :disabled="forcesColors"
              data-test="swatch-label"
              @click="form.labelColor = swatch"
            />
            <span class="mx-2 text-xs text-muted-foreground">·</span>
            <button
              v-for="swatch in SWATCHES"
              :key="`message-${swatch}`"
              type="button"
              class="size-5 rounded-sm border"
              :style="{ backgroundColor: swatch }"
              :title="swatch"
              :aria-label="swatch"
              :disabled="forcesColors"
              data-test="swatch-message"
              @click="form.color = swatch"
            />
          </div>
        </div>

        <!-- Logo -->
        <div class="space-y-3">
          <h2 class="text-sm font-semibold">
            {{ t('tools.badgeforge.sections.logo') }}
          </h2>

          <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
              <Label for="badge-logo">
                {{ t('tools.badgeforge.fields.logo') }}
              </Label>
              <Select v-model="form.logo">
                <SelectTrigger
                  id="badge-logo"
                  class="w-full"
                  data-test="badge-logo"
                >
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem :value="NO_LOGO">
                    {{ t('tools.badgeforge.logo_none') }}
                  </SelectItem>
                  <SelectItem
                    v-for="logo in props.logos"
                    :key="logo.slug"
                    :value="logo.slug"
                  >
                    {{ logo.name }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div class="space-y-2">
              <Label for="badge-logo-position">
                {{ t('tools.badgeforge.fields.logo_position') }}
              </Label>
              <Select v-model="form.logoPosition">
                <SelectTrigger
                  id="badge-logo-position"
                  class="w-full"
                  data-test="badge-logo-position"
                >
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem
                    v-for="position in [
                      'left',
                      'right',
                    ] as const"
                    :key="position"
                    :value="position"
                  >
                    {{
                      t(
                        `tools.badgeforge.positions.${position}`,
                      )
                    }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div class="space-y-2">
              <Label for="badge-logo-color">
                {{ t('tools.badgeforge.fields.logo_color') }}
              </Label>
              <Input
                id="badge-logo-color"
                v-model="form.logoColor"
                :placeholder="
                  t(
                    'tools.badgeforge.placeholders.logo_color',
                  )
                "
                data-test="badge-logo-color"
              />
            </div>

            <div class="space-y-2">
              <Label for="badge-logo-size">
                {{ t('tools.badgeforge.fields.logo_size') }}
              </Label>
              <Input
                id="badge-logo-size"
                v-model="form.logoSize"
                type="number"
                :min="props.limits.logoSize.min"
                :max="props.limits.logoSize.max"
                :placeholder="String(spec.style.logoSize)"
                data-test="badge-logo-size"
              />
            </div>
          </div>
        </div>

        <!-- Avancado -->
        <div class="space-y-3">
          <h2 class="text-sm font-semibold">
            {{ t('tools.badgeforge.sections.advanced') }}
          </h2>

          <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
              <Label for="badge-link">
                {{ t('tools.badgeforge.fields.link') }}
              </Label>
              <Input
                id="badge-link"
                v-model="form.link"
                type="url"
                :placeholder="
                  t('tools.badgeforge.placeholders.link')
                "
                data-test="badge-link"
              />
            </div>

            <div class="space-y-2">
              <Label for="badge-cache">
                {{ t('tools.badgeforge.fields.cache_seconds') }}
              </Label>
              <Input
                id="badge-cache"
                v-model="form.cacheSeconds"
                type="number"
                :min="props.limits.cacheSeconds.min"
                :max="props.limits.cacheSeconds.max"
                :placeholder="
                  String(props.limits.cacheSeconds.default)
                "
                data-test="badge-cache"
              />
            </div>
          </div>
        </div>
      </section>

      <!-- Preview e snippets -->
      <section class="space-y-6">
        <div class="flex items-center justify-between gap-2">
          <h2 class="text-sm font-semibold">
            {{ t('tools.badgeforge.preview.title') }}
          </h2>
          <Label
            for="badge-dark"
            class="flex items-center gap-2 text-xs text-muted-foreground"
          >
            <Checkbox
              id="badge-dark"
              v-model="darkBackground"
              data-test="badge-dark"
            />
            {{ t('tools.badgeforge.preview.dark') }}
          </Label>
        </div>

        <div
          class="flex min-h-[140px] items-center justify-center rounded-md border p-6"
          :class="
            darkBackground ? 'bg-neutral-900' : 'bg-neutral-100'
          "
          data-test="badge-stage"
        >
          <img
            v-if="hasContent"
            :src="dataUri"
            :alt="altText"
            :width="dimensions.width"
            :height="dimensions.height"
            data-test="badge-preview"
          >
          <p
            v-else
            class="text-sm text-muted-foreground"
            data-test="badge-empty"
          >
            {{ t('tools.badgeforge.preview.empty') }}
          </p>
        </div>

        <p
          v-if="hasContent"
          class="text-xs text-muted-foreground"
          data-test="badge-dimensions"
        >
          {{
            t('tools.badgeforge.preview.dimensions', {
              width: dimensions.width,
              height: dimensions.height,
            })
          }}
        </p>

        <div class="space-y-2">
          <h2 class="text-sm font-semibold">
            {{ t('tools.badgeforge.snippets.title') }}
          </h2>

          <Tabs v-model="snippetTab">
            <TabsList class="grid w-full grid-cols-4">
              <TabsTrigger value="url">
                {{ t('tools.badgeforge.snippets.url') }}
              </TabsTrigger>
              <TabsTrigger value="markdown">
                {{ t('tools.badgeforge.snippets.markdown') }}
              </TabsTrigger>
              <TabsTrigger value="html">
                {{ t('tools.badgeforge.snippets.html') }}
              </TabsTrigger>
              <TabsTrigger value="rst">
                {{ t('tools.badgeforge.snippets.rst') }}
              </TabsTrigger>
            </TabsList>

            <TabsContent
              v-for="tab in [
                'url',
                'markdown',
                'html',
                'rst',
              ] as const"
              :key="tab"
              :value="tab"
              class="space-y-2"
            >
              <pre
                class="overflow-x-auto rounded-md bg-muted p-3 text-xs break-all whitespace-pre-wrap"
                data-test="badge-snippet"
              >{{ snippets[tab] }}</pre>
              <div class="flex items-center gap-2">
                <Button
                  variant="outline"
                  size="sm"
                  data-test="badge-copy"
                  @click="copySnippet(snippets[tab])"
                >
                  <Copy class="size-4" />
                  {{ t('tools.ui.actions.copy') }}
                </Button>
                <span
                  v-if="copyMessage"
                  class="text-xs text-muted-foreground"
                  data-test="badge-copy-feedback"
                >
                  {{ copyMessage }}
                </span>
              </div>
            </TabsContent>
          </Tabs>
        </div>
      </section>
    </div>
  </div>
</template>
