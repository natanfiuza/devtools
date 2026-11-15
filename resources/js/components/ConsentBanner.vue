<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Cookie } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { useConsent } from '@/composables/useConsent';
import { useRoutes } from '@/composables/useRoutes';

/**
 * Banner de consentimento (ADR-013): canto inferior, sem bloquear a leitura -
 * a pagina continua navegavel por tras dele.
 *
 * Quem decide se ele aparece e o `useConsent`: sem decisao ele esta na tela, e
 * depois de uma decisao so o botao "Cookies" do rodape o traz de volta. Os dois
 * botoes e o interruptor da medicao escrevem a mesma decisao - nao ha "salvar"
 * separado, senao a escolha poderia ficar so na tela.
 *
 * "Necessarios" nao tem controle: e o que faz o site funcionar (sessao, idioma
 * e tema) e nao ha o que desligar.
 */
const { t } = useI18n();
const routes = useRoutes();
const { consent, bannerOpen, accept, reject } = useConsent();

/** O interruptor reflete a decisao: sem resposta ele fica indefinido. */
const analyticsChecked = computed<boolean | 'indeterminate'>(() =>
    consent.value === null ? 'indeterminate' : consent.value === 'granted',
);

const toggleAnalytics = (value: boolean | 'indeterminate'): void => {
    if (value === true) {
        accept();

        return;
    }

    reject();
};
</script>

<template>
    <section
        v-if="bannerOpen"
        data-test="consent-banner"
        role="region"
        :aria-label="t('app.consent.title')"
        class="fixed inset-x-4 bottom-4 z-50 flex flex-col gap-3 rounded-lg border bg-surface p-4 text-sm shadow-lg sm:inset-x-auto sm:left-4 sm:w-96"
    >
        <div class="flex items-start gap-3">
            <Cookie
                class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                aria-hidden="true"
            />
            <div class="flex flex-col gap-1">
                <h2 class="font-medium">
                    {{ t('app.consent.title') }}
                </h2>
                <p class="text-muted-foreground">
                    {{ t('app.consent.body') }}
                </p>
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            <Button size="sm" data-test="consent-accept" @click="accept()">
                {{ t('app.consent.accept') }}
            </Button>
            <Button
                size="sm"
                variant="outline"
                data-test="consent-reject"
                @click="reject()"
            >
                {{ t('app.consent.reject') }}
            </Button>
        </div>

        <details data-test="consent-details">
            <summary
                class="cursor-pointer text-muted-foreground hover:text-foreground"
            >
                {{ t('app.consent.details') }}
            </summary>

            <div class="mt-3 flex flex-col gap-3">
                <div
                    data-test="consent-necessary"
                    class="flex flex-col gap-0.5"
                >
                    <span class="font-medium">
                        {{ t('app.consent.necessary') }}
                    </span>
                    <span class="text-muted-foreground">
                        {{ t('app.consent.necessary_body') }}
                    </span>
                </div>

                <label
                    data-test="consent-analytics"
                    class="flex items-start gap-3"
                >
                    <Checkbox
                        data-test="consent-analytics-toggle"
                        :model-value="analyticsChecked"
                        class="mt-0.5"
                        @update:model-value="toggleAnalytics"
                    />
                    <span class="flex flex-col gap-0.5">
                        <span class="font-medium">
                            {{ t('app.consent.analytics') }}
                        </span>
                        <span class="text-muted-foreground">
                            {{ t('app.consent.analytics_body') }}
                        </span>
                    </span>
                </label>

                <Link
                    data-test="consent-privacy"
                    :href="routes.privacy.value"
                    class="text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                >
                    {{ t('app.consent.privacy') }}
                </Link>
            </div>
        </details>
    </section>
</template>
