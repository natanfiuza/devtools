<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AuthCard from '@/components/AuthCard.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import SocialAuthButtons from '@/components/SocialAuthButtons.vue';
import TextLink from '@/components/TextLink.vue';
import TurnstileWidget from '@/components/TurnstileWidget.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { useRoutes } from '@/composables/useRoutes';
import type { Locale } from '@/types';

const { t } = useI18n();
const routes = useRoutes();
const page = usePage();

defineProps<{
    status?: string;
    passwordRules: string;
}>();

/**
 * Idioma principal da conta, ja pre-selecionado com o idioma da pagina - quem
 * nao mexer no campo fica com o idioma em que se cadastrou. Nao troca a
 * interface: o cookie do idioma de navegacao continua como esta (ADR-026).
 */
const locale = ref(page.props.locale);

const locales = computed<Locale[]>(() => page.props.locales ?? []);

/**
 * O token do Turnstile e de uso unico. Quando o cadastro volta com erro, o
 * widget precisa de um desafio novo - senao o proximo envio carrega um token
 * ja consumido e falha de novo (ADR-052).
 */
const turnstile = ref<{ reset: () => void } | null>(null);
</script>

<template>
  <Head :title="t('auth.register.title')" />

  <AuthCard
    :title="t('auth.register.title')"
    :description="t('auth.register.description')"
  >
    <p
      v-if="status"
      class="mb-5 rounded-md border border-brand/40 bg-brand/5 px-3 py-2 text-sm"
      role="status"
      aria-live="polite"
    >
      {{ status }}
    </p>

    <SocialAuthButtons action="register" />

    <div class="my-5 flex items-center gap-3">
      <span
        class="h-px flex-1 bg-border"
        aria-hidden="true"
      />
      <span class="text-xs tracking-wide text-muted-foreground uppercase">
        {{ t('auth.social.divider') }}
      </span>
      <span
        class="h-px flex-1 bg-border"
        aria-hidden="true"
      />
    </div>

    <Form
      v-slot="{ errors, processing }"
      v-bind="routes.form.register.value"
      :reset-on-success="['password', 'password_confirmation']"
      class="flex flex-col gap-5"
      @error="turnstile?.reset()"
    >
      <div class="grid gap-4">
        <div class="grid gap-2">
          <Label for="name">{{ t('auth.register.name') }}</Label>
          <Input
            id="name"
            v-focus
            type="text"
            name="name"
            required
            autocomplete="name"
            :placeholder="t('auth.register.name_placeholder')"
            :aria-invalid="errors.name ? 'true' : undefined"
            aria-describedby="name-error"
          />
          <InputError
            id="name-error"
            :message="errors.name"
          />
        </div>

        <div class="grid gap-2">
          <Label for="email">{{ t('auth.register.email') }}</Label>
          <Input
            id="email"
            type="email"
            name="email"
            required
            autocomplete="email"
            :placeholder="t('auth.register.email_placeholder')"
            :aria-invalid="errors.email ? 'true' : undefined"
            aria-describedby="email-error"
          />
          <InputError
            id="email-error"
            :message="errors.email"
          />
        </div>

        <div class="grid gap-2">
          <Label for="password">
            {{ t('auth.register.password') }}
          </Label>
          <PasswordInput
            id="password"
            name="password"
            required
            autocomplete="new-password"
            :passwordrules="passwordRules"
            :aria-invalid="errors.password ? 'true' : undefined"
            aria-describedby="password-error"
          />
          <InputError
            id="password-error"
            :message="errors.password"
          />
        </div>

        <div class="grid gap-2">
          <Label for="password_confirmation">
            {{ t('auth.register.password_confirmation') }}
          </Label>
          <PasswordInput
            id="password_confirmation"
            name="password_confirmation"
            required
            autocomplete="new-password"
            :passwordrules="passwordRules"
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

        <div class="grid gap-2">
          <Label for="locale">{{ t('auth.register.locale') }}</Label>
          <Select
            v-model="locale"
            name="locale"
          >
            <SelectTrigger
              id="locale"
              class="w-full"
              aria-describedby="locale-help"
            >
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem
                v-for="option in locales"
                :key="option.code"
                :value="option.code"
              >
                <span :lang="option.html">{{
                  option.name
                }}</span>
              </SelectItem>
            </SelectContent>
          </Select>
          <p
            id="locale-help"
            class="text-xs leading-relaxed text-muted-foreground"
          >
            {{ t('auth.register.locale_help') }}
          </p>
          <InputError
            id="locale-error"
            :message="errors.locale"
          />
        </div>

        <Label
          for="newsletter"
          class="flex items-start gap-3 text-sm leading-snug font-normal"
        >
          <!-- value="1": o CreateNewUser valida a newsletter como
                         boolean, e o valor padrao do checkbox e "on", que a
                         regra recusa. -->
          <Checkbox
            id="newsletter"
            name="newsletter"
            value="1"
            class="mt-0.5"
          />
          <span>{{ t('auth.register.newsletter') }}</span>
        </Label>
      </div>

      <TurnstileWidget ref="turnstile" />

      <Button
        type="submit"
        class="w-full"
        :disabled="processing"
        data-test="register-user-button"
      >
        <Spinner v-if="processing" />
        {{ t('auth.register.submit') }}
      </Button>

      <p
        class="text-center text-xs leading-relaxed text-muted-foreground"
      >
        <i18n-t
          keypath="auth.register.terms"
          tag="span"
        >
          <template #terms>
            <TextLink :href="routes.terms.value">
              {{ t('auth.register.terms_link') }}
            </TextLink>
          </template>
          <template #privacy>
            <TextLink :href="routes.privacy.value">
              {{ t('auth.register.privacy_link') }}
            </TextLink>
          </template>
        </i18n-t>
      </p>

      <p class="text-center text-sm text-muted-foreground">
        {{ t('auth.register.have_account') }}
        <TextLink :href="routes.login.value">
          {{ t('auth.register.login') }}
        </TextLink>
      </p>
    </Form>
  </AuthCard>
</template>
