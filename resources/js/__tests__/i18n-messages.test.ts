import { execFileSync } from 'node:child_process';
import { resolve } from 'node:path';
import { createI18n } from 'vue-i18n';
import type { LocaleMessageValue, VueMessageType } from 'vue-i18n';
import { describe, expect, it } from 'vitest';

/**
 * Guarda de compilacao do vue-i18n.
 *
 * As traducoes sao dados de runtime: o PHP manda os namespaces em
 * HandleInertiaRequests e o front compila no navegador (e no SSR). Um `{`
 * literal numa traducao vira interpolacao invalida, e o compilador padrao
 * lanca de dentro de `t()` derrubando o render inteiro (ADR-060).
 *
 * Este teste usa `createI18n` PURO, sem o `messageCompiler` tolerante de
 * lib/i18n.ts: com o compilador tolerante a mensagem invalida degradaria para
 * texto cru e o teste passaria com o bug presente.
 *
 * O PHP e lido direto, como HandleInertiaRequests faz: config/locales.php e
 * um arquivo por namespace em lang/{locale}/, sem bootstrap do Laravel.
 */

type NamespaceMessages = Record<string, unknown>;
type ExportedMessages = Record<string, Record<string, NamespaceMessages>>;

const root = resolve(process.cwd());

const phpScript = String.raw`
$root = $argv[1];
$config = require $root . '/config/locales.php';

$messages = [];

foreach (array_keys($config['supported']) as $locale) {
    $messages[$locale] = [];

    foreach ($config['namespaces'] as $namespace) {
        $path = $root . '/lang/' . $locale . '/' . $namespace . '.php';

        if (! is_file($path)) {
            continue;
        }

        $contents = require $path;
        $messages[$locale][$namespace] = is_array($contents) ? $contents : [];
    }
}

echo json_encode(
    $messages,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
);
`;

function exportMessages(): ExportedMessages {
    let raw: string;

    try {
        raw = execFileSync('php', ['-r', phpScript, root], {
            encoding: 'utf8',
            maxBuffer: 32 * 1024 * 1024,
        });
    } catch (error) {
        const stderr =
            error instanceof Error && 'stderr' in error
                ? String(error.stderr ?? '')
                : '';

        throw new Error(
            `Falha ao ler lang/ com php -r (o PHP precisa estar no PATH): ${stderr.trim()}`,
            { cause: error },
        );
    }

    try {
        return JSON.parse(raw) as ExportedMessages;
    } catch (error) {
        throw new Error(
            `Saida invalida do PHP ao ler lang/: ${raw.slice(0, 200)}`,
            { cause: error },
        );
    }
}

/** Chaves folha em notacao de ponto (as chaves de lang/ nao tem ponto). */
function leaves(values: NamespaceMessages, prefix = ''): string[] {
    const keys: string[] = [];

    for (const [key, value] of Object.entries(values)) {
        const full = prefix === '' ? key : `${prefix}.${key}`;

        if (
            value !== null &&
            typeof value === 'object' &&
            !Array.isArray(value)
        ) {
            keys.push(...leaves(value as NamespaceMessages, full));

            continue;
        }

        keys.push(full);
    }

    return keys;
}

describe('mensagens do vue-i18n', () => {
    it('compilam nos cinco idiomas com o compilador estrito', () => {
        const exported = exportMessages();

        // Falha cedo se o PHP devolver vazio ou uma estrutura inesperada.
        expect(Object.keys(exported).sort()).toEqual([
            'en',
            'es',
            'fr',
            'pt_BR',
            'pt_PT',
        ]);

        const failures: string[] = [];
        let total = 0;

        for (const [locale, namespaces] of Object.entries(exported)) {
            const i18n = createI18n({
                legacy: false,
                locale,
                fallbackLocale: false,
                // O JSON do PHP tem o mesmo formato das props do Inertia.
                messages: {
                    [locale]: namespaces,
                } as unknown as Record<
                    string,
                    Record<string, LocaleMessageValue<VueMessageType>>
                >,
                missingWarn: false,
                fallbackWarn: false,
                // Exemplos de regex em tools.* contem HTML; nao e o alvo aqui.
                warnHtmlMessage: false,
            });

            for (const [namespace, messages] of Object.entries(namespaces)) {
                for (const key of leaves(messages)) {
                    total += 1;

                    try {
                        i18n.global.t(`${namespace}.${key}`);
                    } catch (error) {
                        const detail =
                            error instanceof Error
                                ? (error.message.split('\n')[0] ??
                                  error.message)
                                : String(error);

                        failures.push(
                            `${locale}/${namespace}.${key}: ${detail}`,
                        );
                    }
                }
            }
        }

        expect(total).toBeGreaterThan(0);
        expect(failures).toEqual([]);
    });
});
