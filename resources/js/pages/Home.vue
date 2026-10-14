<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Sparkles, Wrench } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import SearchTrigger from '@/components/SearchTrigger.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useRoutes } from '@/composables/useRoutes';

/**
 * Home da F0: o esqueleto do DESIGN (hero, busca, blocos de status) sem o
 * grid de tools, que entra na F1 junto com o registro de ferramentas.
 */
const { t } = useI18n();
const routes = useRoutes();
</script>

<template>
    <Head :title="t('app.nav.tools')" />

    <div class="mx-auto w-full max-w-[1120px] px-4 py-16 sm:py-24">
        <div class="max-w-3xl">
            <h1
                class="text-4xl leading-tight font-semibold tracking-tight sm:text-[56px]"
            >
                {{ t('home.title') }}
            </h1>
            <p class="mt-4 text-lg text-muted-foreground">
                {{ t('home.subtitle') }}
            </p>

            <div class="mt-8 max-w-md">
                <SearchTrigger class="flex" />
                <p class="mt-2 text-sm text-subtle">
                    {{ t('home.search_hint') }}
                </p>
            </div>
        </div>

        <div class="mt-16 grid gap-4 md:grid-cols-3">
            <Card>
                <CardHeader>
                    <Wrench class="size-5 text-brand" aria-hidden="true" />
                    <CardTitle class="mt-2">
                        {{ t('home.status_title') }}
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <CardDescription>
                        {{ t('home.status_body') }}
                    </CardDescription>
                    <Button variant="outline" size="sm" class="mt-4" as-child>
                        <Link :href="routes.styleguide.value">
                            {{ t('app.nav.styleguide') }}
                        </Link>
                    </Button>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <Sparkles class="size-5 text-accent-2" aria-hidden="true" />
                    <CardTitle class="mt-2">
                        {{ t('home.ai_title') }}
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <CardDescription>
                        {{ t('home.ai_body') }}
                    </CardDescription>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
