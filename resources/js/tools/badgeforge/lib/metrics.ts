import data from '../../../../badgeforge/font-metrics.json';

/**
 * Espelho de `App\Support\BadgeForge\FontMetrics` (ADR-075).
 *
 * A tabela e a mesma do PHP, indexada por code point em string. A iteracao e
 * por code point (`Array.from`), nunca por unidade UTF-16: emoji e CJK contam
 * uma vez, como em `mb_str_split`.
 */

interface MetricsData {
    fallback_units: number;
    bold_percent: number;
    family_bold_percent: Record<string, number>;
    zero_width_ranges: number[][];
    families: Record<string, Record<string, number>>;
}

const metrics = data as MetricsData;

export function textWidth(
    text: string,
    family: string,
    fontSize: number,
    fontWeight: number,
    letterSpacing: number,
): number {
    if (text === '') {
        return 0;
    }

    const advance = metrics.families[family] ?? metrics.families.sans ?? {};
    const fallback = metrics.fallback_units ?? 650;
    const zeroWidth = metrics.zero_width_ranges ?? [];

    let units = 0;
    let count = 0;

    for (const char of Array.from(text)) {
        const codePoint = char.codePointAt(0) ?? 0;
        count++;

        if (isZeroWidth(codePoint, zeroWidth)) {
            continue;
        }

        units += advance[String(codePoint)] ?? fallback;
    }

    // Fontes empacotadas trazem o proprio percentual (Bold/Regular);
    // `sans`/`helvetica` continuam na media global.
    const boldPercent =
        metrics.family_bold_percent?.[family] ?? metrics.bold_percent ?? 100;

    if (fontWeight >= 600 && boldPercent !== 100) {
        units = Math.trunc((units * boldPercent + 50) / 100);
    }

    let pixels = Math.max(1, Math.trunc((units * fontSize + 500) / 1000));

    if (letterSpacing > 0) {
        pixels += letterSpacing * count;
    }

    return pixels;
}

function isZeroWidth(codePoint: number, ranges: number[][]): boolean {
    return ranges.some(
        ([min = 0, max = 0]) => codePoint >= min && codePoint <= max,
    );
}
