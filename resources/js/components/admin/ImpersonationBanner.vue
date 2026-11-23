<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import impersonation from '@/routes/impersonation';

/**
 * Faixa de aviso da impersonation (ADR-025).
 *
 * Fica no topo de toda tela autenticada: enquanto ela existe, quem age e o
 * alvo, e a unica porta de volta e a rota `impersonation.destroy` - que vive
 * fora do painel, porque durante a impersonation o painel esta fechado.
 */
const { t } = useI18n();

const page = usePage();

const active = computed(() => page.props.impersonation);

function leave(): void {
    router.delete(impersonation.destroy.url(), { preserveScroll: true });
}
</script>

<template>
  <div
    v-if="active"
    class="flex flex-wrap items-center justify-between gap-3 border-b border-border bg-accent/10 px-4 py-2"
  >
    <p class="text-sm">
      {{ t('admin.impersonation.banner', { name: active.email }) }}
    </p>
    <Button
      variant="outline"
      size="sm"
      @click="leave"
    >
      {{ t('admin.impersonation.leave') }}
    </Button>
  </div>
</template>
