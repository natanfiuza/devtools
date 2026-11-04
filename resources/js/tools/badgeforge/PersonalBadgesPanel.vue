<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import {
    Copy,
    Pencil,
    RefreshCw,
    Trash2,
} from '@lucide/vue';
import { reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    credential as credentialRoute,
    destroy as destroyBadge,
    refresh as refreshBadge,
    update as updateBadge,
} from '@/routes/badgeforge/badges';
import { useCopyFeedback } from '@/composables/useCopyFeedback';
import type {
    BadgeForgePersonalBadge,
    BadgeForgeToolProps,
} from './types';

/**
 * Lista das badges pessoais (F4, ADR-081).
 *
 * O dono copia a URL publica, renomeia, troca o PAT e exclui. Atualizar a
 * imagem de um provider e um POST autenticado; a nova renderizacao aparece com
 * um `?v=` local (a URL publica nunca ganha `?refresh=1`). Depois de um
 * downgrade a lista continua disponivel para copiar e excluir, mas as edicoes
 * ficam bloqueadas - free nao cria, renomeia nem atualiza.
 */

const props = defineProps<{
    toolProps: BadgeForgeToolProps;
    upgradeUrl: string;
}>();

const { t, te } = useI18n();

const { message: copyMessage, copy } = useCopyFeedback();

const renameForm = useForm({ name: '' });
const credentialForm = useForm({ credential: '', credential_username: '' });

const editingId = ref<string | null>(null);
const credentialId = ref<string | null>(null);
const confirmingId = ref<string | null>(null);
const refreshingId = ref<string | null>(null);
const refreshFailedId = ref<string | null>(null);

/** Cache-buster local: `?v=` so vale na tela, nunca no snippet copiado. */
const versions = reactive<Record<string, number>>({});

const canEdit = (): boolean =>
    props.toolProps.management.canCreatePersonalBadges;

const badges = (): BadgeForgePersonalBadge[] => props.toolProps.personalBadges;

function displayUrl(badge: BadgeForgePersonalBadge, png = false): string {
    const base = png ? badge.pngUrl : badge.url;

    if (base === null) {
        return '';
    }

    const version = versions[badge.id];

    return version === undefined ? base : `${base}?v=${version}`;
}

/** Nome traduzido da metrica; o slug e so fallback. */
function metricName(badge: BadgeForgePersonalBadge): string {
    if (badge.providerSlug === null || badge.definitionSlug === null) {
        return '';
    }

    const key = `tools.badgeforge.provider.metrics.${badge.providerSlug}_${badge.definitionSlug}`;

    return te(key) ? t(key) : `${badge.providerSlug}/${badge.definitionSlug}`;
}

function credentialStatus(badge: BadgeForgePersonalBadge): string {
    if (!badge.private) {
        return t('tools.badgeforge.personal.credential_not_required');
    }

    return badge.credentialSet
        ? t('tools.badgeforge.personal.credential_set')
        : t('tools.badgeforge.personal.credential_missing');
}

function startRename(badge: BadgeForgePersonalBadge): void {
    credentialId.value = null;
    confirmingId.value = null;
    editingId.value = badge.id;
    renameForm.name = badge.name;
    renameForm.clearErrors();
}

function saveRename(badge: BadgeForgePersonalBadge): void {
    renameForm.patch(updateBadge.url({ badgeAsset: badge.id }), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            editingId.value = null;
        },
    });
}

function startCredential(badge: BadgeForgePersonalBadge): void {
    editingId.value = null;
    confirmingId.value = null;
    credentialId.value = badge.id;
    credentialForm.reset();
    credentialForm.clearErrors();
}

function saveCredential(badge: BadgeForgePersonalBadge): void {
    credentialForm
        .transform(() => ({
            credential: credentialForm.credential,
            credential_username:
                badge.credentialUsername !== null ||
                needsUsername(badge)
                    ? credentialForm.credential_username
                    : null,
        }))
        .patch(credentialRoute.url({ badgeAsset: badge.id }), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                credentialId.value = null;
                credentialForm.reset();
            },
        });
}

function cancelCredential(): void {
    credentialId.value = null;
    credentialForm.reset();
    credentialForm.clearErrors();
}

/** Docker `basic`: trocar o PAT exige repetir o username (non-secret). */
function needsUsername(badge: BadgeForgePersonalBadge): boolean {
    const provider = props.toolProps.providers.find(
        (item) => item.slug === badge.providerSlug,
    );

    return provider?.requiresUsername === true;
}

function startDelete(badge: BadgeForgePersonalBadge): void {
    editingId.value = null;
    credentialId.value = null;
    confirmingId.value = badge.id;
}

function destroy(badge: BadgeForgePersonalBadge): void {
    router.delete(destroyBadge.url({ badgeAsset: badge.id }), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            confirmingId.value = null;
        },
    });
}

async function refresh(badge: BadgeForgePersonalBadge): Promise<void> {
    if (refreshingId.value !== null) {
        return;
    }

    refreshingId.value = badge.id;
    refreshFailedId.value = null;

    try {
        const response = await fetch(refreshBadge.url({ badgeAsset: badge.id }), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            refreshFailedId.value = badge.id;

            return;
        }

        versions[badge.id] = Date.now();
    } catch {
        refreshFailedId.value = badge.id;
    } finally {
        refreshingId.value = null;
    }
}
</script>

<template>
  <section
    class="space-y-3"
    data-test="badgeforge-personal"
  >
    <div class="flex flex-wrap items-center justify-between gap-2">
      <h3 class="text-sm font-medium">
        {{ t('tools.badgeforge.personal.title') }}
      </h3>

      <span
        v-if="copyMessage"
        class="text-xs text-muted-foreground"
        data-test="badgeforge-personal-copy-feedback"
      >
        {{ copyMessage }}
      </span>
    </div>

    <p class="text-xs text-subtle">
      {{ t('tools.badgeforge.personal.description') }}
    </p>

    <div
      v-if="!canEdit()"
      class="rounded-md border border-dashed px-4 py-3 text-sm"
      data-test="badgeforge-personal-upgrade"
    >
      <p class="text-subtle">
        {{ t('tools.badgeforge.personal.readonly_note') }}
      </p>
      <Button
        as-child
        variant="outline"
        size="sm"
        class="mt-2"
        data-test="badgeforge-personal-upgrade-link"
      >
        <a :href="upgradeUrl">
          {{ t('tools.badgeforge.personal.upgrade') }}
        </a>
      </Button>
    </div>

    <div
      v-if="badges().length === 0"
      class="rounded-lg border border-dashed px-4 py-8 text-center text-sm text-subtle"
      data-test="badgeforge-personal-empty"
    >
      {{ t('tools.badgeforge.personal.empty') }}
    </div>

    <ul
      v-else
      class="divide-y rounded-lg border border-border"
      data-test="badgeforge-personal-list"
    >
      <li
        v-for="badge in badges()"
        :key="badge.id"
        class="space-y-2 px-4 py-3"
        data-test="badgeforge-personal-row"
      >
        <div class="flex flex-wrap items-center gap-2">
          <img
            v-if="displayUrl(badge) !== ''"
            :src="displayUrl(badge)"
            :alt="badge.name"
            class="h-5 max-w-48"
            data-test="badgeforge-personal-preview"
          >

          <div class="min-w-0 flex-1">
            <template v-if="editingId === badge.id">
              <Input
                v-model="renameForm.name"
                :maxlength="toolProps.limits.personalBadges.nameMaxLength"
                :aria-label="t('tools.badgeforge.personal.name')"
                data-test="badgeforge-personal-rename-input"
                @keyup.enter="saveRename(badge)"
              />
              <InputError :message="renameForm.errors.name" />
            </template>
            <div
              v-else
              class="flex flex-wrap items-center gap-2"
            >
              <p class="truncate text-sm font-medium">
                {{ badge.name }}
              </p>
              <Badge
                variant="outline"
                data-test="badgeforge-personal-type"
              >
                {{
                  badge.type === 'provider'
                    ? metricName(badge)
                    : t('tools.badgeforge.modes.static')
                }}
              </Badge>
              <Badge
                v-if="badge.private"
                variant="outline"
                data-test="badgeforge-personal-private"
              >
                {{ t('tools.badgeforge.personal.private') }}
              </Badge>
            </div>
            <p
              class="text-xs text-subtle"
              data-test="badgeforge-personal-credential-status"
            >
              {{ credentialStatus(badge) }}
            </p>
          </div>

          <template v-if="editingId === badge.id">
            <Button
              size="sm"
              :disabled="renameForm.processing"
              data-test="badgeforge-personal-rename-save"
              @click="saveRename(badge)"
            >
              {{ t('tools.badgeforge.management.save') }}
            </Button>
            <Button
              variant="ghost"
              size="sm"
              data-test="badgeforge-personal-rename-cancel"
              @click="editingId = null"
            >
              {{ t('tools.badgeforge.management.cancel') }}
            </Button>
          </template>
          <template v-else>
            <Button
              variant="outline"
              size="sm"
              :disabled="badge.url === null"
              data-test="badgeforge-personal-copy-svg"
              @click="copy(displayUrl(badge))"
            >
              <Copy class="size-4" />
              {{ t('tools.badgeforge.personal.copy_svg') }}
            </Button>
            <Button
              variant="outline"
              size="sm"
              :disabled="badge.pngUrl === null"
              data-test="badgeforge-personal-copy-png"
              @click="copy(displayUrl(badge, true))"
            >
              <Copy class="size-4" />
              {{ t('tools.badgeforge.personal.copy_png') }}
            </Button>

            <template v-if="canEdit()">
              <Button
                v-if="badge.type === 'provider'"
                variant="ghost"
                size="sm"
                :disabled="refreshingId === badge.id"
                data-test="badgeforge-personal-refresh"
                @click="refresh(badge)"
              >
                <RefreshCw class="size-4" />
                {{
                  refreshingId === badge.id
                    ? t('tools.badgeforge.personal.refreshing')
                    : t('tools.badgeforge.personal.refresh')
                }}
              </Button>
              <Button
                variant="ghost"
                size="sm"
                data-test="badgeforge-personal-rename"
                @click="startRename(badge)"
              >
                <Pencil class="size-4" />
                {{ t('tools.badgeforge.management.rename') }}
              </Button>
              <Button
                v-if="badge.private"
                variant="ghost"
                size="sm"
                data-test="badgeforge-personal-credential"
                @click="startCredential(badge)"
              >
                {{ t('tools.badgeforge.personal.change_credential') }}
              </Button>
            </template>

            <Button
              variant="ghost"
              size="sm"
              data-test="badgeforge-personal-delete"
              @click="startDelete(badge)"
            >
              <Trash2 class="size-4" />
              {{ t('tools.badgeforge.management.delete') }}
            </Button>
          </template>
        </div>

        <p
          v-if="refreshFailedId === badge.id"
          class="text-xs text-destructive"
          data-test="badgeforge-personal-refresh-error"
        >
          {{ t('tools.badgeforge.personal.refresh_failed') }}
        </p>

        <div
          v-if="credentialId === badge.id"
          class="space-y-2 rounded-md border p-3"
          data-test="badgeforge-personal-credential-form"
        >
          <Input
            v-model="credentialForm.credential"
            type="password"
            autocomplete="new-password"
            :placeholder="
              t('tools.badgeforge.personal.credential_placeholder')
            "
            :aria-label="t('tools.badgeforge.personal.credential')"
            data-test="badgeforge-personal-credential-input"
          />
          <InputError :message="credentialForm.errors.credential" />

          <Input
            v-if="needsUsername(badge)"
            v-model="credentialForm.credential_username"
            autocomplete="off"
            :placeholder="
              t('tools.badgeforge.personal.credential_username')
            "
            :aria-label="
              t('tools.badgeforge.personal.credential_username')
            "
            data-test="badgeforge-personal-credential-username"
          />
          <InputError :message="credentialForm.errors.credential_username" />

          <div class="flex gap-2">
            <Button
              size="sm"
              :disabled="credentialForm.processing"
              data-test="badgeforge-personal-credential-save"
              @click="saveCredential(badge)"
            >
              {{ t('tools.badgeforge.management.save') }}
            </Button>
            <Button
              variant="outline"
              size="sm"
              data-test="badgeforge-personal-credential-cancel"
              @click="cancelCredential"
            >
              {{ t('tools.badgeforge.management.cancel') }}
            </Button>
          </div>
        </div>

        <div
          v-if="confirmingId === badge.id"
          class="rounded-md border border-destructive/40 bg-destructive/5 px-3 py-2"
          data-test="badgeforge-personal-delete-confirm"
        >
          <p class="text-sm font-medium">
            {{
              t('tools.badgeforge.management.delete_title', {
                name: badge.name,
              })
            }}
          </p>
          <p class="text-xs text-subtle">
            {{ t('tools.badgeforge.management.delete_description') }}
          </p>
          <div class="mt-2 flex gap-2">
            <Button
              variant="destructive"
              size="sm"
              data-test="badgeforge-personal-delete-confirmed"
              @click="destroy(badge)"
            >
              {{ t('tools.badgeforge.management.delete') }}
            </Button>
            <Button
              variant="outline"
              size="sm"
              data-test="badgeforge-personal-delete-cancel"
              @click="confirmingId = null"
            >
              {{ t('tools.badgeforge.management.cancel') }}
            </Button>
          </div>
        </div>
      </li>
    </ul>
  </section>
</template>
