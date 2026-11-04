<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { Check, Pencil, Trash2 } from '@lucide/vue';
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    destroy as destroyPreset,
    store as storePreset,
    update as updatePreset,
} from '@/routes/badgeforge/presets';
import type { BadgeFormState } from './builder';
import type { BadgeForgePreset, BadgeForgePresetLimits } from './types';

/**
 * Presets do builder (ADR-079).
 *
 * O preset e um snapshot do formulario, nao um badge publicado: aplicar tem de
 * devolver exatamente o que estava na tela. O logo orfao (custom excluido ou
 * embutido removido da config) e avisado e cai para "sem logo".
 */
const props = defineProps<{
    open: boolean;
    presets: BadgeForgePreset[];
    limits: BadgeForgePresetLimits;
    builderForm: BadgeFormState;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    apply: [preset: BadgeForgePreset];
}>();

const { t } = useI18n();

const saveForm = useForm({ name: '' });
const renameForm = useForm({ name: '' });

const editingId = ref<string | null>(null);
const confirmingId = ref<string | null>(null);

watch(
    () => props.open,
    (open) => {
        if (!open) {
            editingId.value = null;
            confirmingId.value = null;
            renameForm.clearErrors();
        }
    },
);

function save(): void {
    if (saveForm.processing) {
        return;
    }

    const form = props.builderForm;

    saveForm
        .transform(() => ({
            name: saveForm.name,
            form: {
                label: form.label,
                message: form.message,
                style: form.style,
                font: form.font,
                color: form.color,
                labelColor: form.labelColor,
                logo: form.logo,
                logoColor: form.logoColor,
                logoSize: form.logoSize,
                logoPosition: form.logoPosition,
                link: form.link,
                cacheSeconds: form.cacheSeconds,
            },
        }))
        .post(storePreset.url(), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                saveForm.reset();
            },
        });
}

function apply(preset: BadgeForgePreset): void {
    emit('apply', preset);
    emit('update:open', false);
}

function startRename(preset: BadgeForgePreset): void {
    confirmingId.value = null;
    editingId.value = preset.id;
    renameForm.name = preset.name;
    renameForm.clearErrors();
}

function cancelRename(): void {
    editingId.value = null;
    renameForm.clearErrors();
}

function saveRename(preset: BadgeForgePreset): void {
    renameForm.patch(updatePreset.url({ badgePreset: preset.id }), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            editingId.value = null;
        },
    });
}

function startDelete(preset: BadgeForgePreset): void {
    editingId.value = null;
    confirmingId.value = preset.id;
}

function destroy(preset: BadgeForgePreset): void {
    router.delete(destroyPreset.url({ badgePreset: preset.id }), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            confirmingId.value = null;
        },
    });
}
</script>

<template>
  <Dialog
    :open="open"
    @update:open="(value: boolean) => emit('update:open', value)"
  >
    <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-xl">
      <DialogHeader>
        <DialogTitle>
          {{ t('tools.badgeforge.management.presets_title') }}
        </DialogTitle>
        <DialogDescription>
          {{ t('tools.badgeforge.management.presets_description') }}
        </DialogDescription>
      </DialogHeader>

      <form
        class="flex flex-wrap items-end gap-2"
        data-test="badge-preset-save"
        @submit.prevent="save"
      >
        <div class="min-w-0 flex-1 space-y-2">
          <Input
            v-model="saveForm.name"
            :maxlength="limits.nameMaxLength"
            :placeholder="
              t('tools.badgeforge.management.preset_name_placeholder')
            "
            :aria-label="t('tools.badgeforge.management.name')"
            data-test="badge-preset-name"
          />
          <InputError :message="saveForm.errors.name" />
        </div>
        <Button
          type="submit"
          size="sm"
          :disabled="saveForm.processing"
          data-test="badge-preset-save-submit"
        >
          {{ t('tools.badgeforge.management.save_preset') }}
        </Button>
      </form>

      <p class="text-xs text-muted-foreground">
        {{
          limits.limit === null
            ? t('tools.badgeforge.management.preset_unlimited_note', {
              used: limits.used,
            })
            : t('tools.badgeforge.management.preset_limit_note', {
              used: limits.used,
              limit: limits.limit,
            })
        }}
      </p>

      <div
        v-if="presets.length === 0"
        class="rounded-md border border-dashed p-4 text-center text-sm text-muted-foreground"
        data-test="badge-preset-empty"
      >
        {{ t('tools.badgeforge.management.empty_presets') }}
      </div>

      <ul
        v-else
        class="divide-y rounded-md border"
        data-test="badge-preset-list"
      >
        <li
          v-for="preset in presets"
          :key="preset.id"
          class="space-y-2 px-3 py-2"
          data-test="badge-preset-row"
        >
          <div class="flex flex-wrap items-center gap-2">
            <div class="min-w-0 flex-1">
              <template v-if="editingId === preset.id">
                <Input
                  v-model="renameForm.name"
                  :maxlength="limits.nameMaxLength"
                  :aria-label="t('tools.badgeforge.management.name')"
                  data-test="badge-preset-rename-input"
                  @keyup.enter="saveRename(preset)"
                />
                <InputError :message="renameForm.errors.name" />
              </template>
              <p
                v-else
                class="truncate text-sm font-medium"
              >
                {{ preset.name }}
              </p>
              <Badge
                v-if="preset.logoOrphaned"
                variant="outline"
                class="border-amber-500/40 text-amber-600"
                data-test="badge-preset-orphaned"
              >
                {{ t('tools.badgeforge.management.preset_orphaned') }}
              </Badge>
            </div>

            <template v-if="editingId === preset.id">
              <Button
                size="sm"
                :disabled="renameForm.processing"
                data-test="badge-preset-rename-save"
                @click="saveRename(preset)"
              >
                {{ t('tools.badgeforge.management.save') }}
              </Button>
              <Button
                variant="ghost"
                size="sm"
                data-test="badge-preset-rename-cancel"
                @click="cancelRename"
              >
                {{ t('tools.badgeforge.management.cancel') }}
              </Button>
            </template>
            <template v-else>
              <Button
                variant="outline"
                size="sm"
                data-test="badge-preset-apply"
                @click="apply(preset)"
              >
                <Check class="size-4" />
                {{ t('tools.badgeforge.management.apply_preset') }}
              </Button>
              <Button
                variant="ghost"
                size="sm"
                data-test="badge-preset-rename"
                @click="startRename(preset)"
              >
                <Pencil class="size-4" />
                {{ t('tools.badgeforge.management.rename') }}
              </Button>
              <Button
                variant="ghost"
                size="sm"
                data-test="badge-preset-delete"
                @click="startDelete(preset)"
              >
                <Trash2 class="size-4" />
                {{ t('tools.badgeforge.management.delete') }}
              </Button>
            </template>
          </div>

          <div
            v-if="confirmingId === preset.id"
            class="rounded-md border border-destructive/40 bg-destructive/5 px-3 py-2"
            data-test="badge-preset-delete-confirm"
          >
            <p class="text-sm font-medium">
              {{
                t('tools.badgeforge.management.delete_title', {
                  name: preset.name,
                })
              }}
            </p>
            <p class="text-xs text-muted-foreground">
              {{ t('tools.badgeforge.management.delete_description') }}
            </p>
            <div class="mt-2 flex gap-2">
              <Button
                variant="destructive"
                size="sm"
                data-test="badge-preset-delete-confirmed"
                @click="destroy(preset)"
              >
                {{ t('tools.badgeforge.management.delete') }}
              </Button>
              <Button
                variant="outline"
                size="sm"
                data-test="badge-preset-delete-cancel"
                @click="confirmingId = null"
              >
                {{ t('tools.badgeforge.management.cancel') }}
              </Button>
            </div>
          </div>
        </li>
      </ul>
    </DialogContent>
  </Dialog>
</template>
