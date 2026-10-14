<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { useRoutes } from '@/composables/useRoutes';
import type { PublicPlan } from '@/types';

/**
 * Secao de precos da home.
 *
 * Os planos vem da tabela `plans` (props), formatados no servidor: nao ha
 * preco nem limite no codigo. A compra e manual (ADR-027) - por isso o plano
 * pago chama o contato por e-mail em vez de abrir um checkout -, e o gratuito
 * chama o cadastro.
 *
 * O nome do plano e coluna de dado e aparece igual nos 5 idiomas; o resto da
 * linha (unidade, "por mes", "Gratis") e texto de idioma.
 */
defineProps<{ plans: PublicPlan[]; contactEmail: string | null }>();

const { t } = useI18n();
const routes = useRoutes();

/** Linhas do card, na ordem: franquia, schema e tamanho da descricao. */
function features(plan: PublicPlan): string[] {
    return [
        plan.isFree
            ? t('home.pricing.features.uses', { count: plan.lifetimeFreeUses })
            : t('home.pricing.features.tokens', { count: plan.monthlyTokens }),
        t('home.pricing.features.schema', { size: plan.schemaSize }),
        t('home.pricing.features.prompt', { count: plan.promptChars }),
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
            <h3 class="text-base font-medium">{{ plan.name }}</h3>

            <p class="mt-2 text-[32px] leading-tight font-semibold tracking-[-0.02em]">
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

            <Button v-if="plan.isFree" class="mt-6" as-child>
                <Link :href="routes.register.value">
                    {{ t('home.cta.register') }}
                </Link>
            </Button>
            <Button
                v-else-if="contactEmail !== null"
                class="mt-6"
                variant="outline"
                as-child
            >
                <a
                    :href="`mailto:${contactEmail}`"
                    data-test="plan-contact"
                >
                    {{ t('home.cta.contact') }}
                </a>
            </Button>
            <!-- Sem contato configurado a venda nao tem por onde comecar: o
                 botao desabilitado diz isso melhor que um link quebrado. -->
            <Button v-else class="mt-6" variant="outline" disabled>
                {{ t('home.cta.contact') }}
            </Button>
        </li>
    </ul>
</template>
