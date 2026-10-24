import { computed, type ComputedRef } from 'vue';
import { usePage } from '@inertiajs/vue3';
import {
    contact,
    donate,
    home,
    login,
    logout,
    pricing,
    privacy,
    register,
    styleguide,
    subscribe,
    terms,
} from '@/routes';
import { store as contactStore } from '@/routes/contact';
import { store as donateStore } from '@/routes/donate';
import { status as donationStatusRoute } from '@/routes/donate';
import { store as loginStore } from '@/routes/login';
import { store as registerStore } from '@/routes/register';
import { store as subscribeStore } from '@/routes/subscribe';
import { store as subscribeCheckoutStore } from '@/routes/subscribe/checkout';
import {
    email as forgotPasswordAction,
    request as forgotPassword,
    set as setPasswordAction,
    update as resetPasswordAction,
} from '@/routes/password';
import { store as confirmPasswordAction } from '@/routes/password/confirm';
import { send as resendVerificationAction } from '@/routes/verification';
import { subscribe as newsletterAction } from '@/routes/newsletter';
import { store as newsletterConfirmAction } from '@/routes/newsletter/confirm';
import { store as newsletterUnsubscribeAction } from '@/routes/newsletter/unsubscribe';
import type { RouteFormDefinition } from '@/wayfinder';

/** Acao de formulario (`action` + `method`) pronta para `v-bind` no `<Form>`. */
type FormAction = ComputedRef<RouteFormDefinition<'post'>>;

export type UseRoutesReturn = {
    /** Slug do idioma atual (`pt-br`). */
    localeSlug: ComputedRef<string>;

    home: ComputedRef<string>;
    pricing: ComputedRef<string>;
    styleguide: ComputedRef<string>;
    privacy: ComputedRef<string>;
    terms: ComputedRef<string>;
    donate: ComputedRef<string>;
    contact: ComputedRef<string>;
    subscribe: (plan?: string) => string;
    /** Pagina de status da doacao: o ULID e o identificador publico. */
    donationStatus: (ulid: string) => string;

    login: ComputedRef<string>;
    register: ComputedRef<string>;
    logout: ComputedRef<string>;
    forgotPassword: ComputedRef<string>;

    form: {
        login: FormAction;
        register: FormAction;
        forgotPassword: FormAction;
        resetPassword: FormAction;
        confirmPassword: FormAction;
        setPassword: FormAction;
        resendVerification: FormAction;
        newsletter: FormAction;
        donate: FormAction;
        contact: FormAction;
        subscribe: ComputedRef<RouteFormDefinition<'post'>>;
        subscribeCheckout: ComputedRef<RouteFormDefinition<'post'>>;
        /** O token do e-mail faz parte da URL, entao vem por parametro. */
        newsletterConfirm: (token: string) => RouteFormDefinition<'post'>;
        newsletterUnsubscribe: (token: string) => RouteFormDefinition<'post'>;
    };
};

/**
 * Rotas com o idioma atual ja injetado.
 *
 * O auth inteiro e as paginas publicas sao prefixados pelo idioma (ADR-022),
 * entao `locale` e parametro obrigatorio no wayfinder. Aqui ele vem das props
 * compartilhadas: o componente escreve `routes.home.value` e nao se preocupa
 * com o idioma. Os callbacks de URL (`auth/{provider}/...`) ficam de fora de
 * proposito: sao rotas de maquina, sem prefixo.
 *
 * No template, ref de topo e desembrulhada sozinha; dentro de um objeto, use
 * `.value` (`routes.form.login.value`).
 */
export function useRoutes(): UseRoutesReturn {
    const page = usePage();

    // Fallback defensivo: a pagina de erro pode ser a primeira renderizacao de
    // uma requisicao que nao passou pelo middleware do Inertia. O ingles e o
    // idioma de fallback do site (config/locales.php).
    const slug = computed<string>(() => page.props.localeSlug ?? 'en');

    const url = (
        route: (args: { locale: string }) => { url: string },
    ): ComputedRef<string> =>
        computed<string>(() => route({ locale: slug.value }).url);

    const form = (route: {
        form: (args: { locale: string }) => RouteFormDefinition<'post'>;
    }): FormAction =>
        computed<RouteFormDefinition<'post'>>(() =>
            route.form({ locale: slug.value }),
        );

    return {
        localeSlug: slug,

        home: url(home),
        pricing: url(pricing),
        styleguide: url(styleguide),
        privacy: url(privacy),
        terms: url(terms),
        donate: url(donate),
        contact: url(contact),
        subscribe: (plan?: string) =>
            subscribe.url(plan ? { query: { plan } } : undefined),
        donationStatus: (ulid: string) =>
            donationStatusRoute.url({
                locale: slug.value,
                donation: ulid,
            }),

        login: url(login),
        register: url(register),
        logout: url(logout),
        forgotPassword: url(forgotPassword),

        form: {
            login: form(loginStore),
            register: form(registerStore),
            forgotPassword: form(forgotPasswordAction),
            resetPassword: form(resetPasswordAction),
            confirmPassword: form(confirmPasswordAction),
            setPassword: form(setPasswordAction),
            resendVerification: form(resendVerificationAction),
            newsletter: form(newsletterAction),
            donate: form(donateStore),
            contact: form(contactStore),
            subscribe: computed<RouteFormDefinition<'post'>>(() =>
                subscribeStore.form(),
            ),
            subscribeCheckout: computed<RouteFormDefinition<'post'>>(() =>
                subscribeCheckoutStore.form(),
            ),
            newsletterConfirm: (token: string) =>
                newsletterConfirmAction.form({ locale: slug.value, token }),
            newsletterUnsubscribe: (token: string) =>
                newsletterUnsubscribeAction.form({ locale: slug.value, token }),
        },
    };
}
