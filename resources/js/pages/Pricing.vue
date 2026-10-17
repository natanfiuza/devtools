<script setup lang="ts">
import { AtSign, Clock, Cpu, Percent, Sparkles } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import PageHead from '@/components/PageHead.vue';
import PricingSection from '@/components/PricingSection.vue';
import type { PublicPlan } from '@/types';

/**
 * Pagina publica de planos (`/{locale}/pricing`).
 *
 * Apresenta o catalogo ativo da tabela `plans` e detalha as vantagens do
 * plano Pro (ADR-033).
 */
defineProps<{
    plans: PublicPlan[];
}>();

const { t } = useI18n();

const advantages = [
    { key: 'username', icon: AtSign },
    { key: 'history', icon: Clock },
    { key: 'quota', icon: Cpu },
    { key: 'priority', icon: Sparkles },
    { key: 'discount', icon: Percent },
] as const;
</script>

<template>
    <PageHead :title="t('home.pricing.title')" />

    <div class="mx-auto w-full max-w-[1120px] px-4 py-16 sm:py-24">
        <header class="max-w-3xl">
            <h1
                class="text-4xl leading-tight font-semibold tracking-tight sm:text-[48px]"
            >
                {{ t('home.pricing.title') }}
            </h1>
            <p class="mt-4 text-lg text-muted-foreground">
                {{ t('home.pricing.subtitle') }}
            </p>
        </header>

        <section class="mt-12" data-test="pricing-plans-section">
            <PricingSection :plans="plans" />
        </section>

        <section class="mt-20" data-test="pricing-advantages-section">
            <h2 class="text-2xl font-semibold tracking-[-0.02em]">
                {{ t('home.pricing.advantages.title') }}
            </h2>
            <p class="mt-2 max-w-2xl text-muted-foreground">
                {{ t('home.pricing.advantages.subtitle') }}
            </p>

            <ul class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <li
                    v-for="item in advantages"
                    :key="item.key"
                    class="flex flex-col gap-2 rounded-xl border bg-surface p-5"
                >
                    <div class="flex items-center gap-2">
                        <component
                            :is="item.icon"
                            class="size-5 shrink-0 text-brand"
                            aria-hidden="true"
                        />
                        <h3 class="text-base font-medium">
                            {{ t(`home.pricing.advantages.items.${item.key}.title`) }}
                        </h3>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        {{ t(`home.pricing.advantages.items.${item.key}.body`) }}
                    </p>
                </li>
            </ul>
        </section>
    </div>
</template>
