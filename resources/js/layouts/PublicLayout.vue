<script setup lang="ts">
import { ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { Menu, Search } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import AnalyticsScript from '@/components/AnalyticsScript.vue';
import AppWordmark from '@/components/AppWordmark.vue';
import CommandPalette from '@/components/CommandPalette.vue';
import ConsentBanner from '@/components/ConsentBanner.vue';
import LocaleSwitcher from '@/components/LocaleSwitcher.vue';
import NewsletterForm from '@/components/NewsletterForm.vue';
import SearchTrigger from '@/components/SearchTrigger.vue';
import ThemeToggle from '@/components/ThemeToggle.vue';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { useCommandPalette } from '@/composables/useCommandPalette';
import { useConsent } from '@/composables/useConsent';
import { useRoutes } from '@/composables/useRoutes';
import { dashboard } from '@/routes';

/**
 * Layout do site publico (DESIGN.md): container 1120px, header de 56px
 * sticky com blur, footer minimo.
 *
 * Os links do header sao Tools, Planos e Styleguide. "Planos" e ancora da
 * secao de precos da home (nao ha pagina de precos); Docs entra na F7.
 *
 * A palette de busca e montada aqui, uma vez por pagina: o gatilho do header,
 * o do hero e o item do menu mobile abrem a mesma.
 */
const { t } = useI18n();
const page = usePage();
const routes = useRoutes();
const { open: openSearch } = useCommandPalette();
const { open: openConsent } = useConsent();

const menuOpen = ref(false);

/** Fecha o menu mobile antes de abrir a palette (senao os dois ficam abertos). */
const openSearchFromMenu = (): void => {
    menuOpen.value = false;
    openSearch();
};

const year = new Date().getFullYear();

const isAuthenticated = (): boolean => page.props.auth?.user != null;
</script>

<template>
    <div class="flex min-h-screen flex-col">
        <a
            href="#conteudo"
            class="sr-only rounded-md bg-surface px-3 py-2 text-sm focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:outline-2 focus:outline-offset-2 focus:outline-ring"
        >
            {{ t('app.actions.skip_to_content') }}
        </a>

        <header
            class="sticky top-0 z-40 border-b bg-background/80 backdrop-blur"
        >
            <div
                class="mx-auto flex h-14 w-full max-w-[1120px] items-center gap-4 px-4"
            >
                <Link
                    :href="routes.home.value"
                    class="rounded-md focus:outline-2 focus:outline-offset-2 focus:outline-ring"
                >
                    <AppWordmark />
                    <span class="sr-only">{{ t('app.brand') }}</span>
                </Link>

                <nav
                    class="hidden items-center gap-1 md:flex"
                    :aria-label="t('app.nav.tools')"
                >
                    <Button variant="ghost" size="sm" as-child>
                        <Link :href="routes.home.value">
                            {{ t('app.nav.tools') }}
                        </Link>
                    </Button>
                    <Button variant="ghost" size="sm" as-child>
                        <Link :href="`${routes.home.value}#precos`">
                            {{ t('app.nav.pricing') }}
                        </Link>
                    </Button>
                    <Button variant="ghost" size="sm" as-child>
                        <Link :href="routes.styleguide.value">
                            {{ t('app.nav.styleguide') }}
                        </Link>
                    </Button>
                </nav>

                <div class="ml-auto flex items-center gap-2">
                    <SearchTrigger class="hidden lg:flex" />
                    <LocaleSwitcher />
                    <ThemeToggle />

                    <Button
                        v-if="!isAuthenticated()"
                        size="sm"
                        as-child
                        class="hidden md:inline-flex"
                    >
                        <Link :href="routes.login.value">
                            {{ t('app.actions.login') }}
                        </Link>
                    </Button>
                    <Button
                        v-else
                        size="sm"
                        as-child
                        class="hidden md:inline-flex"
                    >
                        <Link :href="dashboard().url">
                            {{ t('app.actions.dashboard') }}
                        </Link>
                    </Button>

                    <Sheet v-model:open="menuOpen">
                        <SheetTrigger as-child>
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                class="md:hidden"
                                :aria-label="t('app.actions.open_menu')"
                            >
                                <Menu class="size-4" aria-hidden="true" />
                            </Button>
                        </SheetTrigger>
                        <SheetContent side="right" class="w-64">
                            <SheetHeader>
                                <SheetTitle>
                                    <AppWordmark size="sm" />
                                </SheetTitle>
                            </SheetHeader>
                            <nav
                                class="flex flex-col gap-1 px-4"
                                :aria-label="t('app.actions.open_menu')"
                            >
                                <Button
                                    variant="ghost"
                                    class="justify-start"
                                    @click="openSearchFromMenu()"
                                >
                                    <Search
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                    {{ t('app.actions.search') }}
                                </Button>
                                <Button
                                    variant="ghost"
                                    class="justify-start"
                                    as-child
                                    @click="menuOpen = false"
                                >
                                    <Link :href="routes.home.value">
                                        {{ t('app.nav.tools') }}
                                    </Link>
                                </Button>
                                <Button
                                    variant="ghost"
                                    class="justify-start"
                                    as-child
                                    @click="menuOpen = false"
                                >
                                    <Link :href="`${routes.home.value}#precos`">
                                        {{ t('app.nav.pricing') }}
                                    </Link>
                                </Button>
                                <Button
                                    variant="ghost"
                                    class="justify-start"
                                    as-child
                                    @click="menuOpen = false"
                                >
                                    <Link :href="routes.styleguide.value">
                                        {{ t('app.nav.styleguide') }}
                                    </Link>
                                </Button>
                                <Button
                                    v-if="!isAuthenticated()"
                                    variant="ghost"
                                    class="justify-start"
                                    as-child
                                    @click="menuOpen = false"
                                >
                                    <Link :href="routes.login.value">
                                        {{ t('app.actions.login') }}
                                    </Link>
                                </Button>
                                <Button
                                    v-else
                                    variant="ghost"
                                    class="justify-start"
                                    as-child
                                    @click="menuOpen = false"
                                >
                                    <Link :href="dashboard().url">
                                        {{ t('app.actions.dashboard') }}
                                    </Link>
                                </Button>
                            </nav>
                        </SheetContent>
                    </Sheet>
                </div>
            </div>
        </header>

        <main id="conteudo" class="flex-1">
            <slot />
        </main>

        <footer class="border-t">
            <div
                class="mx-auto flex w-full max-w-[1120px] flex-col gap-8 px-4 py-8 text-sm text-muted-foreground sm:flex-row sm:justify-between"
            >
                <div class="flex flex-col gap-3">
                    <p>
                        &copy; {{ year }} {{ t('app.brand') }} &middot;
                        {{ t('app.footer.rights') }}
                    </p>

                    <nav
                        class="flex flex-wrap items-center gap-x-4 gap-y-1"
                        :aria-label="t('app.footer.language')"
                    >
                        <Link
                            :href="routes.privacy.value"
                            class="hover:text-foreground"
                        >
                            {{ t('app.footer.privacy') }}
                        </Link>
                        <Link
                            :href="routes.terms.value"
                            class="hover:text-foreground"
                        >
                            {{ t('app.footer.terms') }}
                        </Link>
                        <button
                            type="button"
                            data-test="consent-reopen"
                            class="hover:text-foreground"
                            @click="openConsent()"
                        >
                            {{ t('app.footer.cookies') }}
                        </button>
                    </nav>

                    <div class="flex items-center gap-2">
                        <span class="sr-only">
                            {{ t('app.footer.language') }}
                        </span>
                        <LocaleSwitcher />
                    </div>
                </div>

                <NewsletterForm />
            </div>
        </footer>

        <ConsentBanner />
        <AnalyticsScript />
        <CommandPalette />
    </div>
</template>
