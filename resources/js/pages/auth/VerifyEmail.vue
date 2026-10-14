<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AuthCard from '@/components/AuthCard.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useRoutes } from '@/composables/useRoutes';

const { t } = useI18n();
const routes = useRoutes();
const page = usePage();

/** Conta autenticada nesta tela, entao o e-mail sempre esta nas props. */
const email = computed<string>(() => page.props.auth.user.email);

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head :title="t('auth.verify.title')" />

    <AuthCard :title="t('auth.verify.title')">
        <p
            v-if="status === 'verification-link-sent'"
            class="mb-5 rounded-md border border-brand/40 bg-brand/5 px-3 py-2 text-sm"
            role="status"
            aria-live="polite"
        >
            {{ t('auth.verify.sent') }}
        </p>

        <p class="mb-5 text-sm text-muted-foreground">
            {{ t('auth.verify.description', { email }) }}
        </p>

        <Form
            v-bind="routes.form.resendVerification.value"
            v-slot="{ processing }"
            class="flex flex-col gap-3"
        >
            <Button
                type="submit"
                variant="secondary"
                class="w-full"
                :disabled="processing"
                data-test="resend-verification-button"
            >
                <Spinner v-if="processing" />
                {{ t('auth.verify.resend') }}
            </Button>

            <!-- Sair e POST: o TextLink precisa do metodo explicito. -->
            <TextLink
                :href="routes.logout.value"
                method="post"
                as="button"
                class="mx-auto text-sm"
            >
                {{ t('auth.verify.logout') }}
            </TextLink>
        </Form>
    </AuthCard>
</template>
