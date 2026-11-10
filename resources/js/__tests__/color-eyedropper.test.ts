import { afterEach, describe, expect, it } from 'vitest';
import {
    isAbortError,
    isEyeDropperSupported,
    pickScreenColor,
} from '@/lib/eyedropper';

/**
 * Conta-gotas da tela (ADR-117): presente, ausente, cancelado e falha.
 */

function setEyeDropper(value: unknown): void {
    (window as unknown as { EyeDropper?: unknown }).EyeDropper = value;
}

afterEach(() => {
    setEyeDropper(undefined);
});

describe('EyeDropper', () => {
    it('reporta ausencia de suporte', () => {
        setEyeDropper(undefined);

        expect(isEyeDropperSupported()).toBe(false);
    });

    it('ignora valores que nao sao construtores', () => {
        setEyeDropper({ open: () => Promise.resolve() });

        expect(isEyeDropperSupported()).toBe(false);
    });

    it('devolve null quando nao ha suporte', async () => {
        setEyeDropper(undefined);

        await expect(pickScreenColor()).resolves.toBeNull();
    });

    it('devolve o HEX escolhido', async () => {
        const Stub = class {
            open(): Promise<{ sRGBHex: string }> {
                return Promise.resolve({ sRGBHex: '#123456' });
            }
        };

        setEyeDropper(Stub);

        expect(isEyeDropperSupported()).toBe(true);
        await expect(pickScreenColor()).resolves.toBe('#123456');
    });

    it('trata cancelamento como null', async () => {
        const Stub = class {
            open(): Promise<{ sRGBHex: string }> {
                return Promise.reject(new DOMException('cancel', 'AbortError'));
            }
        };

        setEyeDropper(Stub);

        await expect(pickScreenColor()).resolves.toBeNull();
    });

    it('trata qualquer falha como null', async () => {
        const Stub = class {
            open(): Promise<{ sRGBHex: string }> {
                return Promise.reject(new Error('not allowed'));
            }
        };

        setEyeDropper(Stub);

        await expect(pickScreenColor()).resolves.toBeNull();
    });

    it('reconhece apenas DOMException AbortError', () => {
        expect(isAbortError(new DOMException('x', 'AbortError'))).toBe(true);
        expect(isAbortError(new DOMException('x', 'NotAllowedError'))).toBe(
            false,
        );
        expect(isAbortError(new Error('AbortError'))).toBe(false);
        expect(isAbortError(null)).toBe(false);
    });
});
