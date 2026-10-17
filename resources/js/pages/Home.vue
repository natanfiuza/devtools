<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Sparkles } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import PageHead from '@/components/PageHead.vue';
import PricingSection from '@/components/PricingSection.vue';
import SearchTrigger from '@/components/SearchTrigger.vue';
import ToolGrid from '@/components/ToolGrid.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useRoutes } from '@/composables/useRoutes';
import { allTools } from '@/tools/registry';
import type { PublicPlan } from '@/types';

/**
 * Home publica (DESIGN): hero com a busca, grid de ferramentas por categoria,
 * AI SQL Builder em destaque e precos.
 *
 * O grid sai do registro de tools (`resources/js/tools`), o mesmo que alimenta
 * a busca Cmd+K e o sitemap; os planos saem da tabela `plans` (props). A pagina
 * nao conhece ferramenta nem preco: so compoe.
 */
defineProps<{
    /**
     * Catalogo ativo, ja formatado no idioma da tela. `plans` e `contactEmail`
     * vem do `HomeController`.
     */
    plans: PublicPlan[];
    contactEmail: string | null;
}>();

const { t } = useI18n();
const routes = useRoutes();

/** O registro inteiro: a F1 nao tem filtro de visibilidade a fazer aqui. */
const tools = allTools;
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

                    <Button class="mt-6" as-child>
                        <Link :href="routes.register.value">
                            {{ t('home.cta.register') }}
                        </Link>
                    </Button>
                </div>

                <!-- Preview estatico do que a tool faz: texto, nao uma demo -
                     a ferramenta de verdade entra na F5. -->
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

        <section id="precos" class="mt-20 scroll-mt-20">
            <h2 class="text-[32px] font-semibold tracking-[-0.02em]">
                {{ t('home.pricing.title') }}
            </h2>
            <p class="mt-2 max-w-2xl text-muted-foreground">
                {{ t('home.pricing.subtitle') }}
            </p>

            <PricingSection :plans="plans" :contact-email="contactEmail" />
        </section>
    </div>
</template>
