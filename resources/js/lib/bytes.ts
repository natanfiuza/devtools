/**
 * Tamanho em bytes, escrito no idioma da pagina.
 *
 * O rotulo vem do `Intl.NumberFormat` com `style: 'unit'` porque ele e traducao
 * ("5 MB" em pt, "5 Mo" em fr) - escrever isso a mao seria mais uma tabela de
 * unidades para manter em cinco idiomas, e a unica coisa que ela acrescentaria
 * e a chance de divergir.
 *
 * A base e 1024, que e o que o sistema de arquivos e a maioria das ferramentas
 * chama de KB, e a precisao para em uma casa decimal: aqui o numero serve para
 * dizer "passou do limite de 5 MB", nao para medir.
 */

export const BYTE_UNITS = ['byte', 'kilobyte', 'megabyte', 'gigabyte'] as const;

export type ByteUnit = (typeof BYTE_UNITS)[number];

const STEP = 1024;

/** Unidade em que o tamanho fica mais legivel. */
export function byteUnit(bytes: number): ByteUnit {
    const size = Math.max(bytes, 0);
    let unit = 0;

    while (unit < BYTE_UNITS.length - 1 && size >= STEP ** (unit + 1)) {
        unit += 1;
    }

    return BYTE_UNITS[unit] as ByteUnit;
}

export function formatBytes(bytes: number, locale: string): string {
    const size = Math.max(bytes, 0);
    const unit = byteUnit(size);

    return new Intl.NumberFormat(locale, {
        style: 'unit',
        unit,
        unitDisplay: 'short',
        // Byte e contagem, nao medida: "1,5 byte" nao existe.
        maximumFractionDigits: unit === 'byte' ? 0 : 1,
    }).format(size / STEP ** BYTE_UNITS.indexOf(unit));
}
