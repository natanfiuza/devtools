import {
    computed,
    ref,
    shallowRef,
    watch,
    type ComputedRef,
    type Ref,
    type ShallowRef,
} from 'vue';
import { parseColor, type Rgb } from '@/lib/color';
import { useColorState, type ColorState } from '@/composables/useColorState';
import {
    generateLayoutSystem,
    LayoutEngineError,
    type LayoutEngineErrorCode,
    type LayoutSeedInfo,
    type LayoutStrategy,
    type LayoutSystemResult,
} from '../engine';

/**
 * Estado da ferramenta na F2: seed, estrategia, fundo do alpha e o ultimo
 * quadro valido. Sem storage, sem servidor e sem tema do site.
 *
 * A regra central e "ultimo valor valido": entrada invalida nunca apaga o
 * quadro anterior nem inventa um fundo; ela so vira um aviso, e o quadro
 * continua o mesmo ate a proxima entrada valida (ADR-149).
 */

/** Aviso ativo; `null` quando a ultima entrada gerou um quadro. */
export type LayoutNotice = LayoutEngineErrorCode | 'invalid-seed' | null;

/**
 * Cor interna do picker antes da primeira escolha. Nunca vira seed: o campo
 * abre vazio e so uma interacao real (`text` nao vazio) libera a geracao.
 */
const PLACEHOLDER = { r: 255, g: 255, b: 255, a: 1 };

export interface LayoutSystemState {
    picker: ColorState;
    strategy: Ref<LayoutStrategy>;
    backgroundText: Ref<string>;
    /** Ha texto no campo da seed (mesmo invalido). */
    hasSeed: ComputedRef<boolean>;
    /** A seed tem alpha e precisa de fundo explicito. */
    needsBackground: ComputedRef<boolean>;
    background: ComputedRef<Rgb | null>;
    backgroundInvalid: ComputedRef<boolean>;
    result: ShallowRef<LayoutSystemResult | null>;
    seed: ComputedRef<LayoutSeedInfo | null>;
    notice: Ref<LayoutNotice>;
    /** Ha um quadro anterior enquanto a entrada atual e invalida. */
    isStale: ComputedRef<boolean>;
}

export function useLayoutSystem(): LayoutSystemState {
    const picker = useColorState({ initial: PLACEHOLDER });

    // Campo vazio e um estado da pagina, nao um erro de formato: o picker
    // esconde `empty`, e a seed nasce "nao escolhida".
    picker.setFromText('');

    const strategy = ref<LayoutStrategy>('analogous');
    const backgroundText = ref('');
    const result = shallowRef<LayoutSystemResult | null>(null);
    const notice = ref<LayoutNotice>(null);

    const hasSeed = computed(() => picker.text.value.trim() !== '');

    const background = computed<Rgb | null>(() => {
        const text = backgroundText.value.trim();

        if (text === '') {
            return null;
        }

        const parsed = parseColor(text);

        if (!parsed.ok) {
            return null;
        }

        // O fundo da composicao e sempre opaco: so os canais RGB entram.
        return {
            r: parsed.color.rgba.r,
            g: parsed.color.rgba.g,
            b: parsed.color.rgba.b,
        };
    });

    const backgroundInvalid = computed(
        () => backgroundText.value.trim() !== '' && background.value === null,
    );

    const needsBackground = computed(() => picker.rgba.value.a < 1);

    const seed = computed<LayoutSeedInfo | null>(
        () => result.value?.seed ?? null,
    );

    const isStale = computed(
        () => notice.value !== null && result.value !== null,
    );

    watch(
        [
            () => picker.text.value,
            () => picker.rgba.value,
            strategy,
            backgroundText,
        ],
        () => {
            if (!hasSeed.value) {
                result.value = null;
                notice.value = null;

                return;
            }

            // A validade vem do texto digitado, nao de `picker.error`: os
            // sliders nao limpam um erro de texto anterior, e um texto valido
            // ja aplicou a cor em `rgba`.
            if (!parseColor(picker.text.value).ok) {
                notice.value = 'invalid-seed';

                return;
            }

            if (picker.rgba.value.a < 1 && background.value === null) {
                notice.value = 'seed-alpha-without-background';

                return;
            }

            try {
                result.value = generateLayoutSystem({
                    seed: picker.rgba.value,
                    strategy: strategy.value,
                    background: background.value,
                });
                notice.value = null;
            } catch (error) {
                if (error instanceof LayoutEngineError) {
                    notice.value = error.code;

                    return;
                }

                throw error;
            }
        },
        { immediate: true },
    );

    return {
        picker,
        strategy,
        backgroundText,
        hasSeed,
        needsBackground,
        background,
        backgroundInvalid,
        result,
        seed,
        notice,
        isStale,
    };
}
