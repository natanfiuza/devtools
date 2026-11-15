<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AnalyticsScript from '@/components/AnalyticsScript.vue';
import AppWordmark from '@/components/AppWordmark.vue';
import ConsentBanner from '@/components/ConsentBanner.vue';
import LocaleSwitcher from '@/components/LocaleSwitcher.vue';
import ThemeToggle from '@/components/ThemeToggle.vue';
import { useRoutes } from '@/composables/useRoutes';

/**
 * Moldura das telas de acesso (DESIGN.md, secao "Tela de acesso"): barra de
 * 56px com o wordmark e os controles de idioma e tema, conteudo centrado na
 * coluna de 384px e rodape de uma linha com as paginas legais.
 *
 * A moldura nao escreve titulo: quem o faz e o `AuthCard`, dentro da pagina.
 * Assim cada tela decide o proprio cabecalho - inclusive trocando-o conforme o
 * estado, como a de confirmar senha - sem depender de props de layout.
 *
 * O banner de consentimento e a tag de medicao (ADR-013) entram aqui tambem:
 * quem cai direto numa tela de acesso precisa poder decidir na mesma hora. O
 * rodape desta moldura continua de uma linha - quem reabre o banner pelo
 * rodape e a home, e a decisao ja tomada vale para as telas de acesso.
 */
const { t } = useI18n();
const routes = useRoutes();

const year = new Date().getFullYear();
</script>

<template>
    <div class="flex min-h-svh flex-col bg-background">
        <a
            href="#conteudo"
            class="sr-only rounded-md px-3 py-2 text-sm focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50"
        >
            {{ t('app.actions.skip_to_content') }}
        </a>

        <header
            class="sticky top-0 z-40 border-b bg-background/80 backdrop-blur"
        >
            <div
                class="mx-auto flex h-14 w-full max-w-[1120px] items-center gap-2 px-4"
            >
                <Link :href="routes.home.value" class="rounded-md">
                    <AppWordmark />
                    <span class="sr-only">{{ t('app.brand') }}</span>
                </Link>

                <div class="ml-auto flex items-center gap-2">
                    <LocaleSwitcher />
                    <ThemeToggle />
                </div>
            </div>
        </header>

        <main
            id="conteudo"
            class="flex flex-1 items-center justify-center px-4 py-10"
        >
            <div class="w-full max-w-sm">
                <slot />
            </div>
        </main>

        <footer class="border-t">
            <div
                class="mx-auto flex w-full max-w-[1120px] flex-wrap items-center justify-center gap-x-4 gap-y-1 px-4 py-4 text-sm text-muted-foreground"
            >
                <span>&copy; {{ year }} {{ t('app.brand') }}</span>
                <Link
                    :href="routes.privacy.value"
                    class="hover:text-foreground"
                >
                    {{ t('app.footer.privacy') }}
                </Link>
                <Link :href="routes.terms.value" class="hover:text-foreground">
                    {{ t('app.footer.terms') }}
                </Link>
            </div>
        </footer>

        <ConsentBanner />
        <AnalyticsScript />
    </div>
</template>
