<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { NO_LOGO } from './builder';
import { logoAsset } from './logos';
import LogoPreview from './LogoPreview.vue';
import type { BadgeForgeCustomLogoSummary, BadgeForgeLogo } from './types';

/**
 * Seletor compartilhado de logo (embutido ou personalizado) com miniatura
 * vetorial inline para os formularios do BadgeForge.
 */
defineProps<{
    id: string;
    modelValue: string;
    logos: BadgeForgeLogo[];
    customLogos: BadgeForgeCustomLogoSummary[];
    dataTest?: string;
    customOptionDataTest?: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const { t } = useI18n();
</script>

<template>
  <Select
    :model-value="modelValue"
    @update:model-value="
      (value) => emit('update:modelValue', String(value))
    "
  >
    <SelectTrigger
      :id="id"
      class="w-full"
      :data-test="dataTest"
    >
      <SelectValue />
    </SelectTrigger>
    <SelectContent>
      <SelectItem :value="NO_LOGO">
        {{ t('tools.badgeforge.logo_none') }}
      </SelectItem>
      <SelectItem
        v-for="logo in logos"
        :key="logo.slug"
        :value="logo.slug"
      >
        <span class="inline-flex items-center gap-2">
          <LogoPreview
            v-if="logoAsset(logo.slug)"
            :view-box="logoAsset(logo.slug)!.viewBox"
            :paths="logoAsset(logo.slug)!.paths"
            color="currentColor"
            :size="16"
          />
          <span>{{ logo.name }}</span>
        </span>
      </SelectItem>
      <SelectItem
        v-for="logo in customLogos"
        :key="logo.slug"
        :value="logo.slug"
        :data-test="customOptionDataTest"
      >
        {{ logo.name }}
      </SelectItem>
    </SelectContent>
  </Select>
</template>
