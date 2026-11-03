<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { Eye, Pencil, Trash2 } from '@lucide/vue';
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
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
    destroy as destroyLogo,
    update as updateLogo,
} from '@/routes/badgeforge/logos';
import LogoPreview from './LogoPreview.vue';
import LogoUploadForm from './LogoUploadForm.vue';
import { forgetCustomLogo, loadCustomLogo } from './customLogos';
import type {
    BadgeForgeCustomLogoLimits,
    BadgeForgeCustomLogoSummary,
    BadgeLogo,
} from './types';

/**
 * Gestao dos logos personalizados dentro do builder (ADR-078, ADR-079).
 *
 * O dialogo so lista metadados; os paths sao buscados quando o usuario pede a
 * previa. Upload, renomeacao e exclusao sao as unicas requisicoes - o resto do
 * builder continua local.
 */
const props = defineProps<{
    open: boolean;
    logos: BadgeForgeCustomLogoSummary[];
    limits: BadgeForgeCustomLogoLimits;
    upgradeUrl: string;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const { t } = useI18n();

const previewId = ref<string | null>(null);
const previewLogo = ref<BadgeLogo | null>(null);
const previewLoading = ref(false);
const previewFailed = ref(false);

const editingId = ref<string | null>(null);
const confirmingId = ref<string | null>(null);

const renameForm = useForm({ name: '' });

watch(
    () => props.open,
    (open) => {
        if (!open) {
            resetRowState();
        }
    },
);

function close(): void {
    emit('update:open', false);
}

function resetRowState(): void {
    previewId.value = null;
    previewLogo.value = null;
    previewLoading.value = false;
    previewFailed.value = false;
    editingId.value = null;
    confirmingId.value = null;
    renameForm.clearErrors();
}

async function togglePreview(logo: BadgeForgeCustomLogoSummary): Promise<void> {
    if (previewId.value === logo.id) {
        previewId.value = null;
        previewLogo.value = null;
        previewFailed.value = false;

        return;
    }

    previewId.value = logo.id;
    previewLogo.value = null;
    previewFailed.value = false;
    previewLoading.value = true;

    const loaded = await loadCustomLogo(logo);

    if (previewId.value !== logo.id) {
        return;
    }

    previewLogo.value = loaded;
    previewLoading.value = false;
    previewFailed.value = loaded === null;
}

function startRename(logo: BadgeForgeCustomLogoSummary): void {
    confirmingId.value = null;
    editingId.value = logo.id;
    renameForm.name = logo.name;
    renameForm.clearErrors();
}

function cancelRename(): void {
    editingId.value = null;
    renameForm.clearErrors();
}

function saveRename(logo: BadgeForgeCustomLogoSummary): void {
    renameForm.patch(updateLogo.url({ badgeCustomLogo: logo.id }), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            editingId.value = null;
        },
    });
}

function startDelete(logo: BadgeForgeCustomLogoSummary): void {
    editingId.value = null;
    confirmingId.value = logo.id;
}

function destroy(logo: BadgeForgeCustomLogoSummary): void {
    router.delete(destroyLogo.url({ badgeCustomLogo: logo.id }), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            forgetCustomLogo(logo.slug);
            confirmingId.value = null;

            if (previewId.value === logo.id) {
                previewId.value = null;
                previewLogo.value = null;
            }
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
          {{ t('tools.badgeforge.management.logos_title') }}
        </DialogTitle>
        <DialogDescription>
          {{ t('tools.badgeforge.management.logos_description') }}
        </DialogDescription>
      </DialogHeader>

      <LogoUploadForm
        :max-bytes="limits.maxBytes"
        :name-max-length="limits.nameMaxLength"
        :limit="limits.limit"
        :used="limits.used"
        :upgrade-url="upgradeUrl"
      />

      <div
        v-if="logos.length === 0"
        class="rounded-md border border-dashed p-4 text-center text-sm text-muted-foreground"
        data-test="badge-logo-empty"
      >
        {{ t('tools.badgeforge.management.empty_logos') }}
      </div>

      <ul
        v-else
        class="divide-y rounded-md border"
        data-test="badge-logo-list"
      >
        <li
          v-for="logo in logos"
          :key="logo.id"
          class="space-y-2 px-3 py-2"
          data-test="badge-logo-row"
        >
          <div class="flex flex-wrap items-center gap-2">
            <div class="min-w-0 flex-1">
              <template v-if="editingId === logo.id">
                <Input
                  v-model="renameForm.name"
                  :maxlength="limits.nameMaxLength"
                  :aria-label="t('tools.badgeforge.management.name')"
                  data-test="badge-logo-rename-input"
                  @keyup.enter="saveRename(logo)"
                />
                <InputError :message="renameForm.errors.name" />
              </template>
              <p
                v-else
                class="truncate text-sm font-medium"
              >
                {{ logo.name }}
              </p>
              <p class="text-xs text-muted-foreground">
                {{
                  t('tools.badgeforge.management.path_count', {
                    count: logo.pathCount,
                  })
                }}
              </p>
            </div>

            <template v-if="editingId === logo.id">
              <Button
                size="sm"
                :disabled="renameForm.processing"
                data-test="badge-logo-rename-save"
                @click="saveRename(logo)"
              >
                {{ t('tools.badgeforge.management.save') }}
              </Button>
              <Button
                variant="ghost"
                size="sm"
                data-test="badge-logo-rename-cancel"
                @click="cancelRename"
              >
                {{ t('tools.badgeforge.management.cancel') }}
              </Button>
            </template>
            <template v-else>
              <Button
                variant="outline"
                size="sm"
                data-test="badge-logo-preview-toggle"
                @click="togglePreview(logo)"
              >
                <Eye class="size-4" />
                {{ t('tools.badgeforge.management.preview') }}
              </Button>
              <Button
                variant="ghost"
                size="sm"
                data-test="badge-logo-rename"
                @click="startRename(logo)"
              >
                <Pencil class="size-4" />
                {{ t('tools.badgeforge.management.rename') }}
              </Button>
              <Button
                variant="ghost"
                size="sm"
                data-test="badge-logo-delete"
                @click="startDelete(logo)"
              >
                <Trash2 class="size-4" />
                {{ t('tools.badgeforge.management.delete') }}
              </Button>
            </template>
          </div>

          <div
            v-if="previewId === logo.id"
            class="flex min-h-10 items-center rounded-md bg-muted/50 px-3 py-2"
          >
            <p
              v-if="previewLoading"
              class="text-xs text-muted-foreground"
            >
              {{ t('tools.badgeforge.management.preview_loading') }}
            </p>
            <p
              v-else-if="previewFailed || previewLogo === null"
              class="text-xs text-destructive"
            >
              {{ t('tools.badgeforge.management.preview_error') }}
            </p>
            <LogoPreview
              v-else
              :view-box="previewLogo.viewBox"
              :paths="previewLogo.paths"
              color="#000000"
            />
          </div>

          <div
            v-if="confirmingId === logo.id"
            class="rounded-md border border-destructive/40 bg-destructive/5 px-3 py-2"
            data-test="badge-logo-delete-confirm"
          >
            <p class="text-sm font-medium">
              {{
                t('tools.badgeforge.management.delete_title', {
                  name: logo.name,
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
                data-test="badge-logo-delete-confirmed"
                @click="destroy(logo)"
              >
                {{ t('tools.badgeforge.management.delete') }}
              </Button>
              <Button
                variant="outline"
                size="sm"
                data-test="badge-logo-delete-cancel"
                @click="confirmingId = null"
              >
                {{ t('tools.badgeforge.management.cancel') }}
              </Button>
            </div>
          </div>
        </li>
      </ul>

      <div class="flex justify-end">
        <Button
          variant="outline"
          size="sm"
          data-test="badge-logo-close"
          @click="close"
        >
          {{ t('tools.badgeforge.management.cancel') }}
        </Button>
      </div>
    </DialogContent>
  </Dialog>
</template>
