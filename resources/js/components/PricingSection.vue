<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useRoutes } from '@/composables/useRoutes';
import type { PublicPlan } from '@/types';

/**
 * Grade de planos da pagina de precos (`/{locale}/pricing`).
 *
 * Os planos vem da tabela `plans` (props), formatados no servidor: nao ha
 * preco nem limite no codigo. Os planos pagos levam ao formulario de intencao
 * de assinatura (`/subscribe?plan=<slug>`), que exige conta cadastrada
 * (ADR-033), e o gratuito leva ao cadastro.
 */
defineProps<{ plans: PublicPlan[] }>();

const { t } = useI18n();
const routes = useRoutes();

/** Linhas do card: franquia, schema, prompt e vantagens extras nos planos Pro. */
function features(plan: PublicPlan): string[] {
    if (plan.isFree) {
        return [
            t('home.pricing.features.uses', { count: plan.lifetimeFreeUses }),
            t('home.pricing.features.schema', { size: plan.schemaSize }),
            t('home.pricing.features.prompt', { count: plan.promptChars }),
        ];
    }

    return [
        t('home.pricing.features.tokens', { count: plan.monthlyTokens }),
        t('home.pricing.features.schema', { size: plan.schemaSize }),
        t('home.pricing.features.prompt', { count: plan.promptChars }),
        t('home.pricing.features.username'),
        t('home.pricing.features.history'),
        t('home.pricing.features.priority'),
    ];
}
</script>

<template>
    <ul class="mt-8 grid gap-4 md:grid-cols-3">
        <li
            v-for="plan in plans"
            :key="plan.slug"
            :data-test="`plan-card-${plan.slug}`"
            class="flex flex-col rounded-xl border bg-card p-6"
        >
            <div class="flex items-center justify-between gap-2">
                <h3 class="text-base font-medium">
                    {{ plan.name }}
                </h3>
                <Badge
                    v-if="plan.interval === 'year'"
                    variant="secondary"
                    data-test="plan-yearly-badge"
                >
                    {{ t('home.pricing.yearly_badge') }}
                </Badge>
            </div>

            <p
                class="mt-2 text-[32px] leading-tight font-semibold tracking-[-0.02em]"
            >
                {{ plan.isFree ? t('home.pricing.free') : plan.price }}
            </p>
            <p v-if="plan.interval !== null" class="text-sm text-subtle">
                {{ t(`home.pricing.interval.${plan.interval}`) }}
            </p>

            <ul class="mt-6 flex-1 space-y-2 text-sm text-muted-foreground">
                <li
                    v-for="feature in features(plan)"
                    :key="feature"
                    class="flex gap-2"
                >
                    <Check
                        class="mt-0.5 size-4 shrink-0 text-brand"
                        aria-hidden="true"
                    />
                    <span>{{ feature }}</span>
                </li>
            </ul>

            <Button v-if="plan.isFree" class="mt-6" variant="outline" as-child>
                <Link :href="routes.register.value">
                    {{ t('home.cta.register') }}
                </Link>
            </Button>
            <Button v-else class="mt-6" as-child>
                <Link
                    :href="routes.subscribe(plan.slug)"
                    :data-test="`plan-subscribe-${plan.slug}`"
                >
                    {{ t('home.cta.subscribe') }}
                </Link>
            </Button>
        </li>
    </ul>
</template>
