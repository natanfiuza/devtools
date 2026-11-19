<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Save } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatHex } from '@/lib/color';
import systems from '@/routes/dashboard/colors/systems';
import type {
    LayoutSystemLimits,
    LayoutSystemQuota,
    LayoutSystemTag,
} from '@/types/layoutSystem';
import LayoutWorkspace from './LayoutWorkspace.vue';
import SaveLayoutSystemDialog from './components/SaveLayoutSystemDialog.vue';
import { useFrozenLayout } from './composables/useFrozenLayout';
import { useLayoutDraft } from './composables/useLayoutDraft';
import { useLayoutSystem } from './composables/useLayoutSystem';
import { useUnsavedGuard } from './composables/useUnsavedGuard';
import type { LayoutController } from './lib/controller';
import { regenerateState } from './lib/regenerate';
import {
    layoutStateFingerprint,
    strategyMessageKey,
    suggestLayoutSystemName,
    type LayoutMessageTranslator,
    type LayoutSystemDetails,
    type LayoutSystemSaveSource,
} from './lib/save';
import {
    buildLayoutState,
    hydrateState,
    type LayoutStateInputsV1,
    type LayoutSystemStateV1,
} from './lib/state';

/**
 * Painel do Layout Harmonizer: monta o controller, o rascunho e o salvamento
 * (F4, ADR-161/ADR-168/ADR-169).
 *
 * Escolhe o workspace pelo envelope recebido: versoes atuais montam o
 * `useLayoutSystem` com `initial` (hidrata sem regenerar); versoes anteriores
 * ou mais novas montam o `useFrozenLayout`. O rascunho so e gravado para
 * anonimo com quadro valido; o botao de salvar vira o intent de login para
 * anonimo e abre o modal para autenticado.
 */

const props = withDefaults(
    defineProps<{
        /** Envelope salvo/importado; `null` no tool anonimo. */
        state?: LayoutSystemStateV1 | null;
        /** CTA da paleta: seed inicial quando nao ha envelope. */
        seedText?: string | null;
        authenticated: boolean;
        limits: LayoutSystemLimits;
        quota?: LayoutSystemQuota | null;
        accountTags?: LayoutSystemTag[];
        originPaletteId?: string | null;
        originName?: string | null;
        saveMode?: 'create' | 'update';
        systemId?: string | null;
        initialDetails?: LayoutSystemDetails | null;
        /** Guarda de navegacao: tool autenticado e editor. */
        guard?: boolean;
        /** O editor tem o proprio botao de salvar no cabecalho. */
        showSaveButton?: boolean;
        /** Alteracoes fora do quadro (metadados do editor) tambem contam. */
        extraDirty?: (() => boolean) | null;
    }>(),
    {
        state: null,
        seedText: null,
        quota: null,
        accountTags: () => [],
        originPaletteId: null,
        originName: null,
        saveMode: 'create',
        systemId: null,
        initialDetails: null,
        guard: false,
        showSaveButton: true,
        extraDirty: null,
    },
);

const emit = defineEmits<{
    regenerated: [state: LayoutSystemStateV1];
}>();

const { t } = useI18n();

function createController(): LayoutController {
    if (props.state === null) {
        return useLayoutSystem({ seedText: props.seedText ?? undefined });
    }

    const hydrated = hydrateState(props.state);

    if (hydrated.mode === 'live') {
        return useLayoutSystem({
            initial: { state: props.state, mode: 'live' },
        });
    }

    return useFrozenLayout(
        { snapshot: hydrated.snapshot, inputs: hydrated.inputs },
        { mode: hydrated.mode === 'readonly' ? 'readonly' : 'compatible' },
    );
}

const controller = createController();

function inputsOf(): LayoutStateInputsV1 {
    return {
        seedText: controller.picker.text.value,
        backgroundText: controller.backgroundText.value,
        secondaryText: controller.secondaryText.value,
        strategy: controller.strategy.value,
    };
}

/** Envelope do instante atual; `null` enquanto nao ha quadro. */
function currentState(): LayoutSystemStateV1 | null {
    const snapshot = controller.snapshot.value;

    if (snapshot === null) {
        return null;
    }

    return buildLayoutState(
        inputsOf(),
        controller.secondaryColor.value,
        snapshot,
    );
}

const currentFingerprint = computed(() =>
    layoutStateFingerprint(currentState()),
);

const saveSource = computed<LayoutSystemSaveSource | null>(() => {
    const snapshot = controller.snapshot.value;

    if (snapshot === null) {
        return null;
    }

    return {
        inputs: inputsOf(),
        secondary: controller.secondaryColor.value,
        snapshot,
    };
});

const draft = useLayoutDraft({
    snapshot: controller.snapshot,
    enabled: computed(
        () =>
            !props.authenticated &&
            controller.mode === 'live' &&
            controller.snapshot.value !== null &&
            controller.notice.value === null,
    ),
    state: currentState,
    originPaletteId: () => props.originPaletteId,
});

const quotaBlocked = computed(
    () =>
        props.saveMode === 'create' &&
        props.quota !== null &&
        props.quota.limit !== null &&
        props.quota.remaining !== null &&
        props.quota.remaining <= 0,
);

const canSave = computed(
    () =>
        controller.snapshot.value !== null &&
        controller.mode !== 'readonly' &&
        controller.notice.value === null &&
        !controller.pendingRegeneration.value &&
        !quotaBlocked.value,
);

const saveTitle = computed<string | undefined>(() => {
    if (!quotaBlocked.value) {
        return undefined;
    }

    const limit = props.quota?.limit;

    return limit === null || limit === undefined
        ? undefined
        : t('tools.layout-harmonizer.save.limit_reached', { limit });
});

const saveOpen = ref(false);

const baseline = ref<string>(
    props.state === null ? '' : layoutStateFingerprint(props.state),
);

const guard = useUnsavedGuard({
    dirty: () =>
        (baseline.value !== '' &&
            currentFingerprint.value !== '' &&
            currentFingerprint.value !== baseline.value) ||
        props.extraDirty?.() === true,
    enabled: () => props.guard,
});

// No tool a primeira geracao (seed da origem, por exemplo) e a baseline: sair
// logo depois de abrir nao pede confirmacao por um trabalho que nao existiu.
watch(currentFingerprint, (value) => {
    if (baseline.value === '' && value !== '') {
        baseline.value = value;
    }
});

// O PATCH do editor devolve o mesmo componente com props novas: a baseline
// acompanha o que o servidor gravou.
watch(
    () => props.state,
    (value) => {
        if (value !== null) {
            baseline.value = layoutStateFingerprint(value);
        }
    },
);

function resetBaseline(): void {
    baseline.value = currentFingerprint.value;
}

function openSave(): void {
    if (!canSave.value) {
        return;
    }

    saveOpen.value = true;
}

/** Salvar do painel: anonimo atravessa o login, autenticado abre o modal. */
function save(): void {
    if (!props.authenticated) {
        draft.saveNow();

        router.get(
            systems.create.url({
                query: {
                    save: '1',
                    ...(props.originPaletteId === null
                        ? {}
                        : { palette: props.originPaletteId }),
                },
            }),
        );

        return;
    }

    openSave();
}

function releaseGuard(): void {
    guard.release();
}

function onSaved(): void {
    resetBaseline();
}

const translator: LayoutMessageTranslator = (key, named) => t(key, named ?? {});

const suggestedName = computed(() => {
    const initial = props.initialDetails?.name;

    if (initial !== undefined && initial.trim() !== '') {
        return initial;
    }

    const seed = controller.seed.value;

    return suggestLayoutSystemName({
        originName: props.originName,
        strategyLabel: t(strategyMessageKey(controller.strategy.value)),
        hex: seed === null ? '' : formatHex(seed.effective),
        t: translator,
        maxLength: props.limits.nameMaxLength,
    });
});

function regenerate(): void {
    if (props.state === null) {
        return;
    }

    const next = regenerateState(props.state, inputsOf());

    if (next !== null) {
        emit('regenerated', next);
    }
}

defineExpose({
    openSave,
    currentState,
    resetBaseline,
    releaseGuard,
});
</script>

<template>
    <div class="space-y-4" data-test="layout-harmonizer-panel">
        <div
            v-if="showSaveButton"
            class="flex flex-wrap items-center justify-end gap-2"
        >
            <p
                v-if="quotaBlocked"
                class="text-xs text-destructive"
                data-test="layout-save-limit"
            >
                {{ saveTitle }}
            </p>
            <Button
                data-test="layout-save-open"
                :disabled="!canSave"
                :title="saveTitle"
                @click="save"
            >
                <Save class="size-4" />
                {{
                    authenticated
                        ? t('tools.layout-harmonizer.save.save_button')
                        : t('tools.layout-harmonizer.save.login_to_save')
                }}
            </Button>
        </div>

        <LayoutWorkspace
            :controller="controller"
            :regenerate="controller.mode === 'live' ? null : regenerate"
        />

        <SaveLayoutSystemDialog
            v-if="authenticated"
            :open="saveOpen"
            :mode="saveMode"
            :system-id="systemId"
            :source="saveSource"
            :suggested-name="suggestedName"
            :initial="initialDetails"
            :limits="limits"
            :account-tags="accountTags"
            :origin-palette-id="saveMode === 'create' ? originPaletteId : null"
            :before-save="releaseGuard"
            @close="saveOpen = false"
            @saved="onSaved"
        />

        <Dialog :open="guard.pending.value">
            <DialogContent class="sm:max-w-md" data-test="layout-guard-dialog">
                <DialogHeader>
                    <DialogTitle>
                        {{ t('tools.layout-harmonizer.guard.title') }}
                    </DialogTitle>
                    <DialogDescription>
                        {{ t('tools.layout-harmonizer.guard.description') }}
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        data-test="layout-guard-cancel"
                        @click="guard.cancel()"
                    >
                        {{ t('tools.layout-harmonizer.guard.stay') }}
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        size="sm"
                        data-test="layout-guard-confirm"
                        @click="guard.confirm()"
                    >
                        {{ t('tools.layout-harmonizer.guard.leave') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
