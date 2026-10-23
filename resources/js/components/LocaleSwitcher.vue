<script setup lang="ts">
import { Languages } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useLocale } from '@/composables/useLocale';

const { t } = useI18n();
const { locales, current, switchTo } = useLocale();

function onChange(value: unknown): void {
    const next = locales.value.find((locale) => locale.code === value);

    if (next !== undefined) {
        switchTo(next);
    }
}
</script>

<template>
  <DropdownMenu>
    <DropdownMenuTrigger as-child>
      <Button
        variant="ghost"
        size="sm"
        class="gap-2"
        :aria-label="t('app.actions.change_language')"
      >
        <Languages
          class="size-4"
          aria-hidden="true"
        />
        <span class="hidden sm:inline">{{ current?.name ?? '' }}</span>
      </Button>
    </DropdownMenuTrigger>
    <DropdownMenuContent
      align="end"
      class="min-w-40"
    >
      <DropdownMenuRadioGroup
        :model-value="current?.code ?? ''"
        @update:model-value="onChange"
      >
        <DropdownMenuRadioItem
          v-for="locale in locales"
          :key="locale.code"
          :value="locale.code"
        >
          <span :lang="locale.html">{{ locale.name }}</span>
        </DropdownMenuRadioItem>
      </DropdownMenuRadioGroup>
    </DropdownMenuContent>
  </DropdownMenu>
</template>
