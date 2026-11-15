import { createI18n } from 'vue-i18n';
import type { LocaleMessageValue, VueMessageType } from 'vue-i18n';
import { describe, expect, it } from 'vitest';
import { exportMessages, type NamespaceMessages } from './support/langMessages';

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
 * O PHP e lido direto por `support/langMessages.ts`, como o
 * HandleInertiaRequests faz: config/locales.php e um arquivo por namespace em
 * lang/{locale}/, sem bootstrap do Laravel.
 */

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

    it('interpolam ator e paleta nas notificacoes reais dos cinco idiomas', () => {
        // O vue-i18n interpola com `{nome}`; o Laravel, com `:nome`. Chave com a
        // sintaxe do Laravel nao quebra o render: o `t()` devolve o texto com
        // `:actor`/`:palette` literais na tela. O teste da pagina finge as
        // traducoes nessa sintaxe, por isso o texto real e guardado aqui
        // (achado da F8; sem esta guarda o defeito volta silencioso).
        const exported = exportMessages();

        const failures: string[] = [];
        const withActorAndPalette = [
            'palette_liked',
            'palette_comment_approved',
            'comment_reply_approved',
        ];

        for (const [locale, namespaces] of Object.entries(exported)) {
            const i18n = createI18n({
                legacy: false,
                locale,
                fallbackLocale: false,
                messages: {
                    [locale]: namespaces,
                } as unknown as Record<
                    string,
                    Record<string, LocaleMessageValue<VueMessageType>>
                >,
                missingWarn: false,
                fallbackWarn: false,
                warnHtmlMessage: false,
            });

            for (const key of withActorAndPalette) {
                const text = i18n.global.t(`notifications.kinds.${key}`, {
                    actor: 'ATOR',
                    palette: 'PALETA',
                });

                if (
                    !text.includes('ATOR') ||
                    !text.includes('PALETA') ||
                    text.includes(':actor') ||
                    text.includes(':palette')
                ) {
                    failures.push(`${locale}/notifications.${key}: ${text}`);
                }
            }

            // `user_followed` fala do ator e nunca da paleta.
            const followed = i18n.global.t(
                'notifications.kinds.user_followed',
                { actor: 'ATOR', palette: 'PALETA' },
            );

            if (
                !followed.includes('ATOR') ||
                followed.includes('PALETA') ||
                followed.includes(':actor')
            ) {
                failures.push(
                    `${locale}/notifications.user_followed: ${followed}`,
                );
            }

            const anonymous = i18n.global.t(
                'notifications.kinds.palette_liked_anonymous',
                { actor: 'ATOR', palette: 'PALETA' },
            );

            if (
                !anonymous.includes('PALETA') ||
                anonymous.includes('ATOR') ||
                anonymous.includes(':palette')
            ) {
                failures.push(
                    `${locale}/notifications.palette_liked_anonymous: ${anonymous}`,
                );
            }
        }

        expect(failures).toEqual([]);
    });
});
