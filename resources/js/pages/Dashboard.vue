<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import dashboardRoutes from '@/routes/dashboard';
import type { AccountOverviewProps } from '@/types';

/**
 * Visao geral da conta.
 *
 * Responde as tres perguntas de quem entra - em que plano estou, quanto ainda
 * cabe e quando vence - e aponta para o resto. O que a tela mostra vem pronto
 * do controlador: nenhuma regra de cota e recalculada aqui.
 */
const props = defineProps<AccountOverviewProps>();

const { t } = useI18n();
const page = usePage();

/** Numero no idioma de quem esta lendo: 2000000 vira 2.000.000 em pt-BR. */
const numero = computed<Intl.NumberFormat['format']>(
    () => new Intl.NumberFormat(page.props.locale.replace('_', '-')).format,
);

const unidade = computed<string>(() =>
    props.quota.unit === 'tokens'
        ? t('account.overview.unit_tokens')
        : t('account.overview.unit_uses'),
);

/**
 * Janela da cota: o plano gratuito vale para sempre, o pago vale ate o fim do
 * periodo. Esgotada, a linha vira o aviso - e o que a pessoa precisa ler.
 */
const janela = computed<string>(() => {
    if (props.quota.exhausted) {
        return t('account.overview.quota_exhausted');
    }

    if (props.quota.windowStart === null) {
        return t('account.overview.quota_lifetime');
    }

    return t('account.overview.quota_until', {
        date: props.quota.windowEnd ?? '',
    });
});

/** A venda e manual (ADR-027); o rotulo do gateway so existe para `manual`. */
const cobranca = computed<string>(() => {
    if (props.subscription === null) {
        return t('account.overview.subscription_none');
    }

    return props.subscription.gateway === 'manual'
        ? t('account.overview.subscription_manual')
        : props.subscription.gateway;
});
</script>

<template>
  <Head :title="t('account.overview.title')" />

  <div class="space-y-6">
    <header>
      <h2 class="text-lg font-medium">
        {{ t('account.overview.title') }}
      </h2>
      <p class="mt-1 text-sm text-subtle">
        {{ t('account.overview.description') }}
      </p>
    </header>

    <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <div class="rounded-lg border border-border p-4">
        <dt class="text-[12px] tracking-wide text-subtle uppercase">
          {{ t('account.overview.plan') }}
        </dt>
        <dd
          class="mt-2 font-mono text-2xl"
          data-test="conta-plano"
        >
          {{ plan.name }}
        </dd>
        <p class="mt-1 text-xs text-subtle">
          {{
            plan.isFree
              ? t('account.overview.plan_free')
              : plan.price
          }}
        </p>
      </div>

      <div class="rounded-lg border border-border p-4">
        <dt class="text-[12px] tracking-wide text-subtle uppercase">
          {{ t('account.overview.quota') }}
        </dt>
        <dd
          class="mt-2 font-mono text-2xl"
          data-test="conta-restante"
        >
          {{ numero(quota.remaining) }}
          <span class="text-sm text-subtle">{{ unidade }}</span>
        </dd>
        <p class="mt-1 text-xs text-subtle">
          {{ t('account.overview.quota_limit') }}:
          {{ numero(quota.limit) }} &middot;
          {{ t('account.overview.quota_used') }}:
          {{ numero(quota.used) }}
        </p>
        <p
          class="mt-1 text-xs"
          :class="
            quota.exhausted ? 'text-destructive' : 'text-subtle'
          "
          data-test="conta-janela"
        >
          {{ janela }}
        </p>
      </div>

      <div class="rounded-lg border border-border p-4">
        <dt class="text-[12px] tracking-wide text-subtle uppercase">
          {{ t('account.overview.subscription') }}
        </dt>
        <dd
          class="mt-2 text-sm"
          data-test="conta-assinatura"
        >
          {{ cobranca }}
        </dd>
        <p
          v-if="subscription"
          class="mt-1 text-xs text-subtle"
        >
          {{ subscription.start }} &ndash; {{ subscription.end }}
        </p>
      </div>

      <div class="rounded-lg border border-border p-4">
        <dt class="text-[12px] tracking-wide text-subtle uppercase">
          {{ t('account.overview.next_due') }}
        </dt>
        <dd
          class="mt-2 font-mono text-sm"
          data-test="conta-vencimento"
        >
          {{ subscription?.end ?? t('account.overview.no_due') }}
        </dd>
      </div>
    </dl>

    <section
      class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border p-4"
    >
      <div>
        <p class="text-[12px] tracking-wide text-subtle uppercase">
          {{ t('account.overview.payments') }}
        </p>
        <p class="mt-1 font-mono text-lg">
          {{ payments }}
        </p>
      </div>

      <div class="flex flex-wrap gap-2">
        <Button
          variant="outline"
          size="sm"
          as-child
        >
          <Link :href="dashboardRoutes.billing.url()">
            {{ t('account.overview.billing_link') }}
          </Link>
        </Button>
        <Button
          variant="outline"
          size="sm"
          as-child
        >
          <Link :href="dashboardRoutes.history.url()">
            {{ t('account.overview.history_link') }}
          </Link>
        </Button>
      </div>
    </section>
  </div>
</template>
