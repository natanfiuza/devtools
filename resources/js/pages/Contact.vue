<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Check, Mail, Send } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import PageHead from '@/components/PageHead.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useRoutes } from '@/composables/useRoutes';

const props = defineProps<{
    name: string | null;
    email: string | null;
    status: string | null;
}>();

const { t } = useI18n();
const routes = useRoutes();

const subjects = [
    'general',
    'donation',
    'feedback',
    'bug',
    'other',
] as const;

type SubjectKey = (typeof subjects)[number];

const form = useForm({
    name: props.name ?? '',
    email: props.email ?? '',
    subject: 'general' as SubjectKey,
    message: '',
    hp_website: '',
});

const isSent = ref<boolean>(props.status === 'sent');

const characterCount = computed<number>(() => form.message.length);
const maxCharacters = 3000;

function submit(): void {
    form.post(routes.form.contact.value.action, {
        preserveScroll: true,
        onSuccess: () => {
            isSent.value = true;
            form.reset('message', 'hp_website');
        },
    });
}

function resetForm(): void {
    isSent.value = false;
    form.reset('message', 'hp_website');
}
</script>

<template>
  <PageHead />

  <div class="mx-auto w-full max-w-[1120px] px-4 py-16 sm:py-24">
    <header class="max-w-3xl">
      <h1
        class="text-4xl leading-tight font-semibold tracking-tight sm:text-[48px]"
      >
        {{ t('contact.title') }}
      </h1>
      <p class="mt-4 text-lg text-muted-foreground">
        {{ t('contact.subtitle') }}
      </p>
    </header>

    <div
      v-if="isSent"
      data-test="contact-success-banner"
      class="mt-10 max-w-2xl rounded-xl border border-brand/40 bg-brand/5 p-6"
    >
      <div class="flex items-start gap-3">
        <Check
          class="mt-0.5 size-5 shrink-0 text-brand"
          aria-hidden="true"
        />
        <div>
          <h2 class="text-base font-semibold text-foreground">
            {{ t('contact.success_title') }}
          </h2>
          <p class="mt-2 text-sm text-muted-foreground">
            {{ t('contact.success_body') }}
          </p>
          <div class="mt-4">
            <Button
              type="button"
              variant="outline"
              size="sm"
              data-test="contact-send-another"
              @click="resetForm"
            >
              {{ t('contact.send_another') }}
            </Button>
          </div>
        </div>
      </div>
    </div>

    <form
      v-else
      data-test="contact-form"
      class="mt-10 max-w-2xl space-y-6 rounded-xl border bg-card p-6 sm:p-8"
      @submit.prevent="submit"
    >
      <!-- Honeypot oculto para capturar bots -->
      <div
        class="sr-only"
        aria-hidden="true"
      >
        <label for="hp_website">Website</label>
        <input
          id="hp_website"
          v-model="form.hp_website"
          type="text"
          name="hp_website"
          tabindex="-1"
          autocomplete="off"
        >
      </div>

      <div class="grid gap-6 sm:grid-cols-2">
        <div class="grid gap-2">
          <Label for="contact-name">
            {{ t('contact.fields.name') }}
          </Label>
          <Input
            id="contact-name"
            v-model="form.name"
            data-test="contact-name"
            type="text"
            required
            maxlength="100"
            autocomplete="name"
            :placeholder="t('contact.fields.name_placeholder')"
            :aria-invalid="form.errors.name !== undefined"
          />
          <InputError :message="form.errors.name" />
        </div>

        <div class="grid gap-2">
          <Label for="contact-email">
            {{ t('contact.fields.email') }}
          </Label>
          <Input
            id="contact-email"
            v-model="form.email"
            data-test="contact-email"
            type="email"
            required
            maxlength="255"
            autocomplete="email"
            :placeholder="t('contact.fields.email_placeholder')"
            :aria-invalid="form.errors.email !== undefined"
          />
          <InputError :message="form.errors.email" />
        </div>
      </div>

      <div class="grid gap-2">
        <Label for="contact-subject">
          {{ t('contact.fields.subject') }}
        </Label>
        <Select
          v-model="form.subject"
          name="subject"
        >
          <SelectTrigger
            id="contact-subject"
            class="w-full"
            data-test="contact-subject-trigger"
          >
            <SelectValue :placeholder="t('contact.fields.subject_placeholder')" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="sub in subjects"
              :key="sub"
              :value="sub"
              :data-test="`contact-subject-${sub}`"
            >
              {{ t(`contact.subjects.${sub}`) }}
            </SelectItem>
          </SelectContent>
        </Select>
        <InputError :message="form.errors.subject" />
      </div>

      <div class="grid gap-2">
        <div class="flex items-center justify-between">
          <Label for="contact-message">
            {{ t('contact.fields.message') }}
          </Label>
          <span
            class="font-mono text-xs"
            :class="
              characterCount > maxCharacters
                ? 'text-destructive font-medium'
                : 'text-muted-foreground'
            "
          >
            {{
              t('contact.fields.characters', {
                count: characterCount,
                max: maxCharacters,
              })
            }}
          </span>
        </div>
        <textarea
          id="contact-message"
          v-model="form.message"
          data-test="contact-message"
          rows="6"
          required
          :maxlength="maxCharacters"
          :placeholder="t('contact.fields.message_placeholder')"
          :aria-invalid="form.errors.message !== undefined"
          class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-colors placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
        />
        <p class="text-xs text-muted-foreground">
          {{ t('contact.fields.email_hint') }}
        </p>
        <InputError :message="form.errors.message" />
      </div>

      <div class="pt-2">
        <Button
          type="submit"
          class="w-full sm:w-auto"
          :disabled="form.processing || characterCount > maxCharacters"
          data-test="contact-submit"
        >
          <Send
            class="mr-2 size-4"
            aria-hidden="true"
          />
          {{
            form.processing
              ? t('contact.submitting')
              : t('contact.submit')
          }}
        </Button>
      </div>
    </form>
  </div>
</template>
