<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AdminTable from '@/components/admin/AdminTable.vue';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import admin from '@/routes/admin';
import type {
    AdminBadgeCustomProviderRow,
    AdminColumn,
    Paginator,
} from '@/types';

/**
 * Providers customizados no painel (F7, ADR-087).
 *
 * O admin e a unica visao com a origem: template e pointers aparecem aqui para
 * investigar. A acao e desabilitar/reabilitar - nunca excluir, porque o dado
 * e do dono.
 */
const props = defineProps<{
    providers: Paginator<AdminBadgeCustomProviderRow>;
}>();

const { t } = useI18n();

const columns = computed<AdminColumn[]>(() => [
    { key: 'name', label: t('admin.badge_custom_providers.name') },
    { key: 'owner', label: t('admin.badge_custom_providers.owner') },
    { key: 'template', label: t('admin.badge_custom_providers.template') },
    { key: 'metrics', label: t('admin.badge_custom_providers.metrics') },
    { key: 'state', label: t('admin.badge_custom_providers.state') },
]);

function toggle(row: AdminBadgeCustomProviderRow, value: unknown): void {
    router.patch(
        admin.badgeforge.customProviders.update.url(row.id),
        { enabled: value === true },
        { preserveScroll: true, preserveState: true },
    );
}

function goToPage(page: number): void {
    router.get(
        admin.badgeforge.customProviders.index.url(),
        { page },
        { preserveState: true, preserveScroll: true },
    );
}
</script>

<template>
  <Head :title="t('admin.badge_custom_providers.title')" />

  <div class="space-y-4">
    <header>
      <h2 class="text-lg font-medium">
        {{ t('admin.badge_custom_providers.title') }}
      </h2>
      <p class="mt-1 text-sm text-subtle">
        {{ t('admin.badge_custom_providers.description') }}
      </p>
      <p class="mt-1 text-xs text-subtle">
        {{ t('admin.badge_custom_providers.readonly') }}
      </p>
    </header>

    <AdminTable
      :columns="columns"
      :rows="props.providers"
      :empty="t('admin.badge_custom_providers.empty')"
      @page="goToPage"
    >
      <template #cell-name="{ row }">
        <p class="font-medium">
          {{ row.name }}
        </p>
        <p class="font-mono text-[12px] text-subtle">
          {{ row.id }}
        </p>
      </template>

      <template #cell-owner="{ row }">
        <p>{{ row.ownerName }}</p>
        <p class="text-[12px] text-subtle">
          {{ row.ownerEmail }}
        </p>
      </template>

      <template #cell-template="{ row }">
        <code class="block max-w-[36ch] break-all font-mono text-[12px]">
          {{ row.urlTemplate }}
        </code>
        <p class="mt-1 font-mono text-[12px] text-subtle">
          {{ row.pathParams.join(', ') }}
        </p>
        <p class="font-mono text-[12px] text-subtle">
          {{ row.examplePath }}
        </p>
      </template>

      <template #cell-metrics="{ row }">
        <ul class="space-y-1">
          <li
            v-for="definition in row.definitions"
            :key="definition.id"
            class="font-mono text-[12px]"
          >
            {{ definition.slug }} = {{ definition.pointer }}
            <span class="text-subtle">({{ definition.formatter }})</span>
          </li>
        </ul>
      </template>

      <template #cell-state="{ row }">
        <div class="flex items-center gap-3">
          <Badge :variant="row.enabled ? 'secondary' : 'outline'">
            {{
              row.enabled
                ? t('admin.badge_custom_providers.enabled')
                : t('admin.badge_custom_providers.disabled')
            }}
          </Badge>

          <Checkbox
            :id="`custom-provider-${row.id}`"
            :model-value="row.enabled"
            @update:model-value="(value) => toggle(row, value)"
          />
          <Label
            :for="`custom-provider-${row.id}`"
            class="sr-only"
          >
            {{ t('admin.badge_custom_providers.toggle') }}
          </Label>
        </div>
      </template>
    </AdminTable>
  </div>
</template>
