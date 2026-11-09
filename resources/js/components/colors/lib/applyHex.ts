import { parseColor, type Rgba } from '@/lib/color';

/**
 * Traducao de um HEX recebido de fora para o estado do picker.
 *
 * O `input[type=color]` do navegador nao tem alpha: se o usuario escolhe um HEX
 * ali, a opacidade atual continua valendo. A tela e a imagem, ao contrario,
 * devolvem cor de pixel - sempre opaca.
 */

export function withCurrentAlpha(hex: string, current: Rgba): Rgba | null {
    const result = parseColor(hex);

    return result.ok ? { ...result.color.rgba, a: current.a } : null;
}

export function opaqueColor(hex: string): Rgba | null {
    const result = parseColor(hex);

    return result.ok ? { ...result.color.rgba, a: 1 } : null;
}
