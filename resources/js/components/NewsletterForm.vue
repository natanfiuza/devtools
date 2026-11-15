<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useRoutes } from '@/composables/useRoutes';

/**
 * Inscricao na newsletter pelo rodape do site (ADR-024).
 *
 * Sao dois passos: aqui so entra o e-mail e o servidor manda o link de
 * confirmacao. A resposta aparece como aviso do sistema (flash), entao o
 * formulario nao guarda estado proprio - so limpa o campo quando da certo.
 */
const { t } = useI18n();
const routes = useRoutes();
</script>

<template>
    <div class="w-full sm:max-w-xs">
        <p class="text-sm font-medium text-foreground">
            {{ t('newsletter.form.label') }}
        </p>
        <p class="mt-1 text-xs text-muted-foreground">
            {{ t('newsletter.form.description') }}
        </p>

        <Form
            v-slot="{ errors, processing }"
            v-bind="routes.form.newsletter.value"
            :reset-on-success="['email']"
            class="mt-3 flex items-start gap-2"
        >
            <div class="grid flex-1 gap-1">
                <Label for="newsletter-email" class="sr-only">
                    {{ t('newsletter.form.email') }}
                </Label>
                <Input
                    id="newsletter-email"
                    type="email"
                    name="email"
                    required
                    autocomplete="email"
                    :placeholder="t('newsletter.form.email_placeholder')"
                    :aria-invalid="errors.email ? 'true' : undefined"
                    aria-describedby="newsletter-email-error"
                />
                <InputError
                    id="newsletter-email-error"
                    :message="errors.email"
                />
            </div>

            <Button
                type="submit"
                variant="outline"
                :disabled="processing"
                data-test="newsletter-subscribe-button"
            >
                <Spinner v-if="processing" />
                {{ t('newsletter.form.submit') }}
            </Button>
        </Form>
    </div>
</template>
