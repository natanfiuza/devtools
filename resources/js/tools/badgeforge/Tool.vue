<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import {
    Bookmark,
    Copy,
    Download,
    Eraser,
    Image,
    Sparkles,
    UserRound,
} from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';
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
import { login as loginRoute, pricing as pricingRoute } from '@/routes';
import { badgeforge as badgeforgeDashboard } from '@/routes/dashboard';
import CustomProviderForm from './CustomProviderForm.vue';
import CustomProviderPreview from './CustomProviderPreview.vue';
import LogoLibraryDialog from './LogoLibraryDialog.vue';
import ModeTabs from './ModeTabs.vue';
import PersonalBadgeDialog from './PersonalBadgeDialog.vue';
import PresetDialog from './PresetDialog.vue';
import ProviderForm from './ProviderForm.vue';
import ProviderPreview from './ProviderPreview.vue';
import {
    defaultForm,
    isCustomLogoSlug,
    locksColors,
    NO_LOGO,
    normalizeBadge,
    sampleForm,
    type BadgeFormState,
} from './builder';
import { customLogoAsset, loadCustomLogo } from './customLogos';
import { defaultCustomProviderForm } from './lib/customProviderUrl';
import { DEFAULT_FONT } from './lib/fonts';
import { defaultProviderForm } from './lib/providerUrl';
import { badgeDimensions, renderBadge } from './lib/render';
import {
    htmlSnippet,
    markdownSnippet,
    rstSnippet,
    urlSnippet,
} from './lib/snippets';
import { buildBadgeUrl, type BadgeFormat } from './lib/url';
import type {
    BadgeForgeCustomLogoSummary,
    BadgeForgePreset,
    BadgeForgeToolProps,
    BadgeMode,
} from './types';

/**
 * BadgeForge (ADR-072 a ADR-076).
 *
 * Formulario a esquerda, preview e snippets a direita. Geracao e previa sao
 * locais (ADR-002, com a emenda da ADR-079): o SVG e montado no navegador pelo
 * espelho TypeScript do renderer PHP e entra inline no DOM (ADR-077), para a
 * fonte web escolhida valer no preview. O endpoint publico so e usado por quem
 * consome o snippet copiado ou clica em Baixar.
 *
 * O servidor entra apenas nas acoes explicitas de conta (ADR-078, ADR-079):
 * buscar os paths de um logo personalizado escolhido, enviar/renomear/excluir
 * logo e salvar/aplicar preset. Digitar no formulario nunca faz requisicao.
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
const providerForm = ref(defaultProviderForm(props));
const customProviderForm = ref(defaultCustomProviderForm(props));
const mode = ref<BadgeMode>('static');
const darkBackground = ref(false);
const format = ref<BadgeFormat>('svg');
const snippetTab = ref<'url' | 'markdown' | 'html' | 'rst'>('markdown');

const { message: copyMessage, copy: copySnippet } = useCopyFeedback();

const spec = computed(() => normalizeBadge(form.value, props));
const svg = computed(() => renderBadge(spec.value));
const dimensions = computed(() => badgeDimensions(spec.value));
const altText = computed(() => `${spec.value.label}: ${spec.value.message}`);

const hasContent = computed(
    () => form.value.label.trim() !== '' && form.value.message.trim() !== '',
);

const forcesColors = computed(() => locksColors(spec.value.style));

/* ------------------------------------------------------------------ */
/* Conta, logos personalizados e presets (ADR-078, ADR-079)            */
/* ------------------------------------------------------------------ */

const page = usePage();

const logoDialogOpen = ref(false);
const presetDialogOpen = ref(false);
const personalDialogOpen = ref(false);

/** O idioma prefixa as rotas publicas; fora do Inertia cai no fallback. */
const localeSlug = computed<string>(() => {
    const shared = page.props as Record<string, unknown> | undefined;

    return typeof shared?.localeSlug === 'string' ? shared.localeSlug : 'en';
});

const isAuthenticated = computed<boolean>(() => {
    const shared = page.props as Record<string, unknown> | undefined;
    const auth = shared?.auth;

    return (
        typeof auth === 'object' &&
        auth !== null &&
        (auth as { user?: unknown }).user != null
    );
});

/** Sem Inertia (testes) o idioma fica no fallback e as rotas seguem validas. */
const loginUrl = computed<string>(
    () => loginRoute({ locale: localeSlug.value }).url,
);

const pricingUrl = computed<string>(
    () => pricingRoute({ locale: localeSlug.value }).url,
);

/** A lista e o cadastro de endpoints ficam no painel da conta (ADR-083). */
const endpointDashboardUrl = computed<string>(
    () => `${badgeforgeDashboard().url}#endpoint-badges`,
);

const customLogoMap = computed<Map<string, BadgeForgeCustomLogoSummary>>(() => {
    const map = new Map<string, BadgeForgeCustomLogoSummary>();

    for (const logo of props.customLogos) {
        map.set(logo.slug.toLowerCase().trim(), logo);
    }

    return map;
});

/** Busca os paths uma unica vez, na selecao explicita do logo. */
function ensureCustomLogo(slug: string): void {
    const summary = customLogoMap.value.get(slug.toLowerCase().trim());

    if (summary === undefined || customLogoAsset(summary.slug) !== null) {
        return;
    }

    void loadCustomLogo(summary);
}

watch(
    () => form.value.logo,
    (slug) => {
        if (isCustomLogoSlug(slug)) {
            ensureCustomLogo(slug);
        }
    },
);

const origin = typeof window === 'undefined' ? '' : window.location.origin;

/**
 * Slug custom mesmo antes de os paths chegarem: a URL e o snippet nunca
 * dependem do preview (ADR-078). Logo custom desconhecido (preset orfao) fica
 * de fora e cai para "sem logo".
 */
const customLogoSlug = computed<string | null>(() => {
    const slug = form.value.logo;

    return isCustomLogoSlug(slug) &&
        customLogoMap.value.has(slug.toLowerCase().trim())
        ? slug.toLowerCase().trim()
        : null;
});

const url = computed(() =>
    buildBadgeUrl(origin, props.endpoint, spec.value, props, {
        format: format.value,
        logoSlug: customLogoSlug.value ?? undefined,
    }),
);

const downloadUrl = computed(() =>
    buildBadgeUrl(origin, props.endpoint, spec.value, props, {
        format: format.value,
        download: true,
        logoSlug: customLogoSlug.value ?? undefined,
    }),
);

/**
 * PNG acima do cap do servidor e reduzido proporcionalmente: o snippet HTML
 * precisa anunciar a largura/altura que o arquivo tera de fato.
 */
const snippetDimensions = computed(() => {
    const { width, height } = dimensions.value;
    const maxWidth = props.limits.png.maxWidth;

    if (format.value !== 'png' || maxWidth <= 0 || width <= maxWidth) {
        return { width, height };
    }

    return {
        width: maxWidth,
        height: Math.max(1, Math.round((height * maxWidth) / width)),
    };
});

const snippets = computed<Record<'url' | 'markdown' | 'html' | 'rst', string>>(
    () => ({
        url: urlSnippet(url.value),
        markdown: markdownSnippet(url.value, altText.value),
        html: htmlSnippet(
            url.value,
            altText.value,
            snippetDimensions.value.width,
            snippetDimensions.value.height,
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

/** Texto do snapshot; qualquer valor fora do contrato cai no default. */
function presetText(value: unknown, fallback: string): string {
    return typeof value === 'string' ? value : fallback;
}

/**
 * Aplica um preset ao formulario. `logoOrphaned` ja cobre o custom excluido;
 * embutido removido da config tambem cai para "sem logo" (ADR-079).
 */
function applyPreset(preset: BadgeForgePreset): void {
    const source = preset.form;

    form.value = {
        label: presetText(source.label, ''),
        message: presetText(source.message, ''),
        style: presetText(source.style, props.defaults.style),
        font:
            typeof source.font === 'string' && source.font !== ''
                ? source.font
                : DEFAULT_FONT,
        color: presetText(source.color, props.defaults.color),
        labelColor: presetText(source.labelColor, props.defaults.labelColor),
        logo: preset.logoOrphaned ? NO_LOGO : presetText(source.logo, NO_LOGO),
        logoColor: presetText(source.logoColor, props.defaults.logoColor),
        logoSize: typeof source.logoSize === 'number' ? source.logoSize : '',
        logoPosition: source.logoPosition === 'right' ? 'right' : 'left',
        link: presetText(source.link, ''),
        cacheSeconds:
            typeof source.cacheSeconds === 'number' ? source.cacheSeconds : '',
    };
}

/**
 * `?preset=<ulid>` vem do painel. A URL e limpa antes de aplicar para um
 * recarregamento nao reaplicar o snapshot por cima do que foi editado.
 */
onMounted(() => {
    if (typeof window === 'undefined') {
        return;
    }

    const current = new URL(window.location.href);
    const presetId = current.searchParams.get('preset');

    if (presetId === null) {
        return;
    }

    const preset = props.presets.find((item) => item.id === presetId);

    current.searchParams.delete('preset');
    window.history.replaceState(
        window.history.state,
        '',
        current.toString(),
    );

    if (preset !== undefined) {
        applyPreset(preset);
    }
});
</script>

<template>
  <div class="mx-auto max-w-6xl space-y-6 py-4">
    <!-- Gestao da conta: os dialogos so tocam o servidor em acao explicita. -->
    <div
      v-if="props.management.canManage"
      class="flex flex-wrap items-center gap-2"
      data-test="badge-management"
    >
      <Button
        variant="outline"
        size="sm"
        data-test="badge-manage-logos"
        @click="logoDialogOpen = true"
      >
        <Image class="size-4" />
        {{ t('tools.badgeforge.management.logos_button') }}
      </Button>
      <Button
        variant="outline"
        size="sm"
        data-test="badge-manage-presets"
        @click="presetDialogOpen = true"
      >
        <Bookmark class="size-4" />
        {{ t('tools.badgeforge.management.presets_button') }}
      </Button>
      <Button
        variant="outline"
        size="sm"
        data-test="badge-personal-open"
        @click="personalDialogOpen = true"
      >
        <UserRound class="size-4" />
        {{ t('tools.badgeforge.personal.create_button') }}
      </Button>
    </div>
    <p
      v-else-if="!isAuthenticated"
      class="text-xs text-muted-foreground"
      data-test="badge-login-cta"
    >
      {{ t('tools.badgeforge.management.login_cta') }}
      <a
        :href="loginUrl"
        class="font-medium underline underline-offset-2"
      >
        {{ t('tools.badgeforge.management.login_action') }}
      </a>
    </p>

    <!-- Endpoint JSON: cadastro e lista vivem na dashboard (ADR-083). -->
    <div
      v-if="props.management.canManage"
      class="rounded-lg border border-border px-4 py-3"
      data-test="badge-endpoint-cta"
    >
      <p class="text-sm font-medium">
        {{ t('tools.badgeforge.endpoint.cta_title') }}
      </p>
      <p class="text-xs text-subtle">
        {{ t('tools.badgeforge.endpoint.cta_description') }}
      </p>
      <Button
        as-child
        variant="outline"
        size="sm"
        class="mt-2"
        data-test="badge-endpoint-cta-link"
      >
        <a :href="endpointDashboardUrl">
          {{ t('tools.badgeforge.endpoint.cta_action') }}
        </a>
      </Button>
    </div>

    <ModeTabs
      v-model="mode"
      :show-custom="props.customProviders.length > 0"
    />

    <div
      v-if="mode === 'static'"
      class="grid gap-6 lg:grid-cols-2"
    >
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
                  <SelectItem
                    v-for="logo in props.customLogos"
                    :key="logo.slug"
                    :value="logo.slug"
                    data-test="badge-logo-custom-option"
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

            <div class="space-y-2">
              <Label for="badge-font">
                {{ t('tools.badgeforge.fields.font') }}
              </Label>
              <Select v-model="form.font">
                <SelectTrigger
                  id="badge-font"
                  class="w-full"
                  data-test="badge-font"
                >
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem :value="DEFAULT_FONT">
                    {{ t('tools.badgeforge.fonts.default') }}
                  </SelectItem>
                  <SelectItem
                    v-for="font in props.fonts"
                    :key="font.slug"
                    :value="font.slug"
                  >
                    {{ font.name }}
                  </SelectItem>
                </SelectContent>
              </Select>
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
          <!--
            SVG inline (nao `<img>`): fonte carregada pela pagina nao vale
            dentro de uma imagem. O conteudo e gerado e escapado pelo proprio
            renderer, por isso o `v-html` e seguro (ADR-077).
          -->
          <!-- eslint-disable vue/no-v-html -- SVG gerado e escapado pelo renderer -->
          <div
            v-if="hasContent"
            :aria-label="altText"
            class="inline-flex max-w-full"
            data-test="badge-preview"
            v-html="svg"
          />
          <!-- eslint-enable vue/no-v-html -->
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
          <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-sm font-semibold">
              {{ t('tools.badgeforge.snippets.title') }}
            </h2>

            <div class="flex items-center gap-2">
              <div class="w-24">
                <Select v-model="format">
                  <SelectTrigger
                    id="badge-format"
                    class="w-full"
                    :aria-label="t('tools.badgeforge.fields.format')"
                    data-test="badge-format"
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

              <Button
                as-child
                variant="outline"
                size="sm"
                data-test="badge-download"
              >
                <a :href="downloadUrl">
                  <Download class="size-4" />
                  {{ t('tools.ui.actions.download') }}
                </a>
              </Button>
            </div>
          </div>

          <Tabs v-model="snippetTab" data-test="badge-snippet-tabs">
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

    <!-- Provider customizado do dono (F7, ADR-087): so com provider criado. -->
    <div
      v-else-if="mode === 'custom' && props.customProviders.length > 0"
      class="grid gap-6 lg:grid-cols-2"
      data-test="badge-custom-mode"
    >
      <CustomProviderForm
        v-model="customProviderForm"
        :tool-props="props"
      />
      <CustomProviderPreview
        :form="customProviderForm"
        :tool-props="props"
      />
    </div>

    <div
      v-else
      class="grid gap-6 lg:grid-cols-2"
      data-test="badge-provider-mode"
    >
      <ProviderForm
        v-model="providerForm"
        :tool-props="props"
      />
      <ProviderPreview
        :form="providerForm"
        :tool-props="props"
      />
    </div>

    <LogoLibraryDialog
      v-if="props.management.canManage"
      v-model:open="logoDialogOpen"
      :logos="props.customLogos"
      :limits="props.limits.customLogos"
      :upgrade-url="pricingUrl"
    />

    <PresetDialog
      v-if="props.management.canManage"
      v-model:open="presetDialogOpen"
      :presets="props.presets"
      :limits="props.limits.presets"
      :builder-form="form"
      @apply="applyPreset"
    />

    <PersonalBadgeDialog
      v-if="props.management.canManage"
      v-model:open="personalDialogOpen"
      :tool-props="props"
      :mode="mode"
      :builder-form="form"
      :provider-form="providerForm"
      :upgrade-url="pricingUrl"
    />
  </div>
</template>
