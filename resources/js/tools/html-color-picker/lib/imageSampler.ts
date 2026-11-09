/**
 * Matematica do amostrador por imagem (ADR-117).
 *
 * A parte que toca canvas fica no componente (e so depois de o usuario
 * escolher um arquivo); aqui ficam as contas que podem ser testadas sem DOM:
 * encaixar a imagem na caixa, traduzir o ponteiro para pixel e andar com o
 * teclado.
 */

export interface Size {
    width: number;
    height: number;
}

export interface Point {
    x: number;
    y: number;
}

export interface Rect {
    left: number;
    top: number;
    width: number;
    height: number;
}

/**
 * Escala que cabe em `max` sem distorcer e sem ampliar: uma imagem pequena e
 * mostrada no tamanho dela.
 */
export function fitImageSize(natural: Size, max: Size): Size {
    if (natural.width <= 0 || natural.height <= 0) {
        return { width: 0, height: 0 };
    }

    const scale = Math.min(
        1,
        max.width / natural.width,
        max.height / natural.height,
    );

    return {
        width: Math.max(1, Math.round(natural.width * scale)),
        height: Math.max(1, Math.round(natural.height * scale)),
    };
}

export function clampPixel(point: Point, natural: Size): Point {
    return {
        x: Math.min(
            Math.max(0, Math.floor(point.x)),
            Math.max(0, natural.width - 1),
        ),
        y: Math.min(
            Math.max(0, Math.floor(point.y)),
            Math.max(0, natural.height - 1),
        ),
    };
}

/** Ponto do ponteiro na tela -> pixel da imagem original. */
export function pointToPixel(point: Point, rect: Rect, natural: Size): Point {
    if (rect.width <= 0 || rect.height <= 0) {
        return { x: 0, y: 0 };
    }

    return clampPixel(
        {
            x: ((point.x - rect.left) / rect.width) * natural.width,
            y: ((point.y - rect.top) / rect.height) * natural.height,
        },
        natural,
    );
}

/** Passo de teclado; `null` para teclas que nao movem o cursor. */
export function pixelStep(key: string, shift: boolean): Point | null {
    const step = shift ? 10 : 1;

    switch (key) {
        case 'ArrowLeft':
            return { x: -step, y: 0 };
        case 'ArrowRight':
            return { x: step, y: 0 };
        case 'ArrowUp':
            return { x: 0, y: -step };
        case 'ArrowDown':
            return { x: 0, y: step };
        default:
            return null;
    }
}
