<script setup lang="ts">
import { Star, StarOff } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { formatHex, type Rgba } from '@/lib/color';
import SwatchButton from '@/components/colors/SwatchButton.vue';

/**
 * Favoritos do picker e do gerador (F5, ADR-127).
 *
 * A mesma lista de cores aparece nas duas tools; o anonimo guarda no
 * navegador e quem esta logado na conta. A estrela e o unico controle que
 * escreve aqui, entao o painel nunca muda sozinho enquanto o usuario navega.
 */

withDefaults(
    defineProps<{
        colors: Rgba[];
        isFavorite: boolean;
        authenticated: boolean;
        more?: boolean;
        loadingMore?: boolean;
    }>(),
    {
        more: false,
        loadingMore: false,
    },
);

const emit = defineEmits<{
    apply: [color: Rgba];
    toggle: [];
    clear: [];
    more: [];
}>();

const { t } = useI18n();
</script>

<template>
  <section class="rounded-xl border bg-card p-4">
    <div class="flex items-center justify-between gap-2">
      <h2 class="text-sm font-semibold">
        {{ t('tools.shared.library.favorites.title') }}
      </h2>
      <Button
        v-if="colors.length > 0"
        type="button"
        variant="ghost"
        size="sm"
        @click="emit('clear')"
      >
        {{ t('tools.shared.library.favorites.clear') }}
      </Button>
    </div>
    <p class="mt-1 text-xs text-muted-foreground">
      {{
        authenticated
          ? t('tools.shared.library.favorites.description.account')
          : t('tools.shared.library.favorites.description.local')
      }}
    </p>

    <Button
      type="button"
      :variant="isFavorite ? 'secondary' : 'outline'"
      size="sm"
      class="mt-3"
      :aria-pressed="isFavorite"
      data-test="favorites-toggle"
      @click="emit('toggle')"
    >
      <Star
        v-if="isFavorite"
        aria-hidden="true"
      />
      <StarOff
        v-else
        aria-hidden="true"
      />
      {{
        isFavorite
          ? t('tools.shared.library.favorites.remove')
          : t('tools.shared.library.favorites.add')
      }}
    </Button>

    <p
      v-if="colors.length === 0"
      class="mt-3 text-sm text-muted-foreground"
      data-test="favorites-empty"
    >
      {{ t('tools.shared.library.favorites.empty') }}
    </p>

    <div
      v-else
      class="mt-3 grid grid-cols-5 gap-1 sm:grid-cols-8"
    >
      <SwatchButton
        v-for="color in colors"
        :key="formatHex(color)"
        :color="color"
        :label="formatHex(color)"
        @select="emit('apply', color)"
      />
    </div>

    <Button
      v-if="more"
      type="button"
      variant="ghost"
      size="sm"
      class="mt-3"
      :disabled="loadingMore"
      data-test="favorites-more"
      @click="emit('more')"
    >
      {{ t('tools.shared.library.load_more') }}
    </Button>
  </section>
</template>
