import { createI18n } from 'vue-i18n';
import type { Locale, Translations } from '@/types';

export const FALLBACK_LOCALE = 'en';

/** Props de idioma que o Laravel compartilha em toda pagina. */
export type LocaleProps = {
    /** Codigo do idioma ativo (ex.: pt_BR). */
    locale?: string;
    translations?: Translations;
    locales?: Locale[];
};

/**
 * O vue-i18n espelha as mensagens que o Laravel ja resolveu em lang/.
 * A fonte da verdade continua sendo o PHP: o front so recebe, por namespace,
 * as chaves do locale ativo (ver App\Http\Middleware\HandleInertiaRequests).
 */
export function createI18nInstance() {
    return createI18n({
        legacy: false,
        globalInjection: true,
        locale: FALLBACK_LOCALE,
        fallbackLocale: FALLBACK_LOCALE,
        messages: {},
        // O PHP ja resolve o fallback de chave; aqui nao queremos ruido.
        missingWarn: false,
        fallbackWarn: false,
    });
}

export type I18nInstance = ReturnType<typeof createI18nInstance>;

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

/**
 * Le as props de idioma de um objeto nao tipado (as props compartilhadas
 * chegam como Record<string, unknown> no adapter). Ausencia de chave e
 * normal em testes e em componentes montados fora do Inertia.
 */
export function readLocaleProps(props: Record<string, unknown>): LocaleProps {
    const translations = props.translations;
    const locales = props.locales;

    return {
        locale: typeof props.locale === 'string' ? props.locale : undefined,
        // Checagem rasa: o conteudo vem do proprio servidor, via lang/.
        translations: isRecord(translations)
            ? (translations as Translations)
            : undefined,
        locales: Array.isArray(locales) ? (locales as Locale[]) : undefined,
    };
}

/**
 * Aplica as mensagens do locale ativo numa instancia do vue-i18n.
 * Precisa ser barato e idempotente: roda a cada navegacao.
 */
export function applyTranslations(
    instance: I18nInstance,
    { locale, translations }: LocaleProps,
): void {
    const active = locale ?? FALLBACK_LOCALE;

    if (instance.global.locale.value !== active) {
        instance.global.locale.value = active;
    }

    if (translations === undefined) {
        return;
    }

    // O servidor manda o locale ativo completo, entao cada namespace e
    // substituido inteiro (nao ha o que mesclar).
    const messages = instance.global.getLocaleMessage(active) as Record<
        string,
        unknown
    >;

    instance.global.setLocaleMessage(active, {
        ...messages,
        ...translations,
    });
}

/** Instancia do cliente. No SSR cada requisicao cria a sua (ver ssr.ts). */
export const i18n: I18nInstance = createI18nInstance();

/**
 * Sincroniza o idioma e o atributo lang do <html>.
 * No SSR a instancia e a da requisicao (ver ssr.ts).
 */
export function syncI18n(
    props: Record<string, unknown>,
    documentElement?: HTMLElement | null,
    instance: I18nInstance = i18n,
): void {
    const locale = readLocaleProps(props);

    applyTranslations(instance, locale);

    const html =
        locale.locales?.find((item) => item.code === locale.locale)?.html ??
        locale.locale ??
        FALLBACK_LOCALE;

    if (documentElement != null && documentElement.lang !== html) {
        documentElement.lang = html;
    }
}
