import {
    CreditCard,
    History,
    KeyRound,
    LayoutGrid,
    Palette,
    ShieldCheck,
    UserRound,
} from '@lucide/vue';
import type { ComputedRef } from 'vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { dashboard } from '@/routes';
import { index as apiTokens } from '@/routes/api-tokens';
import { edit as editAppearance } from '@/routes/appearance';
import { billing, history } from '@/routes/dashboard';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

/** Grupo da navegacao da conta: um titulo e os itens abaixo dele. */
export type AccountNavGroup = {
    label: string;
    items: NavItem[];
};

/**
 * A navegacao da area autenticada - uma so, para a conta inteira.
 *
 * Antes eram duas: a sidebar do starter kit (com um item so) e a navegacao
 * lateral de settings. Agora a Visao geral, o Historico, as Faturas e a secao
 * Conta ficam na mesma lista, e as URLs de settings seguem as mesmas.
 *
 * Os hrefs sao string (e nao o par caminho+metodo do Inertia) porque a secao
 * aberta e decidida comparando caminhos (useActiveSection).
 */
export function useAccountNav(): ComputedRef<AccountNavGroup[]> {
    const { t } = useI18n();

    return computed<AccountNavGroup[]>(() => [
        {
            label: t('account.nav.menu'),
            items: [
                {
                    title: t('account.nav.overview'),
                    href: dashboard.url(),
                    icon: LayoutGrid,
                },
                {
                    title: t('account.nav.history'),
                    href: history.url(),
                    icon: History,
                },
                {
                    title: t('account.nav.billing'),
                    href: billing.url(),
                    icon: CreditCard,
                },
            ],
        },
        {
            label: t('account.nav.settings'),
            items: [
                {
                    title: t('settings.nav.profile'),
                    href: editProfile().url,
                    icon: UserRound,
                },
                {
                    title: t('settings.nav.security'),
                    href: editSecurity().url,
                    icon: ShieldCheck,
                },
                {
                    title: t('settings.nav.appearance'),
                    href: editAppearance().url,
                    icon: Palette,
                },
                {
                    title: t('settings.nav.api_tokens'),
                    href: apiTokens.url(),
                    icon: KeyRound,
                },
            ],
        },
    ]);
}
