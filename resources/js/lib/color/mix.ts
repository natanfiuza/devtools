import { clamp, oklabToRgb, rgbToOklab } from './convert';
import type { Rgba } from './types';

/**
 * Mistura e escalas (ADR-117).
 *
 * Misturar em OKLab da passos perceptualmente uniformes entre duas cores -
 * diferente de misturar canais sRGB, que escurece no meio. O alpha e
 * interpolado em linha reta e as escalas preservam o alpha da cor base.
 */

export function mixOklab(from: Rgba, to: Rgba, amount: number): Rgba {
    const t = clamp(amount, 0, 1);
    const a = rgbToOklab(from);
    const b = rgbToOklab(to);
    const rgb = oklabToRgb({
        l: a.l + (b.l - a.l) * t,
        a: a.a + (b.a - a.a) * t,
        b: a.b + (b.b - a.b) * t,
    });

    return {
        ...rgb,
        a: from.a + (to.a - from.a) * t,
    };
}

const OPAQUE_BLACK = { r: 0, g: 0, b: 0, a: 1 };
const OPAQUE_WHITE = { r: 255, g: 255, b: 255, a: 1 };

/** `count` tons mais escuros, do mais proximo ao mais distante. */
export function shadesOf(color: Rgba, count = 10): Rgba[] {
    return stepColors(color, OPAQUE_BLACK, count);
}

/** `count` tons mais claros, do mais proximo ao mais distante. */
export function tintsOf(color: Rgba, count = 10): Rgba[] {
    return stepColors(color, OPAQUE_WHITE, count);
}

function stepColors(color: Rgba, target: Rgba, count: number): Rgba[] {
    const total = Math.max(0, Math.floor(count));
    const opaque = { ...target, a: color.a };
    const steps: Rgba[] = [];

    for (let index = 1; index <= total; index += 1) {
        steps.push(mixOklab(color, opaque, index / (total + 1)));
    }

    return steps;
}
