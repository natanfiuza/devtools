<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Eye, ExternalLink, Pencil, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    destroy as destroyLogo,
    update as updateLogo,
} from '@/routes/badgeforge/logos';
import {
    destroy as destroyPreset,
    update as updatePreset,
} from '@/routes/badgeforge/presets';
import LogoPreview from '@/tools/badgeforge/LogoPreview.vue';
import LogoUploadForm from '@/tools/badgeforge/LogoUploadForm.vue';
import { forgetCustomLogo, loadCustomLogo } from '@/tools/badgeforge/customLogos';
import type {
    AccountBadgeForgeProps,
} from '@/types';
import type {
    BadgeForgeCustomLogoSummary,
    BadgeForgePreset,
    BadgeLogo,
} from '@/tools/badgeforge/types';

/**
 * BadgeForge da conta (`/dashboard/badgeforge`, ADR-079).
 *
 * A pagina reusa o contrato da tool (`toolProps`): os mesmos limites, as mesmas
 * listas e os mesmos metadados. Os paths dos logos continuam sob demanda; a
 * previa so busca o logo quando o usuario pede.
 */
const props = defineProps<AccountBadgeForgeProps>();

const { t } = useI18n();

const logos = props.toolProps.customLogos;
const presets = props.toolProps.presets;
const logoLimits = props.toolProps.limits.customLogos;
const presetLimits = props.toolProps.limits.presets;

const previewId = ref<string | null>(null);
const previewLogo = ref<BadgeLogo | null>(null);
const previewLoading = ref(false);
const previewFailed = ref(false);

const editingLogoId = ref<string | null>(null);
const confirmingLogoId = ref<string | null>(null);
const editingPresetId = ref<string | null>(null);
const confirmingPresetId = ref<string | null>(null);

const renameLogoForm = useForm({ name: '' });
const renamePresetForm = useForm({ name: '' });

function presetUrl(preset: BadgeForgePreset): string {
    const url = new URL(props.toolUrl, 'http://localhost');
    url.searchParams.set('preset', preset.id);

    return `${url.pathname}${url.search}`;
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

function startRenameLogo(logo: BadgeForgeCustomLogoSummary): void {
    confirmingLogoId.value = null;
    editingLogoId.value = logo.id;
    renameLogoForm.name = logo.name;
    renameLogoForm.clearErrors();
}

function saveRenameLogo(logo: BadgeForgeCustomLogoSummary): void {
    renameLogoForm.patch(updateLogo.url({ badgeCustomLogo: logo.id }), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            editingLogoId.value = null;
        },
    });
}

function deleteLogo(logo: BadgeForgeCustomLogoSummary): void {
    router.delete(destroyLogo.url({ badgeCustomLogo: logo.id }), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            forgetCustomLogo(logo.slug);
            confirmingLogoId.value = null;

            if (previewId.value === logo.id) {
                previewId.value = null;
                previewLogo.value = null;
            }
        },
    });
}

function startRenamePreset(preset: BadgeForgePreset): void {
    confirmingPresetId.value = null;
    editingPresetId.value = preset.id;
    renamePresetForm.name = preset.name;
    renamePresetForm.clearErrors();
}

function saveRenamePreset(preset: BadgeForgePreset): void {
    renamePresetForm.patch(updatePreset.url({ badgePreset: preset.id }), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            editingPresetId.value = null;
        },
    });
}

function deletePreset(preset: BadgeForgePreset): void {
    router.delete(destroyPreset.url({ badgePreset: preset.id }), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            confirmingPresetId.value = null;
        },
    });
}
</script>

<template>
  <Head :title="t('account.badgeforge.title')" />

  <div class="space-y-8">
    <header class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <h2 class="text-lg font-medium">
          {{ t('account.badgeforge.title') }}
        </h2>
        <p class="mt-1 text-sm text-subtle">
          {{ t('account.badgeforge.description') }}
        </p>
      </div>

      <Button
        variant="outline"
        size="sm"
        as-child
        data-test="badgeforge-open-tool"
      >
        <Link :href="toolUrl">
          <ExternalLink class="size-4" />
          {{ t('account.badgeforge.open_tool') }}
        </Link>
      </Button>
    </header>

    <section
      class="rounded-lg border border-border"
      data-test="badgeforge-limits"
    >
      <h3 class="border-b border-border px-4 py-2 text-sm font-medium">
        {{ t('account.badgeforge.limits') }}
      </h3>

      <dl class="divide-y divide-border">
        <div class="flex items-center justify-between gap-4 px-4 py-2">
          <dt class="text-sm text-subtle">
            {{ t('account.badgeforge.limit_logos') }}
          </dt>
          <dd class="text-sm">
            {{
              logoLimits.limit === null
                ? t('account.badgeforge.unlimited')
                : t('account.badgeforge.used_of', {
                  used: logoLimits.used,
                  limit: logoLimits.limit,
                })
            }}
          </dd>
        </div>

        <div class="flex items-center justify-between gap-4 px-4 py-2">
          <dt class="text-sm text-subtle">
            {{ t('account.badgeforge.limit_presets') }}
          </dt>
          <dd class="text-sm">
            {{
              presetLimits.limit === null
                ? t('account.badgeforge.unlimited')
                : t('account.badgeforge.used_of', {
                  used: presetLimits.used,
                  limit: presetLimits.limit,
                })
            }}
          </dd>
        </div>
      </dl>

      <div
        v-if="!toolProps.management.isPaid"
        class="border-t border-border px-4 py-3"
      >
        <Button
          variant="outline"
          size="sm"
          as-child
          data-test="badgeforge-upgrade"
        >
          <Link :href="upgradeUrl">
            {{ t('account.badgeforge.upgrade') }}
          </Link>
        </Button>
      </div>
    </section>

    <section class="space-y-3">
      <h3 class="text-sm font-medium">
        {{ t('account.badgeforge.logos') }}
      </h3>

      <LogoUploadForm
        :max-bytes="logoLimits.maxBytes"
        :name-max-length="logoLimits.nameMaxLength"
        :limit="logoLimits.limit"
        :used="logoLimits.used"
        :upgrade-url="upgradeUrl"
      />

      <div
        v-if="logos.length === 0"
        class="rounded-lg border border-dashed px-4 py-8 text-center text-sm text-subtle"
        data-test="badgeforge-logos-empty"
      >
        {{ t('account.badgeforge.empty_logos') }}
      </div>

      <ul
        v-else
        class="divide-y rounded-lg border border-border"
        data-test="badgeforge-logos"
      >
        <li
          v-for="logo in logos"
          :key="logo.id"
          class="space-y-2 px-4 py-3"
        >
          <div class="flex flex-wrap items-center gap-2">
            <div class="min-w-0 flex-1">
              <template v-if="editingLogoId === logo.id">
                <Input
                  v-model="renameLogoForm.name"
                  :maxlength="logoLimits.nameMaxLength"
                  :aria-label="t('account.badgeforge.name')"
                  data-test="badgeforge-logo-rename-input"
                  @keyup.enter="saveRenameLogo(logo)"
                />
                <InputError :message="renameLogoForm.errors.name" />
              </template>
              <p
                v-else
                class="truncate text-sm font-medium"
              >
                {{ logo.name }}
              </p>
              <p class="text-xs text-subtle">
                {{
                  t('account.badgeforge.logo_paths', {
                    count: logo.pathCount,
                  })
                }}
              </p>
            </div>

            <template v-if="editingLogoId === logo.id">
              <Button
                size="sm"
                :disabled="renameLogoForm.processing"
                data-test="badgeforge-logo-rename-save"
                @click="saveRenameLogo(logo)"
              >
                {{ t('account.badgeforge.save') }}
              </Button>
              <Button
                variant="ghost"
                size="sm"
                data-test="badgeforge-logo-rename-cancel"
                @click="editingLogoId = null"
              >
                {{ t('account.badgeforge.cancel') }}
              </Button>
            </template>
            <template v-else>
              <Button
                variant="outline"
                size="sm"
                data-test="badgeforge-logo-preview-toggle"
                @click="togglePreview(logo)"
              >
                <Eye class="size-4" />
                {{ t('account.badgeforge.preview') }}
              </Button>
              <Button
                variant="ghost"
                size="sm"
                data-test="badgeforge-logo-rename"
                @click="startRenameLogo(logo)"
              >
                <Pencil class="size-4" />
                {{ t('account.badgeforge.rename') }}
              </Button>
              <Button
                variant="ghost"
                size="sm"
                data-test="badgeforge-logo-delete"
                @click="confirmingLogoId = logo.id"
              >
                <Trash2 class="size-4" />
                {{ t('account.badgeforge.delete') }}
              </Button>
            </template>
          </div>

          <div
            v-if="previewId === logo.id"
            class="flex min-h-10 items-center rounded-md bg-muted/50 px-3 py-2"
          >
            <p
              v-if="previewLoading"
              class="text-xs text-subtle"
            >
              {{ t('account.badgeforge.preview_loading') }}
            </p>
            <p
              v-else-if="previewFailed || previewLogo === null"
              class="text-xs text-destructive"
            >
              {{ t('account.badgeforge.preview_error') }}
            </p>
            <LogoPreview
              v-else
              :view-box="previewLogo.viewBox"
              :paths="previewLogo.paths"
              color="#000000"
            />
          </div>

          <div
            v-if="confirmingLogoId === logo.id"
            class="rounded-md border border-destructive/40 bg-destructive/5 px-3 py-2"
            data-test="badgeforge-logo-delete-confirm"
          >
            <p class="text-sm font-medium">
              {{
                t('account.badgeforge.delete_title', {
                  name: logo.name,
                })
              }}
            </p>
            <p class="text-xs text-subtle">
              {{ t('account.badgeforge.delete_description') }}
            </p>
            <div class="mt-2 flex gap-2">
              <Button
                variant="destructive"
                size="sm"
                data-test="badgeforge-logo-delete-confirmed"
                @click="deleteLogo(logo)"
              >
                {{ t('account.badgeforge.delete') }}
              </Button>
              <Button
                variant="outline"
                size="sm"
                data-test="badgeforge-logo-delete-cancel"
                @click="confirmingLogoId = null"
              >
                {{ t('account.badgeforge.cancel') }}
              </Button>
            </div>
          </div>
        </li>
      </ul>
    </section>

    <section class="space-y-3">
      <h3 class="text-sm font-medium">
        {{ t('account.badgeforge.presets') }}
      </h3>

      <div
        v-if="presets.length === 0"
        class="rounded-lg border border-dashed px-4 py-8 text-center text-sm text-subtle"
        data-test="badgeforge-presets-empty"
      >
        {{ t('account.badgeforge.empty_presets') }}
      </div>

      <ul
        v-else
        class="divide-y rounded-lg border border-border"
        data-test="badgeforge-presets"
      >
        <li
          v-for="preset in presets"
          :key="preset.id"
          class="space-y-2 px-4 py-3"
        >
          <div class="flex flex-wrap items-center gap-2">
            <div class="min-w-0 flex-1">
              <template v-if="editingPresetId === preset.id">
                <Input
                  v-model="renamePresetForm.name"
                  :maxlength="presetLimits.nameMaxLength"
                  :aria-label="t('account.badgeforge.name')"
                  data-test="badgeforge-preset-rename-input"
                  @keyup.enter="saveRenamePreset(preset)"
                />
                <InputError :message="renamePresetForm.errors.name" />
              </template>
              <div
                v-else
                class="flex flex-wrap items-center gap-2"
              >
                <p class="truncate text-sm font-medium">
                  {{ preset.name }}
                </p>
                <Badge
                  v-if="preset.logoOrphaned"
                  variant="outline"
                  class="border-amber-500/40 text-amber-600"
                  data-test="badgeforge-preset-orphaned"
                >
                  {{ t('account.badgeforge.orphaned') }}
                </Badge>
              </div>
            </div>

            <template v-if="editingPresetId === preset.id">
              <Button
                size="sm"
                :disabled="renamePresetForm.processing"
                data-test="badgeforge-preset-rename-save"
                @click="saveRenamePreset(preset)"
              >
                {{ t('account.badgeforge.save') }}
              </Button>
              <Button
                variant="ghost"
                size="sm"
                data-test="badgeforge-preset-rename-cancel"
                @click="editingPresetId = null"
              >
                {{ t('account.badgeforge.cancel') }}
              </Button>
            </template>
            <template v-else>
              <Button
                variant="outline"
                size="sm"
                as-child
                data-test="badgeforge-preset-open"
              >
                <Link :href="presetUrl(preset)">
                  <ExternalLink class="size-4" />
                  {{ t('account.badgeforge.open_preset') }}
                </Link>
              </Button>
              <Button
                variant="ghost"
                size="sm"
                data-test="badgeforge-preset-rename"
                @click="startRenamePreset(preset)"
              >
                <Pencil class="size-4" />
                {{ t('account.badgeforge.rename') }}
              </Button>
              <Button
                variant="ghost"
                size="sm"
                data-test="badgeforge-preset-delete"
                @click="confirmingPresetId = preset.id"
              >
                <Trash2 class="size-4" />
                {{ t('account.badgeforge.delete') }}
              </Button>
            </template>
          </div>

          <div
            v-if="confirmingPresetId === preset.id"
            class="rounded-md border border-destructive/40 bg-destructive/5 px-3 py-2"
            data-test="badgeforge-preset-delete-confirm"
          >
            <p class="text-sm font-medium">
              {{
                t('account.badgeforge.delete_title', {
                  name: preset.name,
                })
              }}
            </p>
            <p class="text-xs text-subtle">
              {{ t('account.badgeforge.delete_description') }}
            </p>
            <div class="mt-2 flex gap-2">
              <Button
                variant="destructive"
                size="sm"
                data-test="badgeforge-preset-delete-confirmed"
                @click="deletePreset(preset)"
              >
                {{ t('account.badgeforge.delete') }}
              </Button>
              <Button
                variant="outline"
                size="sm"
                data-test="badgeforge-preset-delete-cancel"
                @click="confirmingPresetId = null"
              >
                {{ t('account.badgeforge.cancel') }}
              </Button>
            </div>
          </div>
        </li>
      </ul>
    </section>
  </div>
</template>
