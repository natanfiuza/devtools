<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthCard from '@/components/AuthCard.vue';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useRoutes } from '@/composables/useRoutes';

const { t } = useI18n();
const routes = useRoutes();

defineProps<{
    status?: string;
}>();
</script>

<template>
  <Head :title="t('auth.forgot.title')" />

  <AuthCard
    :title="t('auth.forgot.title')"
    :description="t('auth.forgot.description')"
  >
    <p
      v-if="status"
      class="mb-5 rounded-md border border-brand/40 bg-brand/5 px-3 py-2 text-sm"
      role="status"
      aria-live="polite"
    >
      {{ status }}
    </p>

    <Form
      v-slot="{ errors, processing }"
      v-bind="routes.form.forgotPassword.value"
      class="flex flex-col gap-5"
    >
      <div class="grid gap-2">
        <Label for="email">{{ t('auth.forgot.email') }}</Label>
        <Input
          id="email"
          v-focus
          type="email"
          name="email"
          required
          autocomplete="email"
          :placeholder="t('auth.forgot.email_placeholder')"
          :aria-invalid="errors.email ? 'true' : undefined"
          aria-describedby="email-error"
        />
        <InputError
          id="email-error"
          :message="errors.email"
        />
      </div>

      <Button
        type="submit"
        class="w-full"
        :disabled="processing"
        data-test="email-password-reset-link-button"
      >
        <Spinner v-if="processing" />
        {{ t('auth.forgot.submit') }}
      </Button>

      <p class="text-center text-sm text-muted-foreground">
        <TextLink :href="routes.login.value">
          {{ t('auth.forgot.back') }}
        </TextLink>
      </p>
    </Form>
  </AuthCard>
</template>
