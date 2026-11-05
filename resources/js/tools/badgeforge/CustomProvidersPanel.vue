<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Copy, Pencil, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { destroy as destroyCustomProvider } from '@/routes/badgeforge/custom-providers';
import { useCopyFeedback } from '@/composables/useCopyFeedback';
import CustomProviderDialog from './CustomProviderDialog.vue';
import type {
    BadgeForgeCustomProvider,
    BadgeForgeCustomProviderDefinition,
    BadgeForgeToolProps,
} from './types';

/**
 * Providers customizados do dono (F7, ADR-087).
 *
 * O painel lista, cria, edita e exclui; a origem (template) nunca esta aqui,
 * so o nome, as metricas e a URL publica opaca. Free ve a lista e consegue
 * excluir (downgrade), mas nao criar nem editar - o servidor tambem recusa.
 */

const props = defineProps<{
    toolProps: BadgeForgeToolProps;
    upgradeUrl: string;
}>();

const { t } = useI18n();

const { message: copyMessage, copy } = useCopyFeedback();

const dialogOpen = ref(false);
const editingProvider = ref<BadgeForgeCustomProvider | null>(null);
const confirmingId = ref<string | null>(null);

const canCreate = computed(
    () => props.toolProps.management.canCreateCustomProviders,
);

const providers = computed(() => props.toolProps.customProviders);

const atLimit = computed(() => {
    const limit = props.toolProps.limits.customProviders.limit;

    return limit !== null && props.toolProps.limits.customProviders.used >= limit;
});

const origin = typeof window === 'undefined' ? '' : window.location.origin;

function openCreate(): void {
    editingProvider.value = null;
    dialogOpen.value = true;
}

function openEdit(provider: BadgeForgeCustomProvider): void {
    editingProvider.value = provider;
    dialogOpen.value = true;
}

function metricUrl(
    provider: BadgeForgeCustomProvider,
    definition: BadgeForgeCustomProviderDefinition,
    extension: 'svg' | 'png',
): string {
    const base = props.toolProps.customEndpoint.replace(/\/+$/, '');

    return `${origin}${base}/${provider.id}/${definition.slug}/${provider.examplePath}.${extension}`;
}

function startDelete(provider: BadgeForgeCustomProvider): void {
    confirmingId.value = provider.id;
}

function destroy(provider: BadgeForgeCustomProvider): void {
    router.delete(destroyCustomProvider.url({ badgeCustomProvider: provider.id }), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            confirmingId.value = null;
        },
    });
}
</script>

<template>
  <section
    class="space-y-3"
    data-test="badgeforge-custom-providers"
  >
    <div class="flex flex-wrap items-center justify-between gap-2">
      <div>
        <h3 class="text-sm font-medium">
          {{ t('tools.badgeforge.custom_providers.title') }}
        </h3>
        <p class="mt-1 text-xs text-subtle">
          {{ t('tools.badgeforge.custom_providers.description') }}
        </p>
      </div>

      <div class="flex items-center gap-2">
        <span
          v-if="copyMessage"
          class="text-xs text-muted-foreground"
          data-test="badgeforge-custom-copy-feedback"
        >
          {{ copyMessage }}
        </span>
        <Button
          v-if="canCreate"
          variant="outline"
          size="sm"
          :disabled="atLimit"
          data-test="badgeforge-custom-create"
          @click="openCreate"
        >
          {{ t('tools.badgeforge.custom_providers.create_button') }}
        </Button>
      </div>
    </div>

    <p
      v-if="!canCreate"
      class="rounded-md border border-dashed px-4 py-3 text-sm text-subtle"
      data-test="badgeforge-custom-readonly"
    >
      {{ t('tools.badgeforge.custom_providers.readonly_note') }}
      <a
        :href="upgradeUrl"
        class="font-medium underline underline-offset-2"
        data-test="badgeforge-custom-upgrade"
      >
        {{ t('tools.badgeforge.custom_providers.upgrade') }}
      </a>
    </p>

    <p
      v-else-if="atLimit"
      class="text-xs text-subtle"
      data-test="badgeforge-custom-limit"
    >
      {{
        t('tools.badgeforge.custom_providers.limit_reached', {
          limit: toolProps.limits.customProviders.limit,
        })
      }}
    </p>

    <div
      v-if="providers.length === 0"
      class="rounded-lg border border-dashed px-4 py-8 text-center text-sm text-subtle"
      data-test="badgeforge-custom-empty"
    >
      {{ t('tools.badgeforge.custom_providers.empty') }}
    </div>

    <ul
      v-else
      class="divide-y rounded-lg border border-border"
      data-test="badgeforge-custom-list"
    >
      <li
        v-for="provider in providers"
        :key="provider.id"
        class="space-y-2 px-4 py-3"
        data-test="badgeforge-custom-row"
      >
        <div class="flex flex-wrap items-center gap-2">
          <p class="min-w-0 flex-1 truncate text-sm font-medium">
            {{ provider.name }}
          </p>

          <Badge
            v-if="!provider.enabled"
            variant="outline"
            class="border-amber-500/40 text-amber-600"
            data-test="badgeforge-custom-disabled"
          >
            {{ t('tools.badgeforge.custom_providers.disabled') }}
          </Badge>

          <template v-if="canCreate">
            <Button
              variant="ghost"
              size="sm"
              data-test="badgeforge-custom-edit"
              @click="openEdit(provider)"
            >
              <Pencil class="size-4" />
              {{ t('tools.badgeforge.management.rename') }}
            </Button>
          </template>

          <Button
            variant="ghost"
            size="sm"
            data-test="badgeforge-custom-delete"
            @click="startDelete(provider)"
          >
            <Trash2 class="size-4" />
            {{ t('tools.badgeforge.management.delete') }}
          </Button>
        </div>

        <ul class="space-y-2">
          <li
            v-for="definition in provider.definitions"
            :key="definition.slug"
            class="flex flex-wrap items-center gap-2 text-sm"
            data-test="badgeforge-custom-metric"
          >
            <span class="min-w-24 text-subtle">
              {{ definition.label }}
            </span>
            <Button
              variant="outline"
              size="sm"
              data-test="badgeforge-custom-copy-svg"
              @click="copy(metricUrl(provider, definition, 'svg'))"
            >
              <Copy class="size-4" />
              {{ t('tools.badgeforge.custom_providers.copy_svg') }}
            </Button>
            <Button
              variant="outline"
              size="sm"
              data-test="badgeforge-custom-copy-png"
              @click="copy(metricUrl(provider, definition, 'png'))"
            >
              <Copy class="size-4" />
              {{ t('tools.badgeforge.custom_providers.copy_png') }}
            </Button>
          </li>
        </ul>

        <div
          v-if="confirmingId === provider.id"
          class="rounded-md border border-destructive/40 bg-destructive/5 px-3 py-2"
          data-test="badgeforge-custom-delete-confirm"
        >
          <p class="text-sm font-medium">
            {{
              t('tools.badgeforge.management.delete_title', {
                name: provider.name,
              })
            }}
          </p>
          <p class="text-xs text-subtle">
            {{ t('tools.badgeforge.custom_providers.delete_description') }}
          </p>
          <div class="mt-2 flex gap-2">
            <Button
              variant="destructive"
              size="sm"
              data-test="badgeforge-custom-delete-confirmed"
              @click="destroy(provider)"
            >
              {{ t('tools.badgeforge.management.delete') }}
            </Button>
            <Button
              variant="outline"
              size="sm"
              data-test="badgeforge-custom-delete-cancel"
              @click="confirmingId = null"
            >
              {{ t('tools.badgeforge.management.cancel') }}
            </Button>
          </div>
        </div>
      </li>
    </ul>

    <CustomProviderDialog
      v-if="canCreate"
      v-model:open="dialogOpen"
      :tool-props="toolProps"
      :provider="editingProvider"
    />
  </section>
</template>
