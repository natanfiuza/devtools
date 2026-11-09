<script setup lang="ts">
import { Star, StarOff } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { formatHex, type Rgba } from '@/lib/color';
import SwatchButton from '@/components/colors/SwatchButton.vue';

/**
 * Favoritos locais (ate 50) e o botao que alterna a cor atual.
 *
 * A estrela e o unico controle que escreve aqui, entao o painel nunca muda
 * sozinho enquanto o usuario navega pelas cores.
 */

const props = defineProps<{
    colors: Rgba[];
    isFavorite: boolean;
}>();

const emit = defineEmits<{
    apply: [color: Rgba];
    toggle: [];
    clear: [];
}>();

const { t } = useI18n();

const toggleLabel = computed(() =>
    props.isFavorite
        ? t('tools.html-color-picker.favorites.remove')
        : t('tools.html-color-picker.favorites.add'),
);
</script>

<template>
  <section class="rounded-xl border bg-card p-4">
    <div class="flex items-center justify-between gap-2">
      <h2 class="text-sm font-semibold">
        {{ t('tools.html-color-picker.favorites.title') }}
      </h2>
      <Button
        v-if="colors.length > 0"
        type="button"
        variant="ghost"
        size="sm"
        @click="emit('clear')"
      >
        {{ t('tools.html-color-picker.favorites.clear') }}
      </Button>
    </div>
    <p class="mt-1 text-xs text-muted-foreground">
      {{ t('tools.html-color-picker.favorites.description') }}
    </p>

    <Button
      type="button"
      :variant="isFavorite ? 'secondary' : 'outline'"
      size="sm"
      class="mt-3"
      :aria-pressed="isFavorite"
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
      {{ toggleLabel }}
    </Button>

    <p
      v-if="colors.length === 0"
      class="mt-3 text-sm text-muted-foreground"
    >
      {{ t('tools.html-color-picker.favorites.empty') }}
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
  </section>
</template>
