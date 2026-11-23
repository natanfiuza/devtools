<script setup lang="ts">
import { nextTick, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import LayoutHarmonizerPanel from './LayoutHarmonizerPanel.vue';
import ImportDraftDialog from './components/ImportDraftDialog.vue';
import LayoutSystemOriginBanner from './components/LayoutSystemOriginBanner.vue';
import {
    clearLayoutDraft,
    markImported,
    readLayoutDraft,
    type LayoutDraft,
} from './lib/draft';
import type { LayoutSystemStateV1 } from './lib/state';
import type {
    LayoutSystemExportLimits,
    LayoutSystemExportMessages,
    LayoutSystemLimits,
    LayoutSystemQuota,
    LayoutSystemTag,
    LayoutSystemToolOrigin,
} from '@/types/layoutSystem';
import { DEFAULT_LAYOUT_EXPORT_LIMITS } from './export/options';

/**
 * Corpo da ferramenta Layout Harmonizer (F4, ADR-161/ADR-168).
 *
 * O servidor entrega cota, limites, tags e a origem opcional da paleta; a
 * geracao continua 100% no navegador. Anonimo com rascunho reabre direto;
 * autenticado com rascunho pendente decide importar ou descartar antes de
 * salvar. O `?save=1` do intent retoma o salvamento depois do login.
 */

const props = withDefaults(
    defineProps<{
        authenticated?: boolean;
        limits?: LayoutSystemLimits;
        quota?: LayoutSystemQuota | null;
        tags?: LayoutSystemTag[];
        origin?: LayoutSystemToolOrigin | null;
        currentVersions?: Record<string, number>;
        exportMessages?: LayoutSystemExportMessages;
        exportLimits?: LayoutSystemExportLimits;
    }>(),
    {
        authenticated: false,
        limits: () => ({
            nameMaxLength: 80,
            descriptionMaxLength: 500,
            tagsPerSystem: 10,
            tagNameMaxLength: 30,
        }),
        quota: null,
        tags: () => [],
        origin: null,
        currentVersions: () => ({}),
        exportMessages: () => ({}),
        exportLimits: () => DEFAULT_LAYOUT_EXPORT_LIMITS,
    },
);

const { t } = useI18n();

function saveRequested(): boolean {
    if (typeof window === 'undefined') {
        return false;
    }

    return new URL(window.location.href).searchParams.get('save') === '1';
}

const saveIntent = saveRequested();

const storedDraft = ref<LayoutDraft | null>(readLayoutDraft());

const originRemoved = ref(false);

const originId = ref<string | null>(
    props.origin?.id ?? storedDraft.value?.originPaletteId ?? null,
);

const initialState =
    !props.authenticated &&
    storedDraft.value !== null &&
    storedDraft.value.supported &&
    storedDraft.value.status !== 'refused'
        ? storedDraft.value.state
        : null;

const state = ref<LayoutSystemStateV1 | null>(initialState);

const draftOpen = ref(
    props.authenticated &&
        storedDraft.value !== null &&
        storedDraft.value.status === 'pending',
);

const panelKey = ref(0);
const panel = ref<InstanceType<typeof LayoutHarmonizerPanel> | null>(null);

/** A seed da origem so vale quando nao ha trabalho para importar. */
function seedText(): string | null {
    if (props.origin === null || originRemoved.value || draftOpen.value) {
        return null;
    }

    return state.value === null ? props.origin.seedHex : null;
}

async function maybeOpenSave(): Promise<void> {
    if (!saveIntent || !props.authenticated) {
        return;
    }

    await nextTick();
    panel.value?.openSave();
}

async function importDraft(): Promise<void> {
    const draft = storedDraft.value;

    draftOpen.value = false;

    if (draft === null || draft.state === null) {
        return;
    }

    markImported();
    state.value = draft.state;
    panelKey.value += 1;

    await maybeOpenSave();
}

async function discardDraft(): Promise<void> {
    draftOpen.value = false;
    storedDraft.value = null;

    clearLayoutDraft();

    await maybeOpenSave();
}

/** Remover a origem limpa a query e o id: salvar sem credito inventado. */
function removeOrigin(): void {
    originRemoved.value = true;
    originId.value = null;

    if (typeof window === 'undefined') {
        return;
    }

    const url = new URL(window.location.href);
    url.searchParams.delete('palette');
    window.history.replaceState(window.history.state, '', url.toString());
}

function onRegenerated(next: LayoutSystemStateV1): void {
    state.value = next;
    panelKey.value += 1;
}

onMounted(() => {
    if (!draftOpen.value) {
        void maybeOpenSave();
    }
});
</script>

<template>
  <div
    class="space-y-4"
    data-test="layout-harmonizer-tool"
  >
    <p class="text-sm text-muted-foreground">
      {{ t('tools.layout-harmonizer.intro') }}
    </p>

    <LayoutSystemOriginBanner
      v-if="origin !== null && !originRemoved"
      :origin="origin"
      @remove="removeOrigin"
    />

    <LayoutHarmonizerPanel
      ref="panel"
      :key="panelKey"
      :state="state"
      :seed-text="seedText()"
      :authenticated="authenticated"
      :limits="limits"
      :quota="quota"
      :account-tags="tags"
      :origin-palette-id="originRemoved ? null : originId"
      :origin-name="originRemoved ? null : (origin?.name ?? null)"
      :export-messages="exportMessages"
      :export-limits="exportLimits"
      :export-origin="originRemoved ? null : origin"
      :guard="authenticated"
      @regenerated="onRegenerated"
    />

    <ImportDraftDialog
      v-if="authenticated"
      :open="draftOpen"
      :supported="storedDraft?.supported ?? false"
      @import="importDraft"
      @discard="discardDraft"
    />
  </div>
</template>
