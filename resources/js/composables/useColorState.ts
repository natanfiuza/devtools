import { computed, ref, type ComputedRef, type Ref } from 'vue';
import {
    clamp,
    formatHex,
    formatHex8,
    hslToRgb,
    hsvToRgb,
    normalizeHue,
    parseColor,
    rgbToHsl,
    rgbToHsv,
} from '@/lib/color';
import type { Hsl, Hsv, ParseErrorCode, Rgba } from '@/lib/color';

/**
 * O estado do picker (ADR-117 e ADR-118).
 *
 * A cor autoritativa e `Rgba`; HSV/HSL/OKLCH sao sempre derivados. Existe uma
 * unica porta de escrita, entao o texto do campo, os sliders e a area 2D nunca
 * divergem.
 *
 * O **matiz ancora** e a excecao: no cinza (saturacao zero) o HSV nao tem
 * matiz, e sem a ancora o slider pularia para 0 ao passar pelo centro. A
 * ancora guarda o ultimo matiz escolhido enquanto a cor nao tiver matiz
 * proprio.
 */

export type RgbChannel = 'r' | 'g' | 'b';
export type HslChannel = 'h' | 's' | 'l';

export interface ColorState {
    rgba: Ref<Rgba>;
    hsv: ComputedRef<Hsv>;
    hsl: ComputedRef<Hsl>;
    /** Texto do campo - pode ser um rascunho invalido enquanto se digita. */
    text: Ref<string>;
    error: Ref<ParseErrorCode | null>;
    /** OKLCH/OKLab digitado fora do gamut, guardado so como aviso. */
    gamutSource: Ref<string | null>;
    outOfGamut: Ref<boolean>;
    setColor(color: Rgba): void;
    setFromHsv(next: Hsv): void;
    setHue(hue: number): void;
    setSaturationValue(saturation: number, value: number): void;
    setRgbChannel(channel: RgbChannel, value: number): void;
    setHslChannel(channel: HslChannel, value: number): void;
    setAlpha(alpha: number): void;
    /** Chamado a cada tecla: aplica quando valido, senao so marca o erro. */
    setFromText(text: string): boolean;
    /** Enter/blur: normaliza o texto para HEX e registra no historico. */
    finalizeText(): boolean;
    /** pointerup/blur/Enter: ponto em que a edicao vira historico. */
    finalize(): void;
}

export interface ColorStateOptions {
    initial: Rgba;
    onFinalize?: (color: Rgba) => void;
}

function clampRgba(color: Rgba): Rgba {
    return {
        r: clamp(color.r, 0, 255),
        g: clamp(color.g, 0, 255),
        b: clamp(color.b, 0, 255),
        a: clamp(color.a, 0, 1),
    };
}

export function useColorState(options: ColorStateOptions): ColorState {
    const rgba = ref<Rgba>(clampRgba(options.initial));
    const hue = ref(normalizeHue(rgbToHsv(options.initial).h));
    const text = ref(formatHex(options.initial));
    const error = ref<ParseErrorCode | null>(null);
    const gamutSource = ref<string | null>(null);
    const outOfGamut = ref(false);

    const hsv = computed<Hsv>(() => {
        const raw = rgbToHsv(rgba.value);

        return { h: hue.value, s: raw.s, v: raw.v };
    });

    // HSL usa o mesmo matiz ancora do HSV: no cinza, preto ou branco o slider
    // de matiz continua no ultimo valor escolhido em vez de pular para 0.
    const hsl = computed<Hsl>(() => {
        const raw = rgbToHsl(rgba.value);

        return { h: hue.value, s: raw.s, l: raw.l };
    });

    function syncText(): void {
        text.value =
            rgba.value.a < 1 ? formatHex8(rgba.value) : formatHex(rgba.value);
    }

    function adoptHue(color: Rgba): void {
        const raw = rgbToHsv(color);

        // Cor sem matiz (cinza) nao apaga a ancora.
        if (raw.s > 0 && raw.v > 0) {
            hue.value = raw.h;
        }
    }

    function clearGamut(): void {
        gamutSource.value = null;
        outOfGamut.value = false;
    }

    function setColor(color: Rgba): void {
        rgba.value = clampRgba(color);
        adoptHue(rgba.value);
        clearGamut();
        syncText();
    }

    function setFromHsv(next: Hsv): void {
        hue.value = normalizeHue(next.h);
        const rgb = hsvToRgb({
            h: hue.value,
            s: clamp(next.s, 0, 100),
            v: clamp(next.v, 0, 100),
        });

        rgba.value = { ...rgb, a: rgba.value.a };
        clearGamut();
        syncText();
    }

    /** Aplica um resultado do parser, copiando os metadados de gamut. */
    function applyParsed(input: string): boolean {
        const result = parseColor(input);

        if (!result.ok) {
            error.value = result.code;

            return false;
        }

        error.value = null;
        rgba.value = clampRgba(result.color.rgba);
        adoptHue(rgba.value);
        gamutSource.value = result.color.sourceValue;
        outOfGamut.value = result.color.outOfGamut;

        return true;
    }

    return {
        rgba,
        hsv,
        hsl,
        text,
        error,
        gamutSource,
        outOfGamut,
        setColor,

        setFromHsv,

        setHue(value) {
            setFromHsv({ h: value, s: hsv.value.s, v: hsv.value.v });
        },

        setSaturationValue(saturation, value) {
            setFromHsv({ h: hsv.value.h, s: saturation, v: value });
        },

        setRgbChannel(channel, value) {
            const next = clampRgba({ ...rgba.value });

            next[channel] = clamp(value, 0, 255);
            rgba.value = next;
            adoptHue(next);
            clearGamut();
            syncText();
        },

        setHslChannel(channel, value) {
            const current = hsl.value;
            const h = channel === 'h' ? normalizeHue(value) : current.h;

            // O h e a propria fonte quando o canal editado e o matiz; nos
            // demais, current.h vem da ancora e nao e sobrescrito.
            if (channel === 'h') {
                hue.value = h;
            }

            const rgb = hslToRgb({
                h,
                s: clamp(channel === 's' ? value : current.s, 0, 100),
                l: clamp(channel === 'l' ? value : current.l, 0, 100),
            });

            rgba.value = { ...rgb, a: rgba.value.a };
            clearGamut();
            syncText();
        },

        setAlpha(alpha) {
            rgba.value = { ...rgba.value, a: clamp(alpha, 0, 1) };
            syncText();
        },

        setFromText(value) {
            text.value = value;

            return applyParsed(value);
        },

        finalizeText() {
            if (!applyParsed(text.value)) {
                return false;
            }

            syncText();
            options.onFinalize?.(rgba.value);

            return true;
        },

        finalize() {
            options.onFinalize?.(rgba.value);
        },
    };
}
