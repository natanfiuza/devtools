<script setup lang="ts">
import { ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { Menu } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import AppWordmark from '@/components/AppWordmark.vue';
import LocaleSwitcher from '@/components/LocaleSwitcher.vue';
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
import { useRoutes } from '@/composables/useRoutes';
import { dashboard, login } from '@/routes';

/**
 * Layout do site publico (DESIGN.md): container 1120px, header de 56px
 * sticky com blur, footer minimo.
 *
 * Os links Tools e Styleguide sao paginas que existem hoje. Pricing e Docs
 * entram quando as paginas entrarem (F1/F7) -- link morto no header nao.
 */
const { t } = useI18n();
const page = usePage();
const routes = useRoutes();

const menuOpen = ref(false);

const isAuthenticated = (): boolean => page.props.auth?.user != null;
</script>

<template>
    <div class="flex min-h-screen flex-col">
        <a
            href="#conteudo"
            class="bg-surface focus:outline-ring sr-only rounded-md px-3 py-2 text-sm focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:outline-2 focus:outline-offset-2"
        >
            {{ t('app.actions.skip_to_content') }}
        </a>

        <header
            class="bg-background/80 sticky top-0 z-40 border-b backdrop-blur"
        >
            <div
                class="mx-auto flex h-14 w-full max-w-[1120px] items-center gap-4 px-4"
            >
                <Link
                    :href="routes.home.value"
                    class="focus:outline-ring rounded-md focus:outline-2 focus:outline-offset-2"
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
                        <Link :href="login().url">
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
                                    <Link :href="login().url">
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
                class="text-muted-foreground mx-auto flex w-full max-w-[1120px] flex-col gap-3 px-4 py-6 text-sm sm:flex-row sm:items-center sm:justify-between"
            >
                <p>
                    &copy; {{ new Date().getFullYear() }} {{ t('app.brand') }}
                    &middot; {{ t('app.footer.rights') }}
                </p>
                <div class="flex items-center gap-2">
                    <span class="sr-only">{{ t('app.footer.language') }}</span>
                    <LocaleSwitcher />
                </div>
            </div>
        </footer>
    </div>
</template>
