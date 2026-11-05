<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { Copy, Pencil, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    destroy as destroyEndpoint,
    update as updateEndpoint,
} from '@/routes/badgeforge/endpoints';
import { useCopyFeedback } from '@/composables/useCopyFeedback';
import EndpointBadgeDialog from './EndpointBadgeDialog.vue';
import type {
    BadgeForgeEndpointBadge,
    BadgeForgeToolProps,
} from './types';

/**
 * Lista dos endpoints JSON do dono (F5, ADR-083).
 *
 * O dono copia a URL publica (ULID), renomeia e exclui; a URL de origem so
 * aparece no dialogo de criacao e nunca volta do servidor. Atualizar a imagem
 * e do cache do servidor (TTL fixo), entao nao ha botao de atualizar.
 */

const props = defineProps<{ toolProps: BadgeForgeToolProps }>();

const { t } = useI18n();

const { message: copyMessage, copy } = useCopyFeedback();

const renameForm = useForm({ name: '' });

const dialogOpen = ref(false);
const editingId = ref<string | null>(null);
const confirmingId = ref<string | null>(null);

const canCreate = (): boolean =>
    props.toolProps.management.canCreateEndpointBadges;

const endpoints = (): BadgeForgeEndpointBadge[] => props.toolProps.endpointBadges;

function startRename(endpoint: BadgeForgeEndpointBadge): void {
    confirmingId.value = null;
    editingId.value = endpoint.id;
    renameForm.name = endpoint.name;
    renameForm.clearErrors();
}

function saveRename(endpoint: BadgeForgeEndpointBadge): void {
    renameForm.patch(updateEndpoint.url({ badgeEndpoint: endpoint.id }), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            editingId.value = null;
        },
    });
}

function startDelete(endpoint: BadgeForgeEndpointBadge): void {
    editingId.value = null;
    confirmingId.value = endpoint.id;
}

function destroy(endpoint: BadgeForgeEndpointBadge): void {
    router.delete(destroyEndpoint.url({ badgeEndpoint: endpoint.id }), {
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
    data-test="badgeforge-endpoint"
  >
    <div class="flex flex-wrap items-center justify-between gap-2">
      <h3 class="text-sm font-medium">
        {{ t('tools.badgeforge.endpoint.title') }}
      </h3>

      <div class="flex items-center gap-2">
        <span
          v-if="copyMessage"
          class="text-xs text-muted-foreground"
          data-test="badgeforge-endpoint-copy-feedback"
        >
          {{ copyMessage }}
        </span>
        <Button
          v-if="canCreate()"
          variant="outline"
          size="sm"
          data-test="badgeforge-endpoint-create"
          @click="dialogOpen = true"
        >
          {{ t('tools.badgeforge.endpoint.create_button') }}
        </Button>
      </div>
    </div>

    <p class="text-xs text-subtle">
      {{ t('tools.badgeforge.endpoint.description') }}
    </p>

    <div
      v-if="!canCreate()"
      class="rounded-md border border-dashed px-4 py-3 text-sm"
      data-test="badgeforge-endpoint-readonly"
    >
      <p class="text-subtle">
        {{ t('tools.badgeforge.endpoint.readonly_note') }}
      </p>
    </div>

    <div
      v-if="endpoints().length === 0"
      class="rounded-lg border border-dashed px-4 py-8 text-center text-sm text-subtle"
      data-test="badgeforge-endpoint-empty"
    >
      {{ t('tools.badgeforge.endpoint.empty') }}
    </div>

    <ul
      v-else
      class="divide-y rounded-lg border border-border"
      data-test="badgeforge-endpoint-list"
    >
      <li
        v-for="endpoint in endpoints()"
        :key="endpoint.id"
        class="space-y-2 px-4 py-3"
        data-test="badgeforge-endpoint-row"
      >
        <div class="flex flex-wrap items-center gap-2">
          <img
            :src="endpoint.svgUrl"
            :alt="endpoint.name"
            class="h-5 max-w-48"
            data-test="badgeforge-endpoint-preview"
          >

          <div class="min-w-0 flex-1">
            <template v-if="editingId === endpoint.id">
              <Input
                v-model="renameForm.name"
                :maxlength="toolProps.limits.endpointBadges.nameMaxLength"
                :aria-label="t('tools.badgeforge.endpoint.name')"
                data-test="badgeforge-endpoint-rename-input"
                @keyup.enter="saveRename(endpoint)"
              />
              <InputError :message="renameForm.errors.name" />
            </template>
            <p
              v-else
              class="truncate text-sm font-medium"
            >
              {{ endpoint.name }}
            </p>
          </div>

          <template v-if="editingId === endpoint.id">
            <Button
              size="sm"
              :disabled="renameForm.processing"
              data-test="badgeforge-endpoint-rename-save"
              @click="saveRename(endpoint)"
            >
              {{ t('tools.badgeforge.management.save') }}
            </Button>
            <Button
              variant="ghost"
              size="sm"
              data-test="badgeforge-endpoint-rename-cancel"
              @click="editingId = null"
            >
              {{ t('tools.badgeforge.management.cancel') }}
            </Button>
          </template>
          <template v-else>
            <Button
              variant="outline"
              size="sm"
              data-test="badgeforge-endpoint-copy-svg"
              @click="copy(endpoint.svgUrl)"
            >
              <Copy class="size-4" />
              {{ t('tools.badgeforge.endpoint.copy_svg') }}
            </Button>
            <Button
              variant="outline"
              size="sm"
              data-test="badgeforge-endpoint-copy-png"
              @click="copy(endpoint.pngUrl)"
            >
              <Copy class="size-4" />
              {{ t('tools.badgeforge.endpoint.copy_png') }}
            </Button>

            <template v-if="canCreate()">
              <Button
                variant="ghost"
                size="sm"
                data-test="badgeforge-endpoint-rename"
                @click="startRename(endpoint)"
              >
                <Pencil class="size-4" />
                {{ t('tools.badgeforge.management.rename') }}
              </Button>
            </template>

            <Button
              variant="ghost"
              size="sm"
              data-test="badgeforge-endpoint-delete"
              @click="startDelete(endpoint)"
            >
              <Trash2 class="size-4" />
              {{ t('tools.badgeforge.management.delete') }}
            </Button>
          </template>
        </div>

        <div
          v-if="confirmingId === endpoint.id"
          class="rounded-md border border-destructive/40 bg-destructive/5 px-3 py-2"
          data-test="badgeforge-endpoint-delete-confirm"
        >
          <p class="text-sm font-medium">
            {{
              t('tools.badgeforge.management.delete_title', {
                name: endpoint.name,
              })
            }}
          </p>
          <p class="text-xs text-subtle">
            {{ t('tools.badgeforge.endpoint.delete_description') }}
          </p>
          <div class="mt-2 flex gap-2">
            <Button
              variant="destructive"
              size="sm"
              data-test="badgeforge-endpoint-delete-confirmed"
              @click="destroy(endpoint)"
            >
              {{ t('tools.badgeforge.management.delete') }}
            </Button>
            <Button
              variant="outline"
              size="sm"
              data-test="badgeforge-endpoint-delete-cancel"
              @click="confirmingId = null"
            >
              {{ t('tools.badgeforge.management.cancel') }}
            </Button>
          </div>
        </div>
      </li>
    </ul>

    <EndpointBadgeDialog
      v-model:open="dialogOpen"
      :tool-props="toolProps"
    />
  </section>
</template>
