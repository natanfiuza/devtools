import { describe, expect, it } from 'vitest';
import {
    hasUrlCredentials,
    isSensitiveKey,
    maskEnv,
} from '@/tools/env-example/lib/mask';
import { parseEnv } from '@/tools/env-example/lib/parse';

/**
 * A mascara.
 *
 * Dois lados importam igual: o nome sensivel precisa ser pego (senao o segredo
 * vaza no exemplo) e o nome inocente precisa ficar (senao o exemplo perde
 * sentido). A URL com credencial cobre o caso em que o nome nao denuncia o
 * valor.
 */

function mascarar(source: string) {
    return maskEnv(parseEnv(source));
}

describe('isSensitiveKey', () => {
    it('reconhece os segmentos aprovados', () => {
        for (const key of [
            'APP_KEY',
            'DB_PASSWORD',
            'API_TOKEN',
            'MAIL_PASS',
            'JWT_SECRET',
            'AWS_CREDENTIAL',
            'DB_DSN',
            'SMTP_PWD',
            'SMTP_PASSWD',
            'OAUTH_AUTH',
            'MY_PRIVATE_THING',
            'api_key',
            'db-password',
        ]) {
            expect(isSensitiveKey(key), key).toBe(true);
        }
    });

    it('nao mascara nome inocente que contem a sequencia', () => {
        for (const key of ['AUTHOR', 'APIKEY', 'MONKEY', 'PATH', 'MAILER']) {
            expect(isSensitiveKey(key), key).toBe(false);
        }
    });
});

describe('hasUrlCredentials', () => {
    it('reconhece URL com usuario e senha', () => {
        expect(hasUrlCredentials('mysql://root:segredo@db/app')).toBe(true);
        expect(hasUrlCredentials('https://user:pass@host/path')).toBe(true);
    });

    it('nao acusa URL sem senha', () => {
        expect(hasUrlCredentials('https://token@host/path')).toBe(false);
        expect(hasUrlCredentials('redis://localhost:6379')).toBe(false);
        expect(hasUrlCredentials('smtp.example.com')).toBe(false);
    });
});

describe('maskEnv', () => {
    const ENV = [
        '# app',
        'APP_NAME=Demo',
        '',
        'APP_KEY=base64:abc123',
        'DB_PASSWORD="p@ss # word"  # banco',
        'DATABASE_URL=mysql://root:secret@db/app',
        'MAIL_HOST=smtp.example.com',
        'AUTHOR=Ana',
        'LINHA QUEBRADA',
    ].join('\n');

    it('esvazia so o valor sensivel e preserva o resto', () => {
        const result = mascarar(ENV);

        expect(result.output).toBe(
            [
                '# app',
                'APP_NAME=Demo',
                '',
                'APP_KEY=',
                'DB_PASSWORD=""  # banco',
                'DATABASE_URL=',
                'MAIL_HOST=smtp.example.com',
                'AUTHOR=Ana',
                'LINHA QUEBRADA',
            ].join('\n'),
        );
    });

    it('lista as chaves mascaradas e as linhas invalidas', () => {
        const result = mascarar(ENV);

        expect(result.masked).toEqual([
            { key: 'APP_KEY', line: 4 },
            { key: 'DB_PASSWORD', line: 5 },
            { key: 'DATABASE_URL', line: 6 },
        ]);
        expect(result.invalid).toEqual([{ line: 9, raw: 'LINHA QUEBRADA' }]);
    });

    it('mantem o export e a aspa simples', () => {
        const result = mascarar("export DB_PASSWORD='x'");

        expect(result.output).toBe("export DB_PASSWORD=''");
        expect(result.masked).toEqual([{ key: 'DB_PASSWORD', line: 1 }]);
    });

    it('nao lista chave sensivel que ja estava vazia', () => {
        const result = mascarar('APP_KEY=\nSECRET=""');

        expect(result.output).toBe('APP_KEY=\nSECRET=""');
        expect(result.masked).toEqual([]);
    });

    it('mascara valor multi-linha numa linha', () => {
        const result = mascarar('PRIVATE_KEY="BEGIN\nmiolo\nEND"\nNEXT=1');

        expect(result.output).toBe('PRIVATE_KEY=""\nNEXT=1');
        expect(result.masked).toEqual([{ key: 'PRIVATE_KEY', line: 1 }]);
    });

    it('nao mascara valor inocente com # no meio', () => {
        const result = mascarar('GREETING=oi#tudo # bem');

        expect(result.output).toBe('GREETING=oi#tudo # bem');
        expect(result.masked).toEqual([]);
        expect(result.invalid).toEqual([]);
    });

    it('normaliza CRLF para LF na saida', () => {
        expect(mascarar('A=1\r\nB=2').output).toBe('A=1\nB=2');
    });
});
