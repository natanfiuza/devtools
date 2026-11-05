<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import admin from '@/routes/admin';
import type {
    AdminBadgeProviderDefinitionRow,
    AdminBadgeProviderRow,
} from '@/types';

/**
 * Catalogo global do BadgeForge (F7, ADR-086).
 *
 * Provider e definicao so ligam e desligam: slug, template e parser continuam
 * contrato da migracao. Desligar tira a rota publica sem apagar historico.
 */
defineProps<{ providers: AdminBadgeProviderRow[] }>();

const { t } = useI18n();

function toggleProvider(provider: AdminBadgeProviderRow, value: unknown): void {
    router.patch(
        admin.badgeforge.providers.update.url(provider.id),
        { enabled: value === true },
        { preserveScroll: true, preserveState: true },
    );
}

function toggleDefinition(
    provider: AdminBadgeProviderRow,
    definition: AdminBadgeProviderDefinitionRow,
    value: unknown,
): void {
    router.patch(
        admin.badgeforge.providerDefinitions.update.url([
            provider.id,
            definition.id,
        ]),
        { enabled: value === true },
        { preserveScroll: true, preserveState: true },
    );
}
</script>

<template>
  <Head :title="t('admin.badge_providers.title')" />

  <div class="space-y-4">
    <header>
      <h2 class="text-lg font-medium">
        {{ t('admin.badge_providers.title') }}
      </h2>
      <p class="mt-1 text-sm text-subtle">
        {{ t('admin.badge_providers.description') }}
      </p>
      <p class="mt-1 text-xs text-subtle">
        {{ t('admin.badge_providers.readonly') }}
      </p>
    </header>

    <p
      v-if="providers.length === 0"
      class="text-sm text-subtle"
    >
      {{ t('admin.badge_providers.empty') }}
    </p>

    <div
      v-else
      class="space-y-3"
    >
      <div
        v-for="provider in providers"
        :key="provider.id"
        class="rounded-lg border border-border"
        :data-test="`badge-provider-${provider.slug}`"
      >
        <div
          class="flex flex-wrap items-center justify-between gap-3 border-b border-border px-3 py-2"
        >
          <div>
            <p class="font-medium">
              {{ provider.name }}
            </p>
            <p class="font-mono text-[13px] text-subtle">
              {{ provider.slug }} ·
              {{ t('admin.badge_providers.category') }}:
              {{ provider.category }}
            </p>
          </div>

          <div class="flex items-center gap-3">
            <Badge
              :variant="provider.enabled ? 'secondary' : 'outline'"
            >
              {{
                provider.enabled
                  ? t('admin.badge_providers.enabled')
                  : t('admin.badge_providers.disabled')
              }}
            </Badge>

            <Checkbox
              :id="`provider-${provider.id}`"
              :model-value="provider.enabled"
              @update:model-value="
                (value) => toggleProvider(provider, value)
              "
            />
            <Label
              :for="`provider-${provider.id}`"
              class="sr-only"
            >
              {{ t('admin.badge_providers.toggle') }}
            </Label>
          </div>
        </div>

        <ul class="divide-y divide-border">
          <li
            v-for="definition in provider.definitions"
            :key="definition.id"
            class="flex flex-wrap items-center justify-between gap-3 px-3 py-2 pl-6"
            :data-test="`badge-definition-${definition.slug}`"
          >
            <div class="flex items-center gap-3">
              <span class="font-mono text-[13px] text-subtle">
                {{ t('admin.badge_providers.definition') }}:
              </span>
              <span class="text-sm">
                {{ definition.label }}
              </span>
              <span class="font-mono text-[13px] text-subtle">
                {{ definition.slug }}
              </span>
            </div>

            <div class="flex items-center gap-3">
              <Badge
                :variant="definition.enabled ? 'secondary' : 'outline'"
              >
                {{
                  definition.enabled
                    ? t('admin.badge_providers.enabled')
                    : t('admin.badge_providers.disabled')
                }}
              </Badge>

              <Checkbox
                :id="`definition-${definition.id}`"
                :model-value="definition.enabled"
                @update:model-value="
                  (value) => toggleDefinition(provider, definition, value)
                "
              />
              <Label
                :for="`definition-${definition.id}`"
                class="sr-only"
              >
                {{ t('admin.badge_providers.toggle_definition') }}
              </Label>
            </div>
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>
