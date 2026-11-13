<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Copy, Download, FlaskConical, RotateCcw, Star } from '@lucide/vue';
import { computed, nextTick, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import PageHead from '@/components/PageHead.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCopyFeedback } from '@/composables/useCopyFeedback';
import { colorsApi } from '@/lib/colors/api';
import {
    buildPaletteScale,
    formatHex,
    parseColor,
    type PaletteScale,
} from '@/lib/color';
import { downloadText } from '@/lib/download';
import { buildFrozenScale } from '@/lib/frozenScale';
import { payloadFromScale } from '@/lib/palettePayload';
import duplicatePalettes from '@/routes/colors/palettes/duplicate';
import ExportPanel from '@/tools/palette-generator/components/ExportPanel.vue';
import type {
    PaletteColorItem,
    PaletteColors,
    PaletteRoles,
    PublicPaletteProps,
} from '@/types/colors';

/**
 * Pagina publica de uma paleta (F4, ADR-124).
 *
 * Vitrine: nome, descricao, tags, uso, data e as cores persistidas, com copia e
 * download. Nao ha autor, curtidas nem comentarios. A escala reusa o painel de
 * exportacao do gerador, montada a partir dos HEX gravados (`buildFrozenScale`),
 * para o visitante ver exatamente o que o dono viu.
 *
 * "Testar nova base" recalcula uma escala **so na memoria**: a URL, o canonical
 * e a OG continuam os da paleta publicada. "Duplicar" exige conta; quem tem
 * conta envia o que esta na tela - a base experimentada vira uma paleta de
 * escala nova, e a origem nunca muda. Quem nao tem passa pela intencao, entra e
 * volta com `?duplicate=1`, que ja abre o experimento.
 */

const props = defineProps<PublicPaletteProps>();

const { t, locale } = useI18n();
const page = usePage();
const copyFeedback = useCopyFeedback();
const feedback = copyFeedback.message;

const authenticated = computed(() => page.props.auth?.user != null);
const localeSlug = computed(() => page.props.localeSlug ?? 'en');

const testing = ref(false);
const baseInput = ref(props.palette.colors.base);
const baseInputEl = ref<InstanceType<typeof Input> | null>(null);

/** Favorito da conta (F5, ADR-128): escrita otimista com volta na falha. */
const favorited = ref(props.favorited);
const favoriting = ref(false);
const favoriteFailed = ref(false);

async function toggleFavorite(): Promise<void> {
    if (favoriting.value) {
        return;
    }

    const next = !favorited.value;

    favorited.value = next;
    favoriting.value = true;
    favoriteFailed.value = false;

    try {
        await colorsApi(`/palettes/${props.palette.id}/favorite`, {
            method: next ? 'POST' : 'DELETE',
        });
    } catch {
        favorited.value = !next;
        favoriteFailed.value = true;
    } finally {
        favoriting.value = false;
    }
}

const items = computed<PaletteColorItem[]>(() => props.palette.colors.items);
const hexes = computed<string[]>(() => items.value.map((item) => item.hex));

/** Escala persistida, reconstruida sem recalcular; `null` se o dado falhar. */
const frozenScale = computed<PaletteScale | null>(() => {
    if (props.palette.kind !== 'scale') {
        return null;
    }

    try {
        return buildFrozenScale({
            base: props.palette.colors.base,
            items: items.value.filter(
                (item): item is { hex: string; tone: number } =>
                    'tone' in item,
            ),
            algorithmVersion: props.palette.algorithm_version,
        });
    } catch {
        // Escala corrompida: a lista de HEX abaixo continua valendo.
        return null;
    }
});

/** Escala experimental: existe so enquanto a cor digitada for valida. */
const experimentalScale = computed<PaletteScale | null>(() => {
    const parsed = parseColor(baseInput.value.trim());

    return parsed.ok ? buildPaletteScale(parsed.color.rgba) : null;
});

const experimentalHex = computed<string | null>(() =>
    experimentalScale.value === null
        ? null
        : formatHex(experimentalScale.value.base),
);

/** So duplica a versao experimentada quando a base realmente mudou. */
const experimenting = computed(
    () =>
        experimentalHex.value !== null &&
        experimentalHex.value.toLowerCase() !==
            props.palette.colors.base.toLowerCase(),
);

const updatedAt = computed<string | null>(() => {
    if (props.palette.updated_at === null) {
        return null;
    }

    const date = new Date(props.palette.updated_at);

    if (Number.isNaN(date.getTime())) {
        return null;
    }

    return new Intl.DateTimeFormat(locale.value.replace('_', '-'), {
        dateStyle: 'medium',
    }).format(date);
});

const extractedJson = computed(() =>
    `${JSON.stringify({
        name: props.palette.name,
        colors: hexes.value,
    }, null, 2)}\n`,
);

const duplicateUrl = computed(() =>
    duplicatePalettes.store.url({
        locale: localeSlug.value,
        slug: props.palette.slug ?? '',
    }),
);

const duplicateIntentUrl = computed(() =>
    duplicatePalettes.create.url({
        locale: localeSlug.value,
        slug: props.palette.slug ?? '',
    }),
);

/** Retrato enviado quando a base experimentada vira a paleta nova. */
type DuplicateOverride = {
    colors: PaletteColors;
    roles: PaletteRoles;
    tags: string[];
};

/** Corpo da duplicacao: com base experimentada, o retrato e o da tela. */
function duplicateBody(): DuplicateOverride | Record<string, never> {
    const scale = experimentalScale.value;

    if (!experimenting.value || scale === null) {
        return {} as Record<string, never>;
    }

    const payload = payloadFromScale(scale, {
        name: props.palette.name,
        description: props.palette.description,
        visibility: 'private',
        tags: props.palette.tags.map((tag) => tag.name),
    });

    return {
        colors: payload.colors,
        roles: payload.roles,
        tags: payload.tags,
    };
}

function duplicate(): void {
    if (props.palette.slug === null) {
        return;
    }

    router.post(duplicateUrl.value, duplicateBody(), {
        preserveScroll: true,
    });
}

function toggleExperiment(): void {
    testing.value = !testing.value;

    if (testing.value) {
        void nextTick(() => {
            baseInputEl.value?.$el?.focus?.();
        });
    }
}

function resetExperiment(): void {
    baseInput.value = props.palette.colors.base;
}

async function copyHex(hex: string): Promise<void> {
    await copyFeedback.copy(hex, t('colors.public.copied_hex', { hex }));
}

async function copyAll(): Promise<void> {
    await copyFeedback.copy(hexes.value.join('\n'), t('colors.public.copied'));
}

function downloadJson(): void {
    downloadText(`${props.palette.slug ?? 'palette'}.json`, extractedJson.value);
}

function downloadHex(): void {
    downloadText(
        `${props.palette.slug ?? 'palette'}.txt`,
        `${hexes.value.join('\n')}\n`,
        'text/plain;charset=utf-8',
    );
}

onMounted(() => {
    // Volta do login com a intencao de duplicar: abre o experimento pronto.
    const url = new URL(page.url, 'http://localhost');

    if (url.searchParams.get('duplicate') === '1') {
        testing.value = true;
    }
});
</script>

<template>
  <PageHead />

  <article
    class="mx-auto flex w-full max-w-[1120px] flex-col gap-8 px-4 py-10"
    data-test="public-palette"
  >
    <header class="flex flex-col gap-3">
      <h1
        class="text-3xl font-semibold tracking-tight"
        data-test="public-palette-name"
      >
        {{ palette.name }}
      </h1>

      <p
        v-if="palette.description !== null"
        class="max-w-2xl text-base text-muted-foreground"
        data-test="public-palette-description"
      >
        {{ palette.description }}
      </p>

      <div class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
        <Badge variant="outline">
          {{ t(`colors.list.kind_${palette.kind}`) }}
        </Badge>
        <span>
          {{ t('colors.list.colors_count', { count: palette.colors_count }) }}
        </span>
        <span data-test="public-palette-usage">
          {{
            palette.duplications_count === 0
              ? t('colors.list.usage_zero')
              : t('colors.list.usage', { count: palette.duplications_count })
          }}
        </span>
        <span
          v-if="updatedAt !== null"
          data-test="public-palette-updated"
        >
          {{ t('colors.list.updated_at', { date: updatedAt }) }}
        </span>
      </div>

      <ul
        v-if="palette.tags.length > 0"
        class="flex flex-wrap gap-1"
        data-test="public-palette-tags"
      >
        <li
          v-for="tag in palette.tags"
          :key="tag.id"
          class="rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground"
        >
          {{ tag.name }}
        </li>
      </ul>
    </header>

    <section
      class="flex flex-col gap-3"
      data-test="public-palette-colors"
    >
      <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
        <li
          v-for="(item, index) in items"
          :key="`${item.hex}-${index}`"
        >
          <button
            type="button"
            class="flex w-full flex-col overflow-hidden rounded-xl border border-border text-left transition hover:border-ring focus:outline-2 focus:outline-offset-2 focus:outline-ring"
            :title="t('colors.public.copy_hex')"
            data-test="public-swatch"
            @click="copyHex(item.hex)"
          >
            <span
              class="h-20 w-full"
              :style="{ backgroundColor: item.hex }"
            />
            <span class="flex items-center justify-between bg-surface px-2 py-1 text-xs">
              <span class="font-mono">{{ item.hex }}</span>
              <span
                v-if="'tone' in item"
                class="text-muted-foreground"
              >
                {{ item.tone }}
              </span>
            </span>
          </button>
        </li>
      </ul>

      <p
        v-if="feedback !== null"
        class="text-xs text-muted-foreground"
        role="status"
        aria-live="polite"
        data-test="public-copy-feedback"
      >
        {{ feedback }}
      </p>
    </section>

    <div class="flex flex-wrap items-center gap-2">
      <Button
        v-if="canFavorite"
        type="button"
        :variant="favorited ? 'secondary' : 'outline'"
        :disabled="favoriting"
        :aria-pressed="favorited"
        data-test="public-favorite"
        @click="toggleFavorite"
      >
        <Star
          class="size-4"
          :class="favorited ? 'fill-current' : ''"
        />
        {{
          favorited
            ? t('colors.public.favorited')
            : t('colors.public.favorite')
        }}
      </Button>

      <Button
        v-if="authenticated"
        type="button"
        data-test="public-duplicate"
        @click="duplicate"
      >
        <Copy class="size-4" />
        {{ t('colors.public.duplicate') }}
      </Button>
      <Button
        v-else
        as-child
      >
        <Link
          :href="duplicateIntentUrl"
          data-test="public-duplicate-login"
        >
          <Copy class="size-4" />
          {{ t('colors.public.duplicate_login') }}
        </Link>
      </Button>

      <Button
        type="button"
        variant="outline"
        data-test="public-experiment-toggle"
        @click="toggleExperiment"
      >
        <FlaskConical class="size-4" />
        {{ t('colors.public.experiment_toggle') }}
      </Button>

      <p
        v-if="favoriteFailed"
        class="text-xs text-destructive"
        role="alert"
        data-test="public-favorite-error"
      >
        {{ t('colors.public.favorite_error') }}
      </p>
    </div>

    <section
      v-if="testing"
      class="flex flex-col gap-4 rounded-xl border border-border bg-surface p-4"
      data-test="public-experiment"
    >
      <div>
        <h2 class="text-sm font-semibold">
          {{ t('colors.public.experiment_title') }}
        </h2>
        <p class="mt-1 text-xs text-muted-foreground">
          {{ t('colors.public.experiment_description') }}
        </p>
      </div>

      <div class="flex flex-col gap-1 sm:max-w-xs">
        <Label for="public-experiment-base">
          {{ t('colors.public.experiment_label') }}
        </Label>
        <Input
          id="public-experiment-base"
          ref="baseInputEl"
          v-model="baseInput"
          autocomplete="off"
          spellcheck="false"
          data-test="public-experiment-input"
        />
        <p
          v-if="experimentalScale === null"
          class="text-xs text-destructive"
          data-test="public-experiment-invalid"
        >
          {{ t('colors.public.experiment_invalid') }}
        </p>
      </div>

      <div
        v-if="experimentalScale !== null"
        class="flex h-14 overflow-hidden rounded-lg border border-border"
        data-test="public-experiment-preview"
      >
        <span
          v-for="tone in experimentalScale.tones"
          :key="tone.tone"
          class="h-full flex-1"
          :style="{ backgroundColor: tone.hex }"
          :title="tone.hex"
        />
      </div>

      <div class="flex flex-wrap items-center gap-2">
        <Button
          type="button"
          variant="ghost"
          size="sm"
          data-test="public-experiment-reset"
          @click="resetExperiment"
        >
          <RotateCcw class="size-4" />
          {{ t('colors.public.experiment_reset') }}
        </Button>
        <p
          v-if="experimenting"
          class="text-xs text-muted-foreground"
          data-test="public-experiment-duplicate-hint"
        >
          {{ t('colors.public.experiment_duplicate_hint') }}
        </p>
      </div>
    </section>

    <ExportPanel
      v-if="frozenScale !== null"
      :scale="frozenScale"
      :copy="copyFeedback.copy"
      data-test="public-export"
    />

    <section
      v-else
      class="flex flex-col gap-3 rounded-xl border border-border bg-card p-4"
      data-test="public-extracted-export"
    >
      <div>
        <h2 class="text-sm font-semibold">
          {{ t('colors.public.extracted_title') }}
        </h2>
        <p class="mt-1 text-xs text-muted-foreground">
          {{ t('colors.public.extracted_description') }}
        </p>
      </div>

      <pre
        tabindex="0"
        class="max-h-80 overflow-auto rounded-lg bg-muted p-3 font-mono text-xs leading-relaxed"
        data-test="public-extracted-code"
      >{{ extractedJson }}</pre>

      <div class="flex flex-wrap gap-2">
        <Button
          type="button"
          data-test="public-extracted-copy"
          @click="copyAll"
        >
          <Copy class="size-4" />
          {{ t('colors.public.copy_all') }}
        </Button>
        <Button
          type="button"
          variant="outline"
          data-test="public-extracted-download-json"
          @click="downloadJson"
        >
          <Download class="size-4" />
          {{ t('colors.public.download_json') }}
        </Button>
        <Button
          type="button"
          variant="outline"
          data-test="public-extracted-download-hex"
          @click="downloadHex"
        >
          <Download class="size-4" />
          {{ t('colors.public.download_hex') }}
        </Button>
      </div>
    </section>
  </article>
</template>
