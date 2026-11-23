<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Label } from '@/components/ui/label';
import {
    isLayoutStrategy,
    LAYOUT_STRATEGIES,
    type LayoutStrategy,
} from '../engine';

/**
 * Escolha da estrategia de harmonia. O catalogo de 33 tokens nao muda entre
 * estrategias; o que muda e a derivacao da secundaria (e o Accent, sempre
 * +120, em todas).
 */

const model = defineModel<LayoutStrategy>({ required: true });

const { t } = useI18n();

const selected = computed<LayoutStrategy>({
    get: () => model.value,
    set: (value) => {
        if (isLayoutStrategy(value)) {
            model.value = value;
        }
    },
});

const description = computed(() =>
    t(`tools.layout-harmonizer.strategy.${model.value}.description`),
);
</script>

<template>
  <div class="space-y-2">
    <Label for="layout-harmonizer-strategy">
      {{ t('tools.layout-harmonizer.strategy.label') }}
    </Label>

    <Select v-model="selected">
      <SelectTrigger
        id="layout-harmonizer-strategy"
        class="w-full"
        data-test="strategy-select"
      >
        <SelectValue />
      </SelectTrigger>
      <SelectContent>
        <SelectItem
          v-for="strategy in LAYOUT_STRATEGIES"
          :key="strategy"
          :value="strategy"
        >
          {{ t(`tools.layout-harmonizer.strategy.${strategy}.name`) }}
        </SelectItem>
      </SelectContent>
    </Select>

    <p
      class="text-xs text-muted-foreground"
      data-test="strategy-description"
    >
      {{ description }}
    </p>
    <p class="text-xs text-muted-foreground">
      {{ t('tools.layout-harmonizer.strategy.hint') }}
    </p>
  </div>
</template>
