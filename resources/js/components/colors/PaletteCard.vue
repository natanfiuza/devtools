<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import palettes from '@/routes/dashboard/colors/palettes';
import type { PaletteRow } from '@/types/colors';
import PaletteActions from './PaletteActions.vue';

/**
 * Cartao de uma paleta em Minhas Paletas (F4).
 *
 * A previa sao as cores persistidas, na ordem gravada; os metadados vem do
 * presenter (contagem denormalizada, "uso" e tags). O cartao so apresenta: as
 * acoes ficam em `PaletteActions`.
 */

defineProps<{
    palette: PaletteRow;
}>();

const emit = defineEmits<{
    delete: [palette: PaletteRow];
}>();

const { t, locale } = useI18n();

function formatDate(value: string | null): string | null {
    if (value === null) {
        return null;
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return null;
    }

    return new Intl.DateTimeFormat(locale.value.replace('_', '-'), {
        dateStyle: 'medium',
    }).format(date);
}
</script>

<template>
  <article
    class="flex flex-col gap-3 rounded-xl border border-border bg-surface p-4"
    data-test="palette-card"
  >
    <div
      class="flex h-14 overflow-hidden rounded-lg border border-border"
      role="img"
      :aria-label="t('colors.list.preview_alt', { name: palette.name })"
      data-test="palette-preview"
    >
      <span
        v-for="(hex, index) in palette.preview"
        :key="`${hex}-${index}`"
        class="h-full flex-1"
        :style="{ backgroundColor: hex }"
        :title="hex"
      />
    </div>

    <div class="flex flex-wrap items-start justify-between gap-2">
      <div class="min-w-0">
        <h3 class="truncate text-base font-semibold">
          <Link
            :href="palettes.edit.url({ colorPalette: palette.id })"
            class="hover:underline"
            data-test="palette-card-name"
          >
            {{ palette.name }}
          </Link>
        </h3>
        <p
          v-if="palette.description !== null"
          class="mt-1 line-clamp-2 text-sm text-muted-foreground"
          data-test="palette-card-description"
        >
          {{ palette.description }}
        </p>
      </div>

      <div class="flex flex-wrap gap-1">
        <Badge variant="outline">
          {{ t(`colors.list.kind_${palette.kind}`) }}
        </Badge>
        <Badge variant="secondary">
          {{
            palette.visibility === 'public'
              ? t('colors.list.badge_public')
              : t('colors.list.badge_private')
          }}
        </Badge>
        <Badge
          v-if="palette.archived"
          variant="destructive"
          data-test="palette-card-archived"
        >
          {{ t('colors.list.badge_archived') }}
        </Badge>
      </div>
    </div>

    <dl class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
      <div>
        <dt class="sr-only">
          {{ t('colors.list.sort_colors_count') }}
        </dt>
        <dd>{{ t('colors.list.colors_count', { count: palette.colors_count }) }}</dd>
      </div>
      <div>
        <dt class="sr-only">
          {{ t('colors.list.sort_duplications_count') }}
        </dt>
        <dd data-test="palette-card-usage">
          {{
            palette.duplications_count === 0
              ? t('colors.list.usage_zero')
              : t('colors.list.usage', { count: palette.duplications_count })
          }}
        </dd>
      </div>
      <div v-if="formatDate(palette.updated_at) !== null">
        <dt class="sr-only">
          {{ t('colors.list.sort_updated_at') }}
        </dt>
        <dd>
          {{
            t('colors.list.updated_at', {
              date: formatDate(palette.updated_at),
            })
          }}
        </dd>
      </div>
    </dl>

    <ul
      v-if="palette.tags.length > 0"
      class="flex flex-wrap gap-1"
      data-test="palette-card-tags"
    >
      <li
        v-for="tag in palette.tags"
        :key="tag.id"
        class="rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground"
      >
        {{ tag.name }}
      </li>
    </ul>

    <PaletteActions
      :palette="palette"
      @delete="emit('delete', palette)"
    />
  </article>
</template>
