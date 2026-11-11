import { afterEach, describe, expect, it, vi } from 'vitest';
import { downloadBlob, downloadText } from '@/lib/download';

/**
 * Download local (F4).
 *
 * O Blob fica na memoria da pagina e a ancora e criada, clicada e removida na
 * hora; a URL so e revogada depois, senao parte dos navegadores cancela o
 * download. Nenhuma requisicao sai daqui.
 */

afterEach(() => {
    vi.unstubAllGlobals();
    vi.useRealTimers();
});

function fakeUrls(): {
    create: ReturnType<typeof vi.fn>;
    revoke: ReturnType<typeof vi.fn>;
} {
    const create = vi.fn(() => 'blob:mock-url');
    const revoke = vi.fn();

    vi.stubGlobal('URL', { createObjectURL: create, revokeObjectURL: revoke });

    return { create, revoke };
}

describe('downloadBlob', () => {
    it('clica uma ancora com nome, remove e revoga depois do atraso', () => {
        const { create, revoke } = fakeUrls();
        // O objeto externo evita o TypeScript estreitar `captured` para `null`
        // so porque a atribuicao acontece dentro do callback.
        const captured: {
            value: { name: string | null; href: string | null } | null;
        } = { value: null };
        const click = vi
            .spyOn(HTMLAnchorElement.prototype, 'click')
            .mockImplementation(() => {
                const anchor = document.querySelector('a[download]');

                captured.value = anchor
                    ? {
                          name: anchor.getAttribute('download'),
                          href: anchor.getAttribute('href'),
                      }
                    : null;
            });

        vi.useFakeTimers();
        downloadBlob('paleta.png', new Blob(['png'], { type: 'image/png' }));

        expect(create).toHaveBeenCalledOnce();
        expect(click).toHaveBeenCalledOnce();
        expect(captured.value).not.toBeNull();
        expect(captured.value?.name).toBe('paleta.png');
        expect(captured.value?.href).toBe('blob:mock-url');
        expect(document.querySelector('a[download]')).toBeNull();
        expect(revoke).not.toHaveBeenCalled();

        vi.advanceTimersByTime(1000);

        expect(revoke).toHaveBeenCalledWith('blob:mock-url');
    });
});

describe('downloadText', () => {
    it('embrulha o texto num Blob com o tipo pedido', () => {
        const { create } = fakeUrls();
        vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(
            () => {},
        );
        vi.useFakeTimers();

        downloadText('tokens.css', ':root {}', 'text/css');

        const blob = create.mock.calls[0]?.[0] as Blob;

        expect(blob.type).toBe('text/css');
        expect(create).toHaveBeenCalledOnce();
    });
});
