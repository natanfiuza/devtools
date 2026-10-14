import { describe, expect, it } from 'vitest';
import {
    FALLBACK_LOCALE,
    applyTranslations,
    createI18nInstance,
    readLocaleProps,
    syncI18n,
} from '@/lib/i18n';
import { swapLocaleInPath } from '@/composables/useLocale';
import type { Locale } from '@/types';

const locales: Locale[] = [
    { code: 'en', slug: 'en', html: 'en', dir: 'ltr', name: 'English' },
    {
        code: 'pt_BR',
        slug: 'pt-br',
        html: 'pt-BR',
        dir: 'ltr',
        name: 'Portugues (Brasil)',
    },
];

describe('readLocaleProps', () => {
    it('le as props de idioma compartilhadas', () => {
        expect(
            readLocaleProps({
                locale: 'pt_BR',
                locales,
                translations: { app: { 'nav.tools': 'Ferramentas' } },
            }),
        ).toEqual({
            locale: 'pt_BR',
            locales,
            translations: { app: { 'nav.tools': 'Ferramentas' } },
        });
    });

    it('ignora props ausentes ou com tipo errado', () => {
        expect(readLocaleProps({})).toEqual({
            locale: undefined,
            locales: undefined,
            translations: undefined,
        });

        expect(
            readLocaleProps({
                locale: 42,
                locales: 'en',
                translations: ['app'],
            }),
        ).toEqual({
            locale: undefined,
            locales: undefined,
            translations: undefined,
        });
    });
});

describe('applyTranslations', () => {
    it('troca o idioma e as mensagens do namespace', () => {
        const instance = createI18nInstance();

        applyTranslations(instance, {
            locale: 'pt_BR',
            translations: { app: { nav: { tools: 'Ferramentas' } } },
        });

        expect(instance.global.locale.value).toBe('pt_BR');
        expect(instance.global.t('app.nav.tools')).toBe('Ferramentas');
    });

    it('substitui o namespace inteiro a cada navegacao', () => {
        const instance = createI18nInstance();

        applyTranslations(instance, {
            locale: 'pt_BR',
            translations: { app: { a: 'antigo' }, home: { title: 'Inicio' } },
        });
        applyTranslations(instance, {
            locale: 'pt_BR',
            translations: { app: { b: 'novo' } },
        });

        // O servidor manda o namespace completo: o que saiu, saiu.
        expect(instance.global.t('app.b')).toBe('novo');
        expect(instance.global.t('app.a')).toBe('app.a');
        expect(instance.global.t('home.title')).toBe('Inicio');
    });

    it('mantem as mensagens do idioma anterior', () => {
        const instance = createI18nInstance();

        applyTranslations(instance, {
            locale: 'pt_BR',
            translations: { app: { ola: 'Ola' } },
        });
        applyTranslations(instance, {
            locale: 'en',
            translations: { app: { ola: 'Hello' } },
        });

        expect(instance.global.t('app.ola')).toBe('Hello');

        // Voltar nao passa pelo servidor: a mensagem ja aplicada continua la.
        // (Para ler outro idioma use { locale } nas opcoes: em v11 o segundo
        // argumento string e a mensagem padrao, nao o idioma.)
        applyTranslations(instance, { locale: 'pt_BR' });

        expect(instance.global.t('app.ola')).toBe('Ola');
    });

    it('so troca o idioma quando nao vem traducao', () => {
        const instance = createI18nInstance();

        applyTranslations(instance, {
            locale: 'pt_BR',
            translations: { app: { ola: 'Ola' } },
        });
        applyTranslations(instance, { locale: 'pt_BR' });

        expect(instance.global.t('app.ola')).toBe('Ola');
    });

    it('cai no idioma de fallback quando nao vem locale', () => {
        const instance = createI18nInstance();

        applyTranslations(instance, {});

        expect(instance.global.locale.value).toBe(FALLBACK_LOCALE);
    });
});

describe('syncI18n', () => {
    it('sincroniza o idioma e o atributo lang do <html>', () => {
        const instance = createI18nInstance();
        const element = document.createElement('html');

        syncI18n(
            { locale: 'pt_BR', locales, translations: { app: { ola: 'Ola' } } },
            element,
            instance,
        );

        expect(instance.global.t('app.ola')).toBe('Ola');
        expect(element.lang).toBe('pt-BR');
    });

    it('usa o codigo do idioma quando a lista de idiomas nao veio', () => {
        const element = document.createElement('html');

        syncI18n({ locale: 'pt_BR' }, element, createI18nInstance());

        expect(element.lang).toBe('pt_BR');
    });

    it('nao toca no documento quando nao ha elemento (SSR)', () => {
        const instance = createI18nInstance();

        expect(() =>
            syncI18n({ locale: 'pt_BR' }, null, instance),
        ).not.toThrow();

        expect(instance.global.locale.value).toBe('pt_BR');
    });
});

describe('swapLocaleInPath', () => {
    const slugs = ['en', 'pt-br', 'pt-pt', 'fr', 'es'];

    it('troca o idioma do primeiro segmento', () => {
        expect(swapLocaleInPath('/pt-br/styleguide', slugs, 'fr')).toBe(
            '/fr/styleguide',
        );
        expect(swapLocaleInPath('/pt-br', slugs, 'es')).toBe('/es');
    });

    it('preserva os demais segmentos', () => {
        expect(swapLocaleInPath('/pt-br/tools/json/format', slugs, 'en')).toBe(
            '/en/tools/json/format',
        );
    });

    it('ignora barras extras e preserva o resto do caminho', () => {
        expect(swapLocaleInPath('pt-br/styleguide', slugs, 'fr')).toBe(
            '/fr/styleguide',
        );
        expect(swapLocaleInPath('//pt-br/', slugs, 'fr')).toBe('/fr/');
    });

    it('devolve null quando o caminho nao tem idioma', () => {
        expect(swapLocaleInPath('/login', slugs, 'fr')).toBeNull();
        expect(swapLocaleInPath('/settings/profile', slugs, 'fr')).toBeNull();
        expect(swapLocaleInPath('/', slugs, 'fr')).toBeNull();
    });
});
