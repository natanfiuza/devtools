<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AuthCard from '@/components/AuthCard.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useRoutes } from '@/composables/useRoutes';

const { t } = useI18n();
const routes = useRoutes();

const props = defineProps<{
    /**
     * `false` quando a conta nasceu de um login social e ainda nao tem senha:
     * a tela vira "defina sua senha", com a confirmacao junto (ADR-023).
     */
    hasPassword: boolean;
}>();

const title = computed<string>(() =>
    t(props.hasPassword ? 'auth.confirm.title' : 'auth.confirm.set_title'),
);
const description = computed<string | undefined>(() =>
    props.hasPassword ? undefined : t('auth.confirm.set_description'),
);
</script>

<template>
    <Head :title="title" />

    <AuthCard :title="title" :description="description">
        <Form
            v-bind="
                hasPassword
                    ? routes.form.confirmPassword.value
                    : routes.form.setPassword.value
            "
            reset-on-success
            v-slot="{ errors, processing }"
            class="flex flex-col gap-5"
        >
            <div class="grid gap-4">
                <div class="grid gap-2">
                    <Label for="password">
                        {{
                            t(
                                hasPassword
                                    ? 'auth.confirm.password'
                                    : 'auth.confirm.set_password',
                            )
                        }}
                    </Label>
                    <PasswordInput
                        id="password"
                        name="password"
                        required
                        autofocus
                        :autocomplete="
                            hasPassword ? 'current-password' : 'new-password'
                        "
                        :aria-invalid="errors.password ? 'true' : undefined"
                        aria-describedby="password-error"
                    />
                    <InputError
                        id="password-error"
                        :message="errors.password"
                    />
                </div>

                <div v-if="!hasPassword" class="grid gap-2">
                    <Label for="password_confirmation">
                        {{ t('auth.confirm.set_password_confirmation') }}
                    </Label>
                    <PasswordInput
                        id="password_confirmation"
                        name="password_confirmation"
                        required
                        autocomplete="new-password"
                        :aria-invalid="
                            errors.password_confirmation ? 'true' : undefined
                        "
                        aria-describedby="password-confirmation-error"
                    />
                    <InputError
                        id="password-confirmation-error"
                        :message="errors.password_confirmation"
                    />
                </div>
            </div>

            <Button
                type="submit"
                class="w-full"
                :disabled="processing"
                data-test="confirm-password-button"
            >
                <Spinner v-if="processing" />
                {{ t(`auth.confirm.${hasPassword ? 'submit' : 'set_submit'}`) }}
            </Button>
        </Form>
    </AuthCard>
</template>
