import { describe, expect, it } from 'vitest';
import {
    formatUuid,
    generateIds,
    ID_KINDS,
    ID_QUANTITIES,
    instantOfId,
    ulid,
    uuidV4,
    uuidV7,
} from '@/tools/timestamp-uuid/lib/ids';

/**
 * Os ids: a forma de cada um e, principalmente, a **volta**.
 *
 * O gerador nao tem valor fixo para conferir - um id aleatorio e aleatorio -, e
 * por isso o que se cobra aqui e a forma (versao e variante nos bits certos, 26
 * caracteres do alfabeto Crockford) e a ida e volta do instante: gerar com um
 * `now` e ler o mesmo `now` de volta.
 *
 * O unico valor exato e o do `formatUuid`, que recebe bytes fixos - e e por isso
 * que ele existe separado: e a conta que da para conferir na mao.
 */

/** Um instante fixo: 2026-10-14 12:00 UTC. */
const AGORA = Date.UTC(2026, 9, 14, 12, 0, 0);

/** As quatro formas que a RFC 9562 fixa, uma por versao. */
const UUID_V4 = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/;
const UUID_V7 = /^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/;
const ULID = /^[0-9A-HJKMNP-TV-Z]{26}$/;

describe('os tipos oferecidos', () => {
    it('sao tres, e a tela abre no primeiro', () => {
        expect(ID_KINDS).toEqual(['uuid_v4', 'uuid_v7', 'ulid']);
    });

    it('a quantidade e 1, 5 ou 10', () => {
        expect(ID_QUANTITIES).toEqual([1, 5, 10]);
    });
});

describe('formatUuid', () => {
    it('poe os 16 bytes na forma 8-4-4-4-12', () => {
        const bytes = Uint8Array.from({ length: 16 }, (_, index) => index);

        expect(formatUuid(bytes)).toBe('00010203-0405-0607-0809-0a0b0c0d0e0f');
    });

    it('nao mexe nos bytes que recebe', () => {
        const bytes = new Uint8Array(16);

        formatUuid(bytes);

        expect(Array.from(bytes)).toEqual(new Array(16).fill(0));
    });
});

describe('uuid v4', () => {
    it('sai na forma canonica, com a versao 4 e a variante', () => {
        expect(uuidV4()).toMatch(UUID_V4);
    });

    it('nao repete', () => {
        const ids = new Set(generateIds('uuid_v4', 10, AGORA));

        expect(ids.size).toBe(10);
    });

    it('constroi a mao onde o navegador nao tem randomUUID', () => {
        // O `randomUUID` so aparece em pagina segura; `getRandomValues` existe
        // em qualquer uma, e o caminho de reserva e o que fica.
        const original = Object.getOwnPropertyDescriptor(crypto, 'randomUUID');

        Object.defineProperty(crypto, 'randomUUID', { value: undefined, configurable: true });

        try {
            const id = uuidV4();

            expect(id).toMatch(UUID_V4);
            expect(uuidV4()).not.toBe(id);
        } finally {
            if (original === undefined) {
                delete (crypto as { randomUUID?: unknown }).randomUUID;
            } else {
                Object.defineProperty(crypto, 'randomUUID', original);
            }
        }
    });
});

describe('uuid v7', () => {
    it('carrega o instante nos primeiros 48 bits', () => {
        const id = uuidV7(AGORA);

        expect(id).toMatch(UUID_V7);
        expect(instantOfId(id)).toEqual({ kind: 'uuid_v7', ms: AGORA });
    });

    it('guarda o instante arredondado para baixo', () => {
        expect(instantOfId(uuidV7(AGORA + 0.7))?.ms).toBe(AGORA);
    });

    it('o resto e aleatorio: dois ids do mesmo instante sao diferentes', () => {
        expect(uuidV7(AGORA)).not.toBe(uuidV7(AGORA));
    });
});

describe('ulid', () => {
    it('sai com 26 caracteres do alfabeto Crockford', () => {
        expect(ulid(AGORA)).toMatch(ULID);
    });

    it('nao usa as letras que se confundem', () => {
        const todos = generateIds('ulid', 10, AGORA).join('');

        expect(todos).not.toMatch(/[ILOU]/);
    });

    it('carrega o instante nos primeiros 10 caracteres', () => {
        expect(instantOfId(ulid(AGORA))).toEqual({ kind: 'ulid', ms: AGORA });
    });

    it('sai em ordem quando o tempo passa', () => {
        // E o motivo de existir: comparados como texto, dois ULIDs ficam na
        // mesma ordem em que nasceram.
        expect(ulid(AGORA) < ulid(AGORA + 1000)).toBe(true);
    });

    it('o resto e aleatorio', () => {
        expect(ulid(AGORA)).not.toBe(ulid(AGORA));
    });
});

describe('instantOfId', () => {
    it('aceita o v7 e o ULID em caixa alta', () => {
        expect(instantOfId(uuidV7(AGORA).toUpperCase())?.ms).toBe(AGORA);
        expect(instantOfId(ulid(AGORA).toLowerCase())?.ms).toBe(AGORA);
    });

    it('tolera o espaco em volta', () => {
        expect(instantOfId(`  ${ulid(AGORA)}  `)?.ms).toBe(AGORA);
    });

    it('recusa o que nao guarda instante nenhum', () => {
        expect(instantOfId('')).toBeNull();
        expect(instantOfId('nada')).toBeNull();
        expect(instantOfId(uuidV4())).toBeNull();
        expect(instantOfId('00000000-0000-0000-0000-000000000000')).toBeNull();
        expect(instantOfId(ulid(AGORA).slice(1))).toBeNull();
        expect(instantOfId(`${ulid(AGORA)}0`)).toBeNull();
    });

    it('recusa o ULID cujo tempo nao cabe nos 48 bits', () => {
        // O primeiro caractere guarda 3 bits (10 caracteres = 50 bits para um
        // tempo de 48). De `8` para cima o instante passaria de 2^48 - e o
        // texto seria um ULID impossivel, nao um do ano 10000.
        expect(instantOfId(`8${'0'.repeat(25)}`)).toBeNull();
        expect(instantOfId(`7${'Z'.repeat(25)}`)?.ms).toBe(2 ** 48 - 1);
    });
});

describe('generateIds', () => {
    it('devolve a quantidade pedida', () => {
        for (const kind of ID_KINDS) {
            expect(generateIds(kind, 1, AGORA)).toHaveLength(1);
            expect(generateIds(kind, 10, AGORA)).toHaveLength(10);
        }
    });

    it('gera do tipo pedido', () => {
        expect(generateIds('uuid_v4', 1, AGORA)[0]).toMatch(UUID_V4);
        expect(generateIds('uuid_v7', 1, AGORA)[0]).toMatch(UUID_V7);
        expect(generateIds('ulid', 1, AGORA)[0]).toMatch(ULID);
    });
});
