import { computed, ref, shallowRef, watch } from 'vue';
import { parseColor, type Rgb, type Rgba } from '@/lib/color';
import { useColorState } from '@/composables/useColorState';
import {
    generateLayoutSystem,
    LayoutEngineError,
    resolveSeed,
    type LayoutSeedInfo,
    type LayoutStrategy,
    type LayoutSystemResult,
    type LayoutTheme,
    type LayoutTokenId,
} from '../engine';
import {
    acceptSuggestion,
    dropUnlocked,
    effectiveOverrides,
    emptyEditState,
    hasUnlockedOverrides,
    isLocked,
    restoreAll,
    restoreTheme,
    restoreToken,
    setOverride,
    signatureOf,
    toggleLock,
    type LayoutEditState,
    type LayoutEditTarget,
} from '../lib/editState';
import type {
    LayoutEditNotice,
    LayoutNotice,
    LayoutSystemInit,
    LiveLayoutController,
} from '../lib/controller';
import {
    LAYOUT_PLACEHOLDER,
    parseOpaqueText,
    parseSecondaryText,
} from '../lib/inputs';
import { buildSnapshot, type LayoutSnapshot } from '../lib/snapshot';
import { hydrateState, materializeResult } from '../lib/state';
import { suggestionsFor, type LayoutSuggestion } from '../lib/suggestions';

/**
 * Estado da ferramenta: seed, estrategia, fundo do alpha, secundaria manual e
 * o ultimo quadro valido, mais as edicoes da F3 (overrides, bloqueios,
 * aceites). Sem storage, sem servidor e sem tema do site.
 *
 * A regra central continua "ultimo valor valido": entrada invalida nunca
 * apaga o quadro anterior nem inventa um fundo; ela so vira um aviso, e o
 * quadro continua o mesmo ate a proxima entrada valida (ADR-149).
 *
 * Edicoes ficam FORA do `result`, porque o motor e substituido inteiro a cada
 * geracao e um valor bloqueado precisa sobreviver a troca de seed.
 *
 * Na F4 o composable tambem reabre um sistema salvo (`initial`): hidrata o
 * snapshot sem regenerar e consome a primeira execucao do watch (ADR-168).
 */

export type {
    LayoutController,
    LayoutEditNotice,
    LayoutNotice,
} from '../lib/controller';

export interface LayoutSystemOptions {
    /** Sistema salvo: hidrata o workspace live sem chamar o motor. */
    initial?: LayoutSystemInit;
    /** CTA da paleta: preenche a seed e deixa o watch gerar uma vez. */
    seedText?: string;
}

export function useLayoutSystem(
    options: LayoutSystemOptions = {},
): LiveLayoutController {
    const picker = useColorState({ initial: LAYOUT_PLACEHOLDER });

    // Campo vazio e um estado da pagina, nao um erro de formato: o picker
    // esconde `empty`, e a seed nasce "nao escolhida".
    picker.setFromText('');

    const strategy = ref<LayoutStrategy>('analogous');
    const backgroundText = ref('');
    const secondaryText = ref('');
    const secondaryBackgroundText = ref('');
    const result = shallowRef<LayoutSystemResult | null>(null);
    const notice = ref<LayoutNotice>(null);
    const editNotice = ref<LayoutEditNotice>(null);
    const target = ref<LayoutEditTarget>('aa');
    const editState = shallowRef<LayoutEditState>(emptyEditState());
    /** O live nunca pendura regeneracao: o watch sempre gera. */
    const pendingRegeneration = ref(false);
    /** Assinatura das entradas que regeneram; `null` antes do primeiro quadro. */
    let lastSignature: string | null = null;

    const hasSeed = computed(() => picker.text.value.trim() !== '');

    const parsedBackground = computed(() =>
        parseOpaqueText(backgroundText.value),
    );

    const background = computed<Rgb | null>(() =>
        parsedBackground.value.kind === 'color'
            ? parsedBackground.value.rgb
            : null,
    );

    /** Fundo da seed precisa ser opaco: invalido e alpha sao recusados. */
    const backgroundInvalid = computed(
        () =>
            parsedBackground.value.kind === 'invalid' ||
            parsedBackground.value.kind === 'alpha',
    );

    const backgroundAlpha = computed(
        () => parsedBackground.value.kind === 'alpha',
    );

    const parsedSecondary = computed(() =>
        parseSecondaryText(secondaryText.value),
    );

    const secondaryInvalid = computed(
        () => parsedSecondary.value === undefined,
    );

    /** Secundaria informada (com alpha), pronta para o envelope persistido. */
    const secondaryColor = computed<Rgba | null>(
        () => parsedSecondary.value ?? null,
    );

    const parsedSecondaryBackground = computed(() =>
        parseOpaqueText(secondaryBackgroundText.value),
    );

    const secondaryBackground = computed<Rgb | null>(() =>
        parsedSecondaryBackground.value.kind === 'color'
            ? parsedSecondaryBackground.value.rgb
            : null,
    );

    const secondaryBackgroundAlpha = computed(
        () => parsedSecondaryBackground.value.kind === 'alpha',
    );

    const secondaryBackgroundInvalid = computed(
        () =>
            parsedSecondaryBackground.value.kind === 'invalid' ||
            parsedSecondaryBackground.value.kind === 'alpha',
    );

    const secondaryNeedsBackground = computed(
        () => secondaryColor.value !== null && secondaryColor.value.a < 1,
    );

    const needsBackground = computed(() => picker.rgba.value.a < 1);

    const seed = computed<LayoutSeedInfo | null>(
        () => result.value?.seed ?? null,
    );

    const secondarySource = computed<'auto' | 'manual'>(
        () => result.value?.secondarySource ?? 'auto',
    );

    const isStale = computed(
        () => notice.value !== null && result.value !== null,
    );

    /** Entrada invalida e estado obsoleto nao aceitam edicao. */
    const editable = computed(
        () => result.value !== null && notice.value === null,
    );

    function resetEdits(): void {
        editState.value = emptyEditState();
        editNotice.value = null;
        lastSignature = null;
    }

    /**
     * Gera uma unica vez. A assinatura so muda com seed efetiva, estrategia ou
     * secundaria manual; nesse caso os overrides sem bloqueio saem ANTES da
     * geracao, e o aviso correspondente aparece sem marcar o quadro como stale.
     */
    function regenerate(): void {
        const manualSecondary = parsedSecondary.value;

        // Secundaria digitada e invalida preserva o ultimo quadro, como a seed.
        if (manualSecondary === undefined) {
            return;
        }

        if (!hasSeed.value) {
            result.value = null;
            notice.value = null;
            resetEdits();

            return;
        }

        // A validade vem do texto digitado, nao de `picker.error`: os sliders
        // nao limpam um erro de texto anterior, e um texto valido ja aplicou a
        // cor em `rgba`.
        if (!parseColor(picker.text.value).ok) {
            notice.value = 'invalid-seed';

            return;
        }

        // Fundo de composicao nunca tem alpha: recusar e melhor que descartar.
        if (backgroundAlpha.value) {
            notice.value = 'seed-background-alpha-invalid';

            return;
        }

        if (picker.rgba.value.a < 1 && background.value === null) {
            notice.value = 'seed-alpha-without-background';

            return;
        }

        if (
            manualSecondary !== null &&
            manualSecondary.a < 1 &&
            secondaryBackground.value === null
        ) {
            notice.value = 'secondary-alpha-without-background';

            return;
        }

        let resolved;

        try {
            resolved = resolveSeed(picker.rgba.value, background.value);
        } catch (error) {
            if (error instanceof LayoutEngineError) {
                notice.value = error.code;

                return;
            }

            throw error;
        }

        const signature = signatureOf(
            resolved.effective,
            strategy.value,
            manualSecondary,
            secondaryBackground.value,
        );
        const signatureChanged =
            lastSignature !== null && signature !== lastSignature;
        const discarded =
            signatureChanged && hasUnlockedOverrides(editState.value);
        const nextState = signatureChanged
            ? dropUnlocked(editState.value)
            : editState.value;

        try {
            const nextResult = generateLayoutSystem({
                seed: picker.rgba.value,
                strategy: strategy.value,
                background: background.value,
                secondary: manualSecondary,
                secondaryBackground: secondaryBackground.value,
                overrides: effectiveOverrides(nextState),
            });

            // So commita depois do sucesso: uma entrada que falha nunca perde
            // edicoes nem marca a assinatura como consumida.
            result.value = nextResult;
            editState.value = nextState;
            lastSignature = signature;
            notice.value = null;
            editNotice.value = discarded ? 'discarded' : null;
        } catch (error) {
            if (error instanceof LayoutEngineError) {
                notice.value = error.code;

                return;
            }

            throw error;
        }
    }

    /**
     * Reabre o sistema salvo preenchendo tudo antes do watch. A primeira
     * execucao e consumida pelo latch: sem ele, montar o workspace regeneraria
     * o quadro e poderia descartar overrides gravados (ADR-168).
     */
    let skipNext = false;

    if (options.initial) {
        const hydrated = hydrateState(options.initial.state);

        picker.setFromText(hydrated.inputs.seedText);
        strategy.value = hydrated.inputs.strategy;
        backgroundText.value = hydrated.inputs.backgroundText;
        secondaryText.value = hydrated.inputs.secondaryText;
        secondaryBackgroundText.value =
            hydrated.inputs.secondaryBackgroundText ?? '';
        result.value = materializeResult(hydrated.snapshot);
        editState.value = hydrated.editState;
        target.value = hydrated.target;
        lastSignature = signatureOf(
            hydrated.snapshot.seed.effective,
            hydrated.snapshot.strategy,
            hydrated.secondary,
            hydrated.secondaryBackground,
        );
        skipNext = true;
    } else if (options.seedText !== undefined) {
        // CTA da paleta: preenche a seed e deixa o watch gerar uma vez.
        picker.setFromText(options.seedText);
    }

    function onInputsChanged(): void {
        if (skipNext) {
            skipNext = false;

            return;
        }

        regenerate();
    }

    watch(
        [
            () => picker.text.value,
            () => picker.rgba.value,
            strategy,
            backgroundText,
            secondaryText,
            secondaryBackgroundText,
        ],
        onInputsChanged,
        { immediate: true },
    );

    /**
     * Acoes explicitas chamam a mesma regeneracao; nunca ha um segundo watch
     * de edicao, entao nao existe geracao dupla nem laco.
     */
    function commit(next: LayoutEditState): void {
        // Entrada invalida: a edicao fica guardada e a regeneracao mantem o
        // quadro antigo; quando a entrada voltar a valer, ja entra com ela.
        editState.value = next;
        regenerate();
    }

    function applyColor(
        theme: LayoutTheme,
        token: LayoutTokenId,
        color: Rgba,
    ): void {
        commit(setOverride(editState.value, theme, token, color));
    }

    function toggleTokenLock(theme: LayoutTheme, token: LayoutTokenId): void {
        const current = result.value?.themes[theme].tokens[token].rgba;

        if (!current) {
            return;
        }

        commit(toggleLock(editState.value, theme, token, current));
    }

    function suggestionFor(
        theme: LayoutTheme,
        token: LayoutTokenId,
    ): LayoutSuggestion | undefined {
        return suggestions.value.find(
            (suggestion) =>
                suggestion.theme === theme && suggestion.token === token,
        );
    }

    function acceptAdjustment(theme: LayoutTheme, token: LayoutTokenId): void {
        const suggestion = suggestionFor(theme, token);

        if (!suggestion?.proposal) {
            return;
        }

        commit(
            acceptSuggestion(
                editState.value,
                theme,
                token,
                suggestion.current,
                suggestion.proposal,
                target.value,
            ),
        );
    }

    function acceptAdjustmentInBothThemes(token: LayoutTokenId): void {
        let next = editState.value;
        let applied = false;

        for (const suggestion of suggestions.value) {
            if (suggestion.token !== token || suggestion.proposal === null) {
                continue;
            }

            applied = true;
            next = acceptSuggestion(
                next,
                suggestion.theme,
                suggestion.token,
                suggestion.current,
                suggestion.proposal,
                target.value,
            );
        }

        if (applied) {
            commit(next);
        }
    }

    function restoreTokenEdit(theme: LayoutTheme, token: LayoutTokenId): void {
        commit(restoreToken(editState.value, theme, token));
    }

    function restoreThemeEdits(theme: LayoutTheme): void {
        commit(restoreTheme(editState.value, theme));
    }

    function restoreAllEdits(): void {
        commit(restoreAll(editState.value));
    }

    function resetSecondary(): void {
        secondaryText.value = '';
        secondaryBackgroundText.value = '';
    }

    const suggestions = computed<LayoutSuggestion[]>(() =>
        result.value ? suggestionsFor(result.value, target.value) : [],
    );

    const snapshot = computed<LayoutSnapshot | null>(() =>
        result.value
            ? buildSnapshot(result.value, editState.value, target.value)
            : null,
    );

    return {
        mode: 'live',
        picker,
        strategy,
        backgroundText,
        secondaryText,
        secondaryBackgroundText,
        hasSeed,
        needsBackground,
        background,
        backgroundInvalid,
        backgroundAlpha,
        secondaryInvalid,
        secondaryNeedsBackground,
        secondaryBackground,
        secondaryBackgroundInvalid,
        secondaryBackgroundAlpha,
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
        canRestoreEdits: true,
        canSuggest: true,
        pendingRegeneration,
        isTokenLocked: (theme, token) =>
            isLocked(editState.value, theme, token),
        applyColor,
        toggleTokenLock,
        acceptAdjustment,
        acceptAdjustmentInBothThemes,
        restoreTokenEdit,
        restoreThemeEdits,
        restoreAllEdits,
        resetSecondary,
    };
}
