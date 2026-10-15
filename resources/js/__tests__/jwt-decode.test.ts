import { describe, expect, it } from 'vitest';
import { decodeJwt, TIME_CLAIMS, type DecodedToken } from '@/tools/jwt-decoder/lib/decode';
import sample from '@/tools/jwt-decoder/sample.txt?raw';

/**
 * A leitura de um JWT, sem tela no meio.
 *
 * Os tokens dos casos sao montados aqui com `btoa`, e nao com o
 * `@/lib/encoding`: o teste precisa de um texto que exercite o codigo, e montar
 * o caso com o proprio codigo esconderia justamente um erro de alfabeto.
 *
 * O instante entra sempre pela mao - `decodeJwt` aceita `now` por isso -, para
 * nenhum caso depender do relogio de quem roda a suite.
 */

/** Um instante fixo: 2026-10-14 12:00 UTC. */
const AGORA = Date.UTC(2026, 9, 14, 12, 0, 0);

/** O mesmo instante em segundos, que e a unidade dos claims. */
const AGORA_S = AGORA / 1000;

const CABECALHO = { alg: 'HS256', typ: 'JWT' };

/** O base64url de "assinatura" - 10 bytes. */
const ASSINATURA = 'YXNzaW5hdHVyYQ';

/** Um segmento base64url de um JSON. */
function segment(value: unknown): string {
    const bytes = new TextEncoder().encode(JSON.stringify(value));
    const binary = String.fromCharCode(...bytes);

    return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

function token(
    payload: unknown,
    header: unknown = CABECALHO,
    signature: string = ASSINATURA,
): string {
    return `${segment(header)}.${segment(payload)}.${signature}`;
}

/** Le, exigindo que seja um token: o caso de recusa tem a sua propria porta. */
function ler(input: string, now: number = AGORA): DecodedToken {
    const result = decodeJwt(input, now);

    if (!result.ok) {
        throw new Error(`esperava um token, veio a recusa ${result.reason}`);
    }

    return result.token;
}

/** O motivo da recusa, ou 'ok' quando o texto passou. */
function motivo(input: string): string {
    const result = decodeJwt(input, AGORA);

    return result.ok ? 'ok' : result.reason;
}

describe('leitura do token', () => {
    it('le o cabecalho e o payload', () => {
        const lido = ler(token({ sub: '42', admin: true, tags: ['a', 'b'], nada: null }));

        expect(lido.header).toEqual({ alg: 'HS256', typ: 'JWT' });
        expect(lido.payload).toEqual({ sub: '42', admin: true, tags: ['a', 'b'], nada: null });
    });

    it('tolera o prefixo Bearer, em qualquer caixa', () => {
        const linha = token({ sub: '42' });

        expect(ler(`Bearer ${linha}`).payload).toEqual({ sub: '42' });
        expect(ler(`bearer ${linha}`).payload).toEqual({ sub: '42' });
        expect(ler(`  bEaReR   ${linha}  `).payload).toEqual({ sub: '42' });
    });

    it('tolera o token quebrado em linhas', () => {
        const linha = token({ sub: '42' });
        const quebrado = `${linha.slice(0, 20)}\n${linha.slice(20)}`;

        expect(ler(quebrado).payload).toEqual({ sub: '42' });
    });

    it('le o exemplo da tela', () => {
        const lido = ler(sample);

        expect(lido.header['alg']).toBe('HS256');
        expect(lido.payload['name']).toBe('João da Silva');
        expect(lido.signatureBytes).toBe(32);
        expect(lido.expired).toBe(true);
    });

    it('nao valida nada do conteudo: o cabecalho vem cru', () => {
        const lido = ler(token({ sub: '1' }, { alg: 'nenhum-algoritmo', typ: 'JWT', extra: 7 }));

        expect(lido.header).toEqual({ alg: 'nenhum-algoritmo', typ: 'JWT', extra: 7 });
    });
});

describe('o que nao e um JWT', () => {
    it('recusa o texto que nao tem tres partes', () => {
        expect(motivo('')).toBe('shape');
        expect(motivo('nada')).toBe('shape');
        expect(motivo('a.b')).toBe('shape');
        expect(motivo(`${token({ sub: '1' })}.quarta`)).toBe('shape');
    });

    it('recusa o segmento com caractere fora do alfabeto', () => {
        expect(motivo(`!!!.${segment({ sub: '1' })}.${ASSINATURA}`)).toBe('base64');
        expect(motivo(`${segment(CABECALHO)}.${segment({ sub: '1' })}.***`)).toBe('base64');
    });

    it('recusa o segmento com comprimento que o base64 nao fecha', () => {
        // 21 caracteres: base64 nenhum tem esse tamanho - sobram 6 bits soltos
        // no fim, que nao chegam a formar um byte.
        expect(motivo(`${segment(CABECALHO)}.${segment({ sub: '1' })}.qualquer-coisa-valida`)).toBe(
            'base64',
        );
    });

    it('recusa o segmento cujos bytes nao sao UTF-8', () => {
        expect(motivo(`_w.${segment({ sub: '1' })}.${ASSINATURA}`)).toBe('base64');
    });

    it('recusa o segmento que nao guarda JSON', () => {
        expect(motivo(`${segment(CABECALHO)}.b25kYQ.${ASSINATURA}`)).toBe('json');
    });

    it('recusa o JSON que nao e um objeto', () => {
        expect(motivo(token('texto'))).toBe('json');
        expect(motivo(token([1, 2, 3]))).toBe('json');
        expect(motivo(token(null))).toBe('json');
        expect(motivo(token(7))).toBe('json');
    });
});

describe('a assinatura', () => {
    it('aparece so como comprimento em bytes', () => {
        expect(ler(token({ sub: '1' })).signatureBytes).toBe(10);

        // 43 caracteres de base64url sem padding sao 32 bytes: o tamanho de uma
        // assinatura HS256 de verdade.
        const completa = 'BCP_2bylQ6tinXehvIuJek2eA0lOvAss9vDRFAr4thw';

        expect(ler(token({ sub: '1' }, CABECALHO, completa)).signatureBytes).toBe(32);
    });

    it('aceita a assinatura vazia do alg none', () => {
        const lido = ler(token({ sub: '1' }, { alg: 'none', typ: 'JWT' }, ''));

        expect(lido.signatureBytes).toBe(0);
    });

    it('nao confere a assinatura: uma inventada decodifica igual', () => {
        // 'YWJjZGVm' e "abcdef": base64url de sobra, e assinatura de nada.
        const inventada = ler(token({ sub: '1' }, CABECALHO, 'YWJjZGVm'));

        expect(inventada.payload).toEqual({ sub: '1' });
        expect(inventada.signatureBytes).toBe(6);
    });
});

describe('os claims de tempo', () => {
    it('saem na ordem iat, nbf, exp', () => {
        expect(TIME_CLAIMS).toEqual(['iat', 'nbf', 'exp']);

        const lido = ler(
            token({ exp: AGORA_S + 60, iat: AGORA_S - 60, nbf: AGORA_S - 30, sub: '1' }),
        );

        expect(lido.claims.map((claim) => claim.name)).toEqual(['iat', 'nbf', 'exp']);
    });

    it('so lista os que existem no payload', () => {
        expect(ler(token({ sub: '1' })).claims).toEqual([]);

        const soExp = ler(token({ sub: '1', exp: AGORA_S }));

        expect(soExp.claims.map((claim) => claim.name)).toEqual(['exp']);
    });

    it('entrega o instante como data', () => {
        const lido = ler(token({ iat: AGORA_S - 60, exp: AGORA_S + 60 }));

        expect(lido.claims.map((claim) => claim.date?.getTime())).toEqual([
            AGORA - 60_000,
            AGORA + 60_000,
        ]);
    });

    it('marca o exp que ja passou', () => {
        expect(ler(token({ exp: AGORA_S - 1 })).expired).toBe(true);
        expect(ler(token({ exp: AGORA_S + 1 })).expired).toBe(false);
        expect(ler(token({ sub: '1' })).expired).toBe(false);
    });

    it('trata o instante do proprio exp como vencido', () => {
        // RFC 7519: o token so vale com o agora **antes** do `exp`.
        expect(ler(token({ exp: AGORA_S })).expired).toBe(true);
    });

    it('marca o nbf que ainda nao chegou', () => {
        expect(ler(token({ nbf: AGORA_S + 1 })).notYetValid).toBe(true);
        expect(ler(token({ nbf: AGORA_S })).notYetValid).toBe(false);
        expect(ler(token({ sub: '1' })).notYetValid).toBe(false);
    });

    it('guarda o valor cru quando ele nao vira data', () => {
        const lido = ler(token({ exp: 'amanha', nbf: null, iat: 1e20 }));

        expect(lido.claims.map((claim) => claim.date)).toEqual([null, null, null]);
        expect(lido.claims.map((claim) => claim.raw)).toEqual([1e20, null, 'amanha']);
        // Sem data nao ha como dizer que venceu - e dizer que venceu seria pior
        // do que dizer que nao deu para ler.
        expect(lido.expired).toBe(false);
        expect(lido.notYetValid).toBe(false);
    });
});
