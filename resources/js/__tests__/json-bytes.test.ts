import { describe, expect, it } from 'vitest';
import { byteUnit, formatBytes } from '@/tools/json-viewer/lib/bytes';

/**
 * Tamanho legivel nos cinco idiomas.
 *
 * O rotulo da unidade vem do `Intl`, entao a assercao precisa tolerar o espaco
 * que ele escolhe (comum, nao quebravel ou fino, conforme o idioma): o que
 * interessa aqui e o numero e a unidade, nao o espaco.
 */

const MB = 1024 * 1024;

function plain(text: string): string {
    return text.replace(/[   ]/g, ' ');
}

describe('byteUnit', () => {
    it('sobe de unidade a cada 1024', () => {
        expect(byteUnit(0)).toBe('byte');
        expect(byteUnit(1023)).toBe('byte');
        expect(byteUnit(1024)).toBe('kilobyte');
        expect(byteUnit(MB - 1)).toBe('kilobyte');
        expect(byteUnit(MB)).toBe('megabyte');
        expect(byteUnit(1024 * MB)).toBe('gigabyte');
    });

    it('para no gigabyte', () => {
        expect(byteUnit(1024 * 1024 * MB)).toBe('gigabyte');
    });

    it('trata tamanho negativo como zero', () => {
        expect(byteUnit(-1)).toBe('byte');
    });
});

describe('formatBytes', () => {
    it('escreve o limite da tool em megabytes', () => {
        expect(plain(formatBytes(5 * MB, 'pt-BR'))).toBe('5 MB');
        expect(plain(formatBytes(5 * MB, 'en'))).toBe('5 MB');
    });

    it('usa o rotulo do idioma', () => {
        // O frances escreve "Mo" para megabyte.
        expect(plain(formatBytes(5 * MB, 'fr'))).toBe('5 Mo');
    });

    it('mostra uma casa decimal quando o numero nao e redondo', () => {
        expect(plain(formatBytes(1.5 * MB, 'pt-BR'))).toBe('1,5 MB');
        expect(plain(formatBytes(1.5 * MB, 'en'))).toBe('1.5 MB');
    });

    it('nao mostra fracao de byte', () => {
        expect(plain(formatBytes(1, 'en'))).toBe('1 byte');
        expect(plain(formatBytes(512, 'en'))).toMatch(/^512 byte/);
    });

    it('delega plural do rotulo para o Intl', () => {
        // Se o rotulo sai "byte" ou "bytes" quem decide e o CLDR da maquina: o
        // que e nosso e o numero e a unidade, nao a flexao.
        expect(plain(formatBytes(0, 'pt-BR'))).toMatch(/^0 byte/);
        expect(plain(formatBytes(0, 'en'))).toMatch(/^0 byte/);
    });
});
