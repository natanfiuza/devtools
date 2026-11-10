/**
 * Conta-gotas da tela (ADR-117).
 *
 * A API `EyeDropper` nao existe em todo navegador (Firefox, por exemplo, nao
 * a tem). Aqui ela e opcional: quando existe, devolve o HEX escolhido; quando
 * nao existe - ou o usuario cancela - devolve `null` e quem chama decide
 * (o HTML Color Picker cai no amostrador por imagem; o gerador de paleta so
 * mostra o botao quando ha suporte).
 */

interface EyeDropperResult {
    sRGBHex: string;
}

interface EyeDropperInstance {
    open(options?: { signal?: AbortSignal }): Promise<EyeDropperResult>;
}

type EyeDropperConstructor = new () => EyeDropperInstance;

function constructorFor(): EyeDropperConstructor | null {
    if (typeof window === 'undefined') {
        return null;
    }

    const candidate = (window as unknown as { EyeDropper?: unknown })
        .EyeDropper;

    return typeof candidate === 'function'
        ? (candidate as EyeDropperConstructor)
        : null;
}

export function isEyeDropperSupported(): boolean {
    return constructorFor() !== null;
}

export function isAbortError(error: unknown): boolean {
    return error instanceof DOMException && error.name === 'AbortError';
}

/**
 * Abre o conta-gotas nativo. `null` quando nao ha suporte ou o usuario
 * cancela - os dois casos em que a tela nao deve mudar nada.
 */
export async function pickScreenColor(): Promise<string | null> {
    const EyeDropper = constructorFor();

    if (EyeDropper === null) {
        return null;
    }

    try {
        const result = await new EyeDropper().open();

        return result.sRGBHex;
    } catch {
        return null;
    }
}
