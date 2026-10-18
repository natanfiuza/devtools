<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useRoutes } from '@/composables/useRoutes';

/**
 * Descadastro em dois passos, como a confirmacao: o GET mostra o botao e o POST
 * aplica. O token vive no cadastro, entao nao expira - e o link continua valendo
 * em qualquer e-mail antigo.
 */
const { t } = useI18n();
const routes = useRoutes();

const props = defineProps<{
    token: string;
    state: 'invalid' | 'pending' | 'unsubscribed';
}>();

const key = computed<string>(
    () => `newsletter.unsubscribe.title_${props.state}`,
);
const bodyKey = computed<string>(
    () => `newsletter.unsubscribe.body_${props.state}`,
);
</script>

<template>
    <Head :title="t(key)" />

    <div class="mx-auto w-full max-w-lg px-4 py-16">
        <h1 class="text-2xl font-medium tracking-tight">
            {{ t(key) }}
        </h1>
        <p class="mt-3 text-muted-foreground">
            {{ t(bodyKey) }}
        </p>

        <Form
            v-if="state === 'pending'"
            v-slot="{ processing }"
            v-bind="routes.form.newsletterUnsubscribe(token)"
            class="mt-6"
        >
            <Button
                type="submit"
                variant="outline"
                :disabled="processing"
                data-test="newsletter-unsubscribe-button"
            >
                <Spinner v-if="processing" />
                {{ t('newsletter.unsubscribe.action') }}
            </Button>
        </Form>

        <p v-else class="mt-6">
            <TextLink :href="routes.home.value">
                {{ t('newsletter.back_home') }}
            </TextLink>
        </p>
    </div>
</template>
