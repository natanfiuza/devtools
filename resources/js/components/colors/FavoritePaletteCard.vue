<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { StarOff } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { colorsApi } from '@/lib/colors/api';
import colorsPalettes from '@/routes/colors/palettes';
import type { FavoritePaletteRow } from '@/types/colors';

/**
 * Cartao de uma paleta favoritada por outra conta (F5, ADR-128).
 *
 * O favorito e so o vinculo: o retrato exibido vem da origem publica, entao
 * uma paleta que voltou a ser publica aparece atualizada. Remover chama a API
 * JSON da biblioteca; a lista e recarregada pelo pai.
 */

const props = defineProps<{
    palette: FavoritePaletteRow;
}>();

const emit = defineEmits<{
    removed: [id: string];
}>();

const { t, locale } = useI18n();
const page = usePage();

const removing = ref(false);
const failed = ref(false);

const localeSlug = computed(() => page.props.localeSlug ?? 'en');

/** Sem slug a origem nao esta publica: o titulo vira texto simples. */
const publicUrl = computed<string | null>(() =>
    props.palette.slug === null
        ? null
        : colorsPalettes.show.url({
              locale: localeSlug.value,
              slug: props.palette.slug,
          }),
);

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

async function remove(): Promise<void> {
    if (removing.value) {
        return;
    }

    removing.value = true;
    failed.value = false;

    try {
        await colorsApi(`/palettes/${props.palette.id}/favorite`, {
            method: 'DELETE',
        });
        emit('removed', props.palette.id);
    } catch {
        failed.value = true;
    } finally {
        removing.value = false;
    }
}
</script>

<template>
  <article
    class="flex flex-col gap-3 rounded-xl border border-border bg-surface p-4"
    data-test="favorite-palette-card"
  >
    <div
      class="flex h-14 overflow-hidden rounded-lg border border-border"
      role="img"
      :aria-label="t('colors.list.preview_alt', { name: palette.name })"
      data-test="favorite-palette-preview"
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
            v-if="publicUrl !== null"
            :href="publicUrl"
            class="hover:underline"
            data-test="favorite-palette-name"
          >
            {{ palette.name }}
          </Link>
          <span
            v-else
            data-test="favorite-palette-name"
          >{{ palette.name }}</span>
        </h3>
        <p
          v-if="palette.description !== null"
          class="mt-1 line-clamp-2 text-sm text-muted-foreground"
          data-test="favorite-palette-description"
        >
          {{ palette.description }}
        </p>
      </div>

      <Badge variant="outline">
        {{ t(`colors.list.kind_${palette.kind}`) }}
      </Badge>
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
        <dd data-test="favorite-palette-usage">
          {{
            palette.duplications_count === 0
              ? t('colors.list.usage_zero')
              : t('colors.list.usage', { count: palette.duplications_count })
          }}
        </dd>
      </div>
      <div v-if="formatDate(palette.favorited_at) !== null">
        <dt class="sr-only">
          {{ t('colors.favorites.tab_favorites') }}
        </dt>
        <dd data-test="favorite-palette-saved-at">
          {{
            t('colors.favorites.favorited_at', {
              date: formatDate(palette.favorited_at),
            })
          }}
        </dd>
      </div>
    </dl>

    <ul
      v-if="palette.tags.length > 0"
      class="flex flex-wrap gap-1"
      data-test="favorite-palette-tags"
    >
      <li
        v-for="tag in palette.tags"
        :key="tag.id"
        class="rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground"
      >
        {{ tag.name }}
      </li>
    </ul>

    <div class="flex flex-wrap items-center gap-2">
      <Button
        type="button"
        variant="outline"
        size="sm"
        :disabled="removing"
        data-test="favorite-palette-remove"
        @click="remove"
      >
        <StarOff class="size-4" />
        {{
          removing
            ? t('colors.favorites.removing')
            : t('colors.favorites.remove')
        }}
      </Button>
      <p
        v-if="failed"
        class="text-xs text-destructive"
        role="alert"
        data-test="favorite-palette-error"
      >
        {{ t('colors.favorites.remove_error') }}
      </p>
    </div>
  </article>
</template>
