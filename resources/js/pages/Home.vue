<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Sparkles } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import PageHead from '@/components/PageHead.vue';
import SearchTrigger from '@/components/SearchTrigger.vue';
import ToolGrid from '@/components/ToolGrid.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useRoutes } from '@/composables/useRoutes';
import { allTools, toolPath } from '@/tools/registry';

/**
 * Home publica (DESIGN): hero com a busca, grid de ferramentas por categoria
 * e AI SQL Builder em destaque.
 *
 * O grid sai do registro de tools (`resources/js/tools`), o mesmo que alimenta
 * a busca Cmd+K e o sitemap; os planos vivem em pagina propria (`Pricing.vue`,
 * `/{locale}/pricing`).
 */
const { t } = useI18n();
const routes = useRoutes();

/** O registro inteiro: a F1 nao tem filtro de visibilidade a fazer aqui. */
const tools = allTools;

/**
 * O CTA do destaque leva a pagina da tool - visitante recebe o recado de login
 * la. O caminho sai do registro, como nos cards: enquanto o manifest nao disser
 * `available`, o destaque volta ao cadastro em vez de linkar para um 404.
 */
const aiTool = allTools.find((tool) => tool.slug === 'ai-sql-builder') ?? null;

const aiHref = computed(() =>
    aiTool === null ? null : toolPath(aiTool, routes.localeSlug.value),
);
</script>

<template>
    <PageHead />

    <div class="mx-auto w-full max-w-[1120px] px-4 py-16 sm:py-24">
        <header class="max-w-3xl">
            <h1
                class="text-4xl leading-tight font-semibold tracking-tight sm:text-[56px]"
            >
                {{ t('home.title') }}
            </h1>
            <p class="mt-4 text-lg text-muted-foreground">
                {{ t('home.subtitle') }}
            </p>

            <div class="mt-8 max-w-xl">
                <SearchTrigger variant="hero" />
            </div>
        </header>

        <section id="tools" class="mt-20 scroll-mt-20">
            <h2 class="text-[32px] font-semibold tracking-[-0.02em]">
                {{ t('home.tools.title') }}
            </h2>
            <p class="mt-2 max-w-2xl text-muted-foreground">
                {{ t('home.tools.subtitle') }}
            </p>

            <ToolGrid :tools="tools" />
        </section>

        <section class="mt-20">
            <div
                class="grid items-center gap-8 rounded-xl border bg-surface p-6 sm:p-10 lg:grid-cols-2"
            >
                <div>
                    <Badge variant="secondary">
                        <Sparkles class="size-3" aria-hidden="true" />
                        {{ t('home.ai.badge') }}
                    </Badge>

                    <h2
                        class="mt-4 text-[32px] font-semibold tracking-[-0.02em]"
                    >
                        {{ t('home.ai.title') }}
                    </h2>
                    <p class="mt-2 text-muted-foreground">
                        {{ t('home.ai.body') }}
                    </p>
                    <p class="mt-4 text-sm text-subtle">
                        {{ t('home.ai.note') }}
                    </p>

                    <div class="mt-6 flex flex-wrap items-center gap-3">
                        <Button v-if="aiHref" as-child>
                            <Link :href="aiHref" data-test="home-ai-cta">
                                {{ t('home.ai.cta') }}
                            </Link>
                        </Button>
                        <Button v-else as-child>
                            <Link :href="routes.register.value">
                                {{ t('home.cta.register') }}
                            </Link>
                        </Button>
                        <Button variant="outline" as-child>
                            <Link :href="routes.pricing.value">
                                {{ t('app.nav.pricing') }}
                            </Link>
                        </Button>
                    </div>
                </div>

                <!-- Exemplo estatico do que a tool faz (o pedido e o SQL que
                     ela devolve). A ferramenta de verdade vive em
                     `/{locale}/tools/ai-sql-builder`. -->
                <figure class="rounded-lg border bg-surface-2 p-4">
                    <p class="text-sm text-subtle">
                        {{ t('home.ai.preview_prompt') }}
                    </p>
                    <pre
                        class="mt-3 overflow-x-auto font-mono text-[13px] leading-relaxed text-foreground"
                    ><code>{{ t('home.ai.preview_sql') }}</code></pre>
                </figure>
            </div>
        </section>
    </div>
</template>
