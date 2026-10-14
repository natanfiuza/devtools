<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { History } from '@lucide/vue';
import { useI18n } from 'vue-i18n';

/**
 * Historico de uso da conta.
 *
 * A tela existe para a navegacao da conta ficar completa - e para o estado
 * vazio dizer a verdade: ainda nao ha ferramenta que consuma cota (elas chegam
 * na F5). Quando existir consumo, ele entra aqui, com data e gasto.
 */
const { t } = useI18n();
</script>

<template>
    <Head :title="t('account.history.title')" />

    <div class="space-y-6">
        <header>
            <h2 class="text-lg font-medium">
                {{ t('account.history.title') }}
            </h2>
            <p class="mt-1 text-sm text-subtle">
                {{ t('account.history.description') }}
            </p>
        </header>

        <div
            class="rounded-lg border border-border px-4 py-12 text-center"
            data-test="historico-vazio"
        >
            <History class="mx-auto size-5 text-subtle" aria-hidden="true" />
            <p class="mt-3 text-sm font-medium">
                {{ t('account.history.empty_title') }}
            </p>
            <p class="mx-auto mt-1 max-w-md text-sm text-subtle">
                {{ t('account.history.empty') }}
            </p>
        </div>
    </div>
</template>
