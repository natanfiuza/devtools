<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useRoutes } from '@/composables/useRoutes';

/**
 * Primeiro passo do double opt-in (ADR-024): a pagina so mostra o botao; quem
 * confirma e o POST. Link de scanner de e-mail abre a pagina e nao inscreve
 * ninguem sem querer.
 */
const { t } = useI18n();
const routes = useRoutes();

const props = defineProps<{
    token: string;
    state: 'invalid' | 'pending' | 'confirmed';
}>();

const key = computed<string>(() => `newsletter.confirm.title_${props.state}`);
const bodyKey = computed<string>(
    () => `newsletter.confirm.body_${props.state}`,
);
</script>

<template>
    <Head :title="t(key)" />

    <div class="mx-auto w-full max-w-lg px-4 py-16">
        <h1 class="text-2xl font-medium tracking-tight">{{ t(key) }}</h1>
        <p class="mt-3 text-muted-foreground">
            {{ t(bodyKey) }}
        </p>

        <Form
            v-if="state === 'pending'"
            v-bind="routes.form.newsletterConfirm(token)"
            v-slot="{ processing }"
            class="mt-6"
        >
            <Button
                type="submit"
                :disabled="processing"
                data-test="newsletter-confirm-button"
            >
                <Spinner v-if="processing" />
                {{ t('newsletter.confirm.action') }}
            </Button>
        </Form>

        <p v-else class="mt-6">
            <TextLink :href="routes.home.value">
                {{ t('newsletter.back_home') }}
            </TextLink>
        </p>
    </div>
</template>
