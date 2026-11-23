import { parseColor, type Rgba } from '@/lib/color';
import {
    generateLayoutSystem,
    LayoutEngineError,
    type LayoutTheme,
    type LayoutTokenId,
} from '../engine';
import {
    effectiveOverrides,
    emptyEditState,
    type LayoutEditState,
    type LayoutOverrideMap,
} from './editState';
import { parseOpaqueText, parseSecondaryText } from './inputs';
import { buildSnapshot, type LayoutSnapshot } from './snapshot';
import {
    buildLayoutState,
    type LayoutStateInputsV1,
    type LayoutSystemStateV1,
} from './state';

/**
 * Regeneracao explicita de um sistema congelado (F4, ADR-169).
 *
 * O modo `compatible` nao guarda o valor automatico anterior dos tokens, entao
 * regenerar e uma decisao do usuario: as entradas atuais viram o novo quadro e
 * so os tokens bloqueados sobrevivem como overrides; edicoes sem bloqueio
 * caem, como em qualquer troca de assinatura no modo live.
 */

/** Valores do quadro congelado que sobrevivem a regeneracao. */
function lockedOverrides(snapshot: LayoutSnapshot): LayoutEditState {
    const state = emptyEditState();

    for (const lock of snapshot.locks) {
        const theme: LayoutTheme = lock.theme;
        const token: LayoutTokenId = lock.token;
        const entry = snapshot.themes
            .find((board) => board.theme === theme)
            ?.tokens.find((current) => current.id === token);

        const locks = (state.locks[theme] ??= {});
        locks[token] = true;

        if (!entry) {
            continue;
        }

        const overrides: LayoutOverrideMap = state.overrides;
        const themeOverrides = (overrides[theme] ??= {});
        themeOverrides[token] = {
            rgb: { r: entry.rgba.r, g: entry.rgba.g, b: entry.rgba.b },
            origin: 'lock',
        };
    }

    return state;
}

function colorOf(text: string): Rgba | null {
    const parsed = parseColor(text.trim());

    return parsed.ok ? parsed.color.rgba : null;
}

/**
 * Novo envelope com as versoes atuais do motor. `null` quando as entradas
 * atuais nao geram quadro (seed invalida, alpha sem fundo ou secundaria
 * invalida): regenerar nunca inventa cor.
 */
export function regenerateState(
    state: LayoutSystemStateV1,
    inputs: LayoutStateInputsV1,
): LayoutSystemStateV1 | null {
    const seed = colorOf(inputs.seedText);

    if (seed === null) {
        return null;
    }

    const parsedBackground = parseOpaqueText(inputs.backgroundText);
    const parsedSecondaryBackground = parseOpaqueText(
        inputs.secondaryBackgroundText ?? '',
    );
    const background =
        parsedBackground.kind === 'color' ? parsedBackground.rgb : null;
    const secondaryBackground =
        parsedSecondaryBackground.kind === 'color'
            ? parsedSecondaryBackground.rgb
            : null;
    const secondary = parseSecondaryText(inputs.secondaryText);

    if (secondary === undefined || parsedBackground.kind === 'alpha') {
        return null;
    }

    if (seed.a < 1 && background === null) {
        return null;
    }

    if (secondary !== null && secondary.a < 1 && secondaryBackground === null) {
        return null;
    }

    const editState = lockedOverrides(state.snapshot);

    try {
        const result = generateLayoutSystem({
            seed,
            strategy: inputs.strategy,
            background,
            secondary,
            secondaryBackground,
            overrides: effectiveOverrides(editState),
        });

        const snapshot = buildSnapshot(
            result,
            editState,
            state.snapshot.target,
        );

        return buildLayoutState(
            inputs,
            secondary,
            snapshot,
            secondaryBackground,
        );
    } catch (error) {
        if (error instanceof LayoutEngineError) {
            return null;
        }

        throw error;
    }
}
