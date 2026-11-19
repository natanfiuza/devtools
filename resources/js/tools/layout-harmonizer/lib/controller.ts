import type { ComputedRef, Ref, ShallowRef } from 'vue';
import type { Rgb, Rgba } from '@/lib/color';
import type { ColorState } from '@/composables/useColorState';
import type {
    LayoutEngineErrorCode,
    LayoutSeedInfo,
    LayoutStrategy,
    LayoutSystemResult,
    LayoutTheme,
    LayoutTokenId,
} from '../engine';
import type { LayoutEditState, LayoutEditTarget } from './editState';
import type { LayoutSnapshot } from './snapshot';
import type { LayoutSuggestion } from './suggestions';
import type {
    LayoutHydrateMode,
    LayoutViewResult,
    LayoutStateInputsV1,
    LayoutSystemStateV1,
} from './state';

/**
 * Contrato comum dos workspaces do Layout Harmonizer (F4, ADR-169).
 *
 * O `.vue` consome a mesma interface nos dois modos: o live regenera com o
 * motor atual; o congelado exibe o snapshot gravado e edita no lugar. A uniao
 * discriminada por `mode` mantem os tipos de `result`/`editState` precisos.
 */

/** Aviso ativo; `null` quando a ultima entrada gerou um quadro. */
export type LayoutNotice = LayoutEngineErrorCode | 'invalid-seed' | null;

/** Aviso de edicao, separado do aviso do quadro para nao marcar stale. */
export type LayoutEditNotice = 'discarded' | null;

export interface LayoutSystemInit {
    state: LayoutSystemStateV1;
    mode: 'live';
}

interface LayoutControllerCommon {
    mode: LayoutHydrateMode;
    picker: ColorState;
    strategy: Ref<LayoutStrategy>;
    backgroundText: Ref<string>;
    secondaryText: Ref<string>;
    /** Ha texto no campo da seed (mesmo invalido). */
    hasSeed: ComputedRef<boolean>;
    /** A seed tem alpha e precisa de fundo explicito. */
    needsBackground: ComputedRef<boolean>;
    background: ComputedRef<Rgb | null>;
    backgroundInvalid: ComputedRef<boolean>;
    /** Texto da secundaria preenchido e invalido; o ultimo valor vale. */
    secondaryInvalid: ComputedRef<boolean>;
    /** Origem efetiva da secundaria no quadro exibido. */
    secondarySource: ComputedRef<'auto' | 'manual'>;
    /** RGB efetivo da secundaria manual; `null` quando automatica. */
    secondaryColor: ComputedRef<Rgb | null>;
    seed: ComputedRef<LayoutSeedInfo | null>;
    notice: Ref<LayoutNotice>;
    /** Ha um quadro anterior enquanto a entrada atual e invalida. */
    isStale: ComputedRef<boolean>;
    /** Meta de sugestao; trocar nao regenera o quadro. */
    target: Ref<LayoutEditTarget>;
    editNotice: Ref<LayoutEditNotice>;
    suggestions: ComputedRef<LayoutSuggestion[]>;
    /** O quadro pode ser editado. */
    editable: ComputedRef<boolean>;
    /** Restaurar token/tema/sistema tem valor-base neste modo. */
    canRestoreEdits: boolean;
    /** Sugestoes automaticas funcionam neste modo. */
    canSuggest: boolean;
    /** Entrada mudou, mas o quadro congelado so troca por decisao explicita. */
    pendingRegeneration: Ref<boolean>;
    isTokenLocked(theme: LayoutTheme, token: LayoutTokenId): boolean;
    applyColor(theme: LayoutTheme, token: LayoutTokenId, color: Rgba): void;
    toggleTokenLock(theme: LayoutTheme, token: LayoutTokenId): void;
    acceptAdjustment(theme: LayoutTheme, token: LayoutTokenId): void;
    acceptAdjustmentInBothThemes(token: LayoutTokenId): void;
    restoreTokenEdit(theme: LayoutTheme, token: LayoutTokenId): void;
    restoreThemeEdits(theme: LayoutTheme): void;
    restoreAllEdits(): void;
    resetSecondary(): void;
}

/** Workspace live (F2/F3): regenera com o motor atual. */
export interface LiveLayoutController extends LayoutControllerCommon {
    mode: 'live';
    result: ShallowRef<LayoutSystemResult | null>;
    snapshot: ComputedRef<LayoutSnapshot | null>;
    editState: ShallowRef<LayoutEditState>;
}

/** Workspace congelado: snapshot gravado, versoes que podem ser antigas. */
export interface FrozenLayoutController extends LayoutControllerCommon {
    mode: 'compatible' | 'readonly';
    result: ComputedRef<LayoutViewResult | null>;
    snapshot: ShallowRef<LayoutSnapshot | null>;
    editState: ComputedRef<LayoutEditState>;
}

export type LayoutController = LiveLayoutController | FrozenLayoutController;

/** Entradas cruas do envelope persistido, do jeito que a tela as edita. */
export type LayoutInputs = LayoutStateInputsV1;
