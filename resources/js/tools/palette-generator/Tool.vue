<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import SavePaletteDialog from '@/components/colors/SavePaletteDialog.vue';
import { Button } from '@/components/ui/button';
import type { PaletteSaveSource, WorkspaceSnapshot } from '@/lib/paletteSave';
import {
    clearPaletteDraft,
    readPaletteDraft,
    savePaletteDraft,
} from '@/lib/saveDraft';
import palettes from '@/routes/dashboard/colors/palettes';
import type { PaletteToolProps } from '@/types/colors';
import GeneratorWorkspace from './GeneratorWorkspace.vue';
import type { ImageLimits } from './lib/imageLimits';

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
}>();

const { t } = useI18n();
const page = usePage();

const workspace = ref<InstanceType<typeof GeneratorWorkspace> | null>(null);
const dialogOpen = ref(false);
const saveSource = ref<PaletteSaveSource | null>(null);

const authenticated = computed(() => page.props.auth?.user != null);
const canSave = computed(() => workspace.value?.isEmpty !== true);

/** A escala e a extraida viram o mesmo par de entrada do modal. */
function sourceOf(snapshot: WorkspaceSnapshot): PaletteSaveSource | null {
    if (snapshot.isEmpty) {
        return null;
    }

    if (snapshot.kind === 'extracted' && snapshot.extracted !== null) {
        return {
            kind: 'extracted',
            palette: snapshot.extracted,
            base: snapshot.base,
        };
    }

    return { kind: 'scale', scale: snapshot.scale };
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
    <div class="flex justify-end">
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
    />

    <SavePaletteDialog
      :open="dialogOpen"
      :source="saveSource"
      :palette="props.palette"
      @close="dialogOpen = false"
    />
  </div>
</template>
