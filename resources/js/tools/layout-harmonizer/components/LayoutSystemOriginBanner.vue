<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { X } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import type { LayoutSystemToolOrigin } from '@/types/layoutSystem';

/**
 * Origem do CTA da paleta (F4, ADR-172).
 *
 * So aparece quando o servidor aceitou `?palette={ULID}`. Remover limpa a
 * query e o id: o sistema pode ser salvo sem credito, mas nunca com credito
 * inventado.
 */

defineProps<{
    origin: LayoutSystemToolOrigin;
}>();

const emit = defineEmits<{
    remove: [];
}>();

const { t } = useI18n();
</script>

<template>
  <div
    class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm"
    data-test="layout-origin-banner"
  >
    <p>
      {{
        t('tools.layout-harmonizer.origin.from', { name: origin.name })
      }}
      <Link
        v-if="origin.url !== null"
        :href="origin.url"
        class="underline hover:no-underline"
        data-test="layout-origin-link"
      >
        {{ t('tools.layout-harmonizer.origin.open') }}
      </Link>
    </p>

    <Button
      type="button"
      variant="ghost"
      size="sm"
      data-test="layout-origin-remove"
      @click="emit('remove')"
    >
      <X class="size-4" />
      {{ t('tools.layout-harmonizer.origin.remove') }}
    </Button>
  </div>
</template>
