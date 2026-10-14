<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthCard from '@/components/AuthCard.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import SocialAuthButtons from '@/components/SocialAuthButtons.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useRoutes } from '@/composables/useRoutes';

const { t } = useI18n();
const routes = useRoutes();

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();
</script>

<template>
    <Head :title="t('auth.login.title')" />

    <AuthCard
        :title="t('auth.login.title')"
        :description="t('auth.login.description')"
    >
        <p
            v-if="status"
            class="mb-5 rounded-md border border-brand/40 bg-brand/5 px-3 py-2 text-sm"
            role="status"
            aria-live="polite"
        >
            {{ status }}
        </p>

        <SocialAuthButtons />

        <div class="my-5 flex items-center gap-3">
            <span class="h-px flex-1 bg-border" aria-hidden="true" />
            <span class="text-xs tracking-wide text-muted-foreground uppercase">
                {{ t('auth.social.divider') }}
            </span>
            <span class="h-px flex-1 bg-border" aria-hidden="true" />
        </div>

        <Form
            v-bind="routes.form.login.value"
            :reset-on-success="['password']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-5"
        >
            <div class="grid gap-4">
                <div class="grid gap-2">
                    <Label for="email">{{ t('auth.login.email') }}</Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        required
                        v-focus
                        autocomplete="email"
                        :placeholder="t('auth.login.email_placeholder')"
                        :aria-invalid="errors.email ? 'true' : undefined"
                        aria-describedby="email-error"
                    />
                    <InputError id="email-error" :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <div class="flex items-center justify-between gap-2">
                        <Label for="password">
                            {{ t('auth.login.password') }}
                        </Label>
                        <TextLink
                            v-if="canResetPassword"
                            :href="routes.forgotPassword.value"
                            class="text-sm"
                        >
                            {{ t('auth.login.forgot') }}
                        </TextLink>
                    </div>
                    <PasswordInput
                        id="password"
                        name="password"
                        required
                        autocomplete="current-password"
                        :placeholder="t('auth.login.password_placeholder')"
                        :aria-invalid="errors.password ? 'true' : undefined"
                        aria-describedby="password-error"
                    />
                    <InputError
                        id="password-error"
                        :message="errors.password"
                    />
                </div>

                <Label
                    for="remember"
                    class="flex items-center gap-3 font-normal"
                >
                    <Checkbox id="remember" name="remember" value="1" />
                    <span>{{ t('auth.login.remember') }}</span>
                </Label>
            </div>

            <Button
                type="submit"
                class="w-full"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                {{ t('auth.login.submit') }}
            </Button>

            <p class="text-center text-sm text-muted-foreground">
                {{ t('auth.login.no_account') }}
                <TextLink :href="routes.register.value">
                    {{ t('auth.login.register') }}
                </TextLink>
            </p>
        </Form>
    </AuthCard>
</template>
