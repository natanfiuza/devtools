import { computed, ref, shallowRef, watch } from 'vue';
import type { Rgba } from '@/lib/color';
import { useColorState } from '@/composables/useColorState';
import type { LayoutStrategy, LayoutTheme, LayoutTokenId } from '../engine';
import type { LayoutEditState, LayoutEditTarget } from '../lib/editState';
import type {
    FrozenLayoutController,
    LayoutEditNotice,
    LayoutNotice,
} from '../lib/controller';
import {
    applyFrozenColor,
    frozenResult,
    isFrozenLocked,
    toggleFrozenLock,
} from '../lib/frozen';
import {
    LAYOUT_PLACEHOLDER,
    parseBackgroundText,
    parseSecondaryText,
} from '../lib/inputs';
import type { LayoutSnapshot } from '../lib/snapshot';
import {
    editStateFromSnapshot,
    type LayoutStateInputsV1,
    type LayoutViewResult,
} from '../lib/state';

/**
 * Workspace de um sistema salvo em versoes anteriores (F4, ADR-169).
 *
 * O snapshot gravado e exibido como veio; editar token, bloquear e salvar
 * continuam possiveis, mas restaurar e sugerir ficam desligados (sem valor
 * automatico anterior e com o algoritmo de sugestoes diferente). Trocar
 * seed/estrategia/secundaria nao mexe no quadro: marca `pendingRegeneration`
 * e a decisao de regenerar fica com o editor.
 */

export interface FrozenLayoutInit {
    snapshot: LayoutSnapshot;
    inputs: LayoutStateInputsV1;
}

export interface FrozenLayoutOptions {
    /** `readonly` quando o shape/versao nao e conhecido do editor. */
    mode?: 'compatible' | 'readonly';
}

export function useFrozenLayout(
    init: FrozenLayoutInit,
    options: FrozenLayoutOptions = {},
): FrozenLayoutController {
    const mode = options.mode ?? 'compatible';
    const picker = useColorState({ initial: LAYOUT_PLACEHOLDER });

    picker.setFromText(init.inputs.seedText);

    const strategy = ref<LayoutStrategy>(init.inputs.strategy);
    const backgroundText = ref(init.inputs.backgroundText);
    const secondaryText = ref(init.inputs.secondaryText);
    const snapshot = shallowRef<LayoutSnapshot>(init.snapshot);
    const pendingRegeneration = ref(false);
    const target = ref<LayoutEditTarget>(init.snapshot.target);
    const notice = ref<LayoutNotice>(null);
    const editNotice = ref<LayoutEditNotice>(null);

    // Montar com os valores hidratados nao e uma edicao: o watch nasce depois
    // das atribuicoes e sem `immediate`.
    watch(
        [
            () => picker.text.value,
            () => picker.rgba.value,
            strategy,
            backgroundText,
            secondaryText,
        ],
        () => {
            pendingRegeneration.value = true;
        },
    );

    const hasSeed = computed(() => picker.text.value.trim() !== '');
    const needsBackground = computed(() => picker.rgba.value.a < 1);
    const background = computed(() =>
        parseBackgroundText(backgroundText.value),
    );
    const backgroundInvalid = computed(
        () => backgroundText.value.trim() !== '' && background.value === null,
    );
    const secondaryInvalid = computed(
        () => parseSecondaryText(secondaryText.value) === undefined,
    );
    const secondarySource = computed(() => snapshot.value.secondarySource);
    const secondaryColor = computed(() => {
        const parsed = parseSecondaryText(secondaryText.value);

        return parsed === undefined || parsed === null ? null : parsed;
    });

    const result = computed<LayoutViewResult>(() =>
        frozenResult(snapshot.value),
    );
    const seed = computed(() => result.value.seed);
    const editState = computed<LayoutEditState>(() =>
        editStateFromSnapshot(snapshot.value),
    );
    const suggestions = computed(() => []);
    const isStale = computed(() => false);
    const editable = computed(() => mode !== 'readonly');

    function applyColor(
        theme: LayoutTheme,
        token: LayoutTokenId,
        color: Rgba,
    ): void {
        if (!editable.value) {
            return;
        }

        snapshot.value = applyFrozenColor(snapshot.value, theme, token, color);
    }

    function toggleTokenLock(theme: LayoutTheme, token: LayoutTokenId): void {
        if (!editable.value) {
            return;
        }

        snapshot.value = toggleFrozenLock(snapshot.value, theme, token);
    }

    return {
        mode,
        picker,
        strategy,
        backgroundText,
        secondaryText,
        hasSeed,
        needsBackground,
        background,
        backgroundInvalid,
        secondaryInvalid,
        secondarySource,
        secondaryColor,
        result,
        seed,
        notice,
        isStale,
        target,
        editState,
        editNotice,
        suggestions,
        snapshot,
        editable,
        canRestoreEdits: false,
        canSuggest: false,
        pendingRegeneration,
        isTokenLocked: (theme, token) =>
            isFrozenLocked(snapshot.value, theme, token),
        applyColor,
        toggleTokenLock,
        acceptAdjustment: () => {},
        acceptAdjustmentInBothThemes: () => {},
        restoreTokenEdit: () => {},
        restoreThemeEdits: () => {},
        restoreAllEdits: () => {},
        resetSecondary: () => {
            secondaryText.value = '';
        },
    };
}
