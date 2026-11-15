<script setup lang="ts">
import { onMounted, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useConsent } from '@/composables/useConsent';
import { denyAnalytics, loadAnalytics } from '@/lib/analytics';

/**
 * Tag do GA4 (ADR-013). Componente sem marcacao: existe para a tag ser
 * injetada no cliente, uma unica vez e so depois do aceite.
 *
 * A recusa depois do aceite (o rodape reabre o banner) para a medicao pelo
 * Consent Mode: o gtag.js ja rodou e tirar o <script> nao desfaria nada.
 *
 * O SSR nao ve nada disto - `onMounted` so roda no navegador -, e sem id
 * configurado em config/analytics.php nao ha o que injetar.
 */
const page = usePage();
const { consent } = useConsent();

const apply = (): void => {
    if (consent.value === 'denied') {
        denyAnalytics();

        return;
    }

    if (consent.value !== 'granted') {
        return;
    }

    const id = page.props.analytics?.ga4Id ?? null;

    if (id !== null) {
        loadAnalytics(id);
    }
};

onMounted(apply);
watch(consent, apply);
</script>

<template>
    <!-- Sem marcacao: a tag entra no <head> por lib/analytics.ts. O `v-if`
         falso satisfaz o root do template sem desenhar nada. -->
    <span v-if="false" hidden aria-hidden="true" />
</template>
