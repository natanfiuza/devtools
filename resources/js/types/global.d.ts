import type { Directive } from 'vue';
import type { Auth } from '@/types/auth';
import type { AnalyticsProps, Appearance, Locale, Translations } from '@/types';

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            appearance: Appearance;
            /** Medicao e consentimento (ADR-013). */
            analytics: AnalyticsProps;
            locale: string;
            localeSlug: string;
            localeHtml: string;
            locales: Locale[];
            /** Admin que esta impersonando esta sessao (ADR-025). */
            impersonation: { email: string } | null;
            seo: {
                /** Tela de acesso: pede `noindex` e nada de `hreflang`. */
                noindex: boolean;
            };
            translations: Translations;
            [key: string]: unknown;
        };
    }
}

declare module 'vue' {
    interface GlobalDirectives {
        vFocus: Directive<HTMLElement, boolean | undefined>;
    }

    interface ComponentCustomProperties {
        $inertia: typeof Router;
        $page: Page;
        $headManager: ReturnType<typeof createHeadManager>;
    }
}
