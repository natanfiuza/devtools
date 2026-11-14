<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import PresetDialog from '@/components/colors/PresetDialog.vue';
import SavePaletteDialog from '@/components/colors/SavePaletteDialog.vue';
import TokenImportDialog from '@/components/colors/TokenImportDialog.vue';
import { Button } from '@/components/ui/button';
import type { PaletteSaveSource, WorkspaceSnapshot } from '@/lib/paletteSave';
import {
    clearPaletteDraft,
    readPaletteDraft,
    savePaletteDraft,
} from '@/lib/saveDraft';
import palettes from '@/routes/dashboard/colors/palettes';
import type {
    ColorLibraryToolProps,
    PaletteToolProps,
    TokenImportLimits,
} from '@/types/colors';
import GeneratorWorkspace from './GeneratorWorkspace.vue';
import type { ImageLimits } from './lib/imageLimits';
import type { ImportedPalette } from './lib/tokenImport';

/**
 * Color Palette Generator (`/{locale}/tools/colors/palette-generator`).
 *
 * O corpo (picker, escala, harmonias, extracao) vive no `GeneratorWorkspace`,
 * porque o editor da F4 reusa o mesmo componente. Aqui fica so o que e desta
 * pagina: o botao "Salvar", o modal e o fluxo de quem ainda nao tem conta.
 *
 * Visitante que clica em "Salvar" leva a base/tipo/extracao para o
 * `sessionStorage`, passa pelo login e volta ao gerador com `?save=1`; ao
 * abrir, o rascunho e restaurado e o modal aparece. Autenticado abre o modal
 * direto. Nenhuma cor sai do navegador ate o salvamento (que e uma acao de
 * conta, nao da tool).
 */

const props = defineProps<{
    /** Tetos da importacao de imagem, entregues pelo servidor (ADR-122). */
    image: ImageLimits;
    /** Limites do modal e tags da conta, entregues pelo servidor (ADR-123). */
    palette: PaletteToolProps;
    /** Biblioteca de cores da conta ou local do anonimo (F5). */
    library?: ColorLibraryToolProps;
    /** Tetos da importacao validada de tokens (F5, Etapa 6). */
    tokens: TokenImportLimits;
}>();

const { t } = useI18n();
const page = usePage();

const workspace = ref<InstanceType<typeof GeneratorWorkspace> | null>(null);
const dialogOpen = ref(false);
const saveSource = ref<PaletteSaveSource | null>(null);
const presetsOpen = ref(false);
const presetBase = ref('');
const importOpen = ref(false);

const authenticated = computed(() => page.props.auth?.user != null);
const canSave = computed(() => workspace.value?.isEmpty !== true);

/**
 * A escala e a extraida viram o mesmo par de entrada do modal. Os papeis so
 * viajam quando vieram de uma importacao de tokens e continuam valendo para o
 * snapshot atual; o workspace ja cuidou dessa validade na assinatura.
 */
function sourceOf(snapshot: WorkspaceSnapshot): PaletteSaveSource | null {
    if (snapshot.isEmpty) {
        return null;
    }

    const roles = workspace.value?.roles ?? null;

    if (snapshot.kind === 'extracted' && snapshot.extracted !== null) {
        return {
            kind: 'extracted',
            palette: snapshot.extracted,
            base: snapshot.base,
            roles,
        };
    }

    return { kind: 'scale', scale: snapshot.scale, roles };
}

function requestSave(): void {
    const current = workspace.value;

    if (current === null) {
        return;
    }

    const source = sourceOf(current.snapshot());

    if (source === null) {
        return;
    }

    if (authenticated.value) {
        saveSource.value = source;
        dialogOpen.value = true;

        return;
    }

    savePaletteDraft(current.draft());
    router.visit(palettes.create.url());
}

/**
 * Importacao ja validou e o usuario confirmou: so agora o estado da tela muda.
 * A base importada passa pelo `useBase` do workspace e entra no historico como
 * qualquer troca.
 */
function applyTokens(palette: ImportedPalette): void {
    const current = workspace.value;

    if (current === null) {
        return;
    }

    current.applyImported(palette);
    current.notify(t('tools.shared.token_import.applied'));
}

/** O dialogo de presets sempre parte da base que esta no gerador. */
function openPresets(): void {
    const current = workspace.value;

    if (current === null) {
        return;
    }

    presetBase.value = current.baseHex();
    presetsOpen.value = true;
}

/**
 * Aplicar repoe a base; a escala volta a ser derivada e o historico registra
 * a troca. O dialogo fecha sozinho ao emitir.
 */
function applyPreset(base: string): void {
    const current = workspace.value;

    if (current === null || !current.applyBaseHex(base)) {
        return;
    }

    current.notify(t('colors.presets.applied'));
}

/** `?save=1` e a volta do intent de criacao, depois do login. */
function wantsSave(): boolean {
    const query = page.url.includes('?')
        ? page.url.slice(page.url.indexOf('?') + 1)
        : '';

    return new URLSearchParams(query).get('save') === '1';
}

onMounted(() => {
    if (!wantsSave() || !authenticated.value || workspace.value === null) {
        return;
    }

    const draft = readPaletteDraft();

    if (draft === null) {
        return;
    }

    workspace.value.restoreDraft(draft);
    clearPaletteDraft();

    const snapshot = workspace.value.snapshot();
    saveSource.value = sourceOf(snapshot);

    if (saveSource.value !== null) {
        dialogOpen.value = true;
        workspace.value.notify(
            t('tools.palette-generator.save.draft_restored'),
        );
    }
});
</script>

<template>
  <div class="flex flex-col gap-4">
    <div class="flex flex-wrap justify-end gap-2">
      <Button
        v-if="authenticated"
        type="button"
        size="sm"
        variant="outline"
        data-test="palette-presets"
        @click="openPresets"
      >
        {{ t('colors.presets.open') }}
      </Button>
      <Button
        type="button"
        size="sm"
        variant="outline"
        data-test="palette-import"
        @click="importOpen = true"
      >
        {{ t('tools.shared.token_import.button') }}
      </Button>
      <Button
        type="button"
        size="sm"
        data-test="palette-save"
        :disabled="!canSave"
        @click="requestSave"
      >
        {{ t('tools.palette-generator.save.action') }}
      </Button>
    </div>

    <GeneratorWorkspace
      ref="workspace"
      :image="props.image"
      :library="props.library"
    />

    <SavePaletteDialog
      :open="dialogOpen"
      :source="saveSource"
      :palette="props.palette"
      @close="dialogOpen = false"
    />

    <PresetDialog
      v-if="authenticated && presetsOpen"
      :open="presetsOpen"
      :base="presetBase"
      :has-base="canSave"
      @close="presetsOpen = false"
      @apply="applyPreset"
    />

    <TokenImportDialog
      :open="importOpen"
      :tokens="props.tokens"
      :source-label-max-length="props.palette.sourceLabelMaxLength"
      @apply="applyTokens"
      @close="importOpen = false"
    />
  </div>
</template>
