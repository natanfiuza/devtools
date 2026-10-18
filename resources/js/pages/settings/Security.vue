<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TwoFactorAuthentication from '@/components/TwoFactorAuthentication.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

type Props = {
    passwordRules: string;
};

const props = defineProps<Props>();

const { t } = useI18n();
</script>

<template>
    <Head :title="t('settings.security.title')" />

    <div class="space-y-6">
        <Heading
            variant="small"
            :title="t('settings.security.title')"
            :description="t('settings.security.description')"
        />

        <Form
            v-bind="SecurityController.update.form()"
            :options="{
                preserveScroll: true,
            }"
            reset-on-success
            :reset-on-error="[
                'password',
                'password_confirmation',
                'current_password',
            ]"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="current_password">
                    {{ t('settings.security.current_password') }}
                </Label>
                <PasswordInput
                    id="current_password"
                    name="current_password"
                    class="mt-1 block w-full"
                    autocomplete="current-password"
                    :placeholder="
                        t('settings.security.current_password_placeholder')
                    "
                />
                <InputError :message="errors.current_password" />
            </div>

            <div class="grid gap-2">
                <Label for="password">
                    {{ t('settings.security.password') }}
                </Label>
                <PasswordInput
                    id="password"
                    name="password"
                    class="mt-1 block w-full"
                    autocomplete="new-password"
                    :placeholder="t('settings.security.password_placeholder')"
                    :passwordrules="props.passwordRules"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">
                    {{ t('settings.security.password_confirmation') }}
                </Label>
                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    class="mt-1 block w-full"
                    autocomplete="new-password"
                    :placeholder="
                        t('settings.security.password_confirmation_placeholder')
                    "
                    :passwordrules="props.passwordRules"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <div class="flex items-center gap-4">
                <Button
                    :disabled="processing"
                    data-test="update-password-button"
                >
                    {{ t('settings.security.save') }}
                </Button>
            </div>
        </Form>

        <TwoFactorAuthentication />
    </div>
</template>
