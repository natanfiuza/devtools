<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Check, Clock, Copy, TriangleAlert } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { useRoutes } from '@/composables/useRoutes';
import type { DonationPublicStatus, DonationStatusProps } from '@/types';

/**
 * Status de uma doacao (`/{locale}/donate/{ulid}`, F6).
 *
 * Publica por link, mas fora do indice: a URL e o identificador da doacao e a
 * pagina nao mostra nome nem e-mail de quem doou. Enquanto o Pix vale, mostra
 * o QR e o copia-e-cola; depois, so o estado final.
 */
const props = defineProps<DonationStatusProps>();

const { t } = useI18n();
const routes = useRoutes();

const copied = ref<boolean>(false);
const copyFailed = ref<boolean>(false);

const tone = computed<string>(() => {
    switch (props.donation.status) {
        case 'paid':
            return 'border-emerald-500/40 bg-emerald-500/5 text-emerald-700';
        case 'pending':
            return 'border-brand/40 bg-brand/5 text-brand';
        default:
            return 'border-amber-500/40 bg-amber-500/5 text-amber-700';
    }
});

const statusIcon = computed(() => {
    switch (props.donation.status) {
        case 'paid':
            return Check;
        case 'pending':
            return Clock;
        default:
            return TriangleAlert;
    }
});

const statusKey = computed<DonationPublicStatus>(() => props.donation.status);

const showPix = computed<boolean>(
    () =>
        props.donation.status === 'pending' &&
        props.donation.qrCode !== null &&
        props.donation.qrImage !== null,
);

async function copyCode(): Promise<void> {
    const code = props.donation.qrCode;

    if (code === null) {
        return;
    }

    try {
        await navigator.clipboard.writeText(code);
        copied.value = true;
        copyFailed.value = false;
    } catch {
        copied.value = false;
        copyFailed.value = true;
    }
}
</script>

<template>
  <Head :title="t('donations.status.title')" />

  <div class="mx-auto w-full max-w-[760px] px-4 py-16 sm:py-24">
    <header>
      <h1
        class="text-3xl leading-tight font-semibold tracking-tight sm:text-4xl"
      >
        {{ t('donations.status.title') }}
      </h1>
    </header>

    <section
      data-test="donation-status"
      :data-status="donation.status"
      :class="['mt-8 rounded-xl border p-6 sm:p-8', tone]"
    >
      <div class="flex items-start gap-3">
        <component
          :is="statusIcon"
          class="mt-0.5 size-5 shrink-0"
          aria-hidden="true"
        />
        <div>
          <h2 class="text-lg font-semibold text-foreground">
            {{ t(`donations.status.${statusKey}`) }}
          </h2>
          <p class="mt-1 text-sm text-muted-foreground">
            {{ t(`donations.status.${statusKey}_hint`) }}
          </p>
        </div>
      </div>

      <dl class="mt-6 space-y-2 text-sm">
        <div class="flex justify-between gap-4">
          <dt class="text-muted-foreground">
            {{ t('donations.status.amount') }}
          </dt>
          <dd class="font-medium text-foreground">
            {{ donation.amount }}
          </dd>
        </div>
        <div
          v-if="donation.createdAt !== null"
          class="flex justify-between gap-4"
        >
          <dt class="text-muted-foreground">
            {{ t('donations.status.created_at') }}
          </dt>
          <dd class="text-foreground">
            {{ donation.createdAt }}
          </dd>
        </div>
        <div
          v-if="
            donation.expiresAt !== null &&
              donation.status === 'pending'
          "
          class="flex justify-between gap-4"
        >
          <dt class="text-muted-foreground">
            {{ t('donations.status.expires_at') }}
          </dt>
          <dd class="text-foreground">
            {{ donation.expiresAt }}
          </dd>
        </div>
      </dl>
    </section>

    <section
      v-if="showPix"
      data-test="donation-pix"
      class="mt-6 rounded-xl border bg-card p-6 sm:p-8"
    >
      <h2 class="text-base font-medium text-foreground">
        {{ t('donations.pix.title') }}
      </h2>
      <p class="mt-2 text-sm text-muted-foreground">
        {{ t('donations.pix.description') }}
      </p>

      <img
        :src="donation.qrImage ?? ''"
        :alt="t('donations.pix.title')"
        data-test="donation-qr"
        class="mx-auto mt-6 size-56 rounded-lg bg-white p-2"
        width="224"
        height="224"
      >

      <div class="mt-6">
        <label
          for="donation-pix-code"
          class="text-sm font-medium text-foreground"
        >
          {{ t('donations.pix.copy') }}
        </label>
        <textarea
          id="donation-pix-code"
          data-test="donation-pix-code"
          readonly
          rows="3"
          :value="donation.qrCode ?? ''"
          class="mt-1 w-full resize-none rounded-md border bg-surface px-3 py-2 font-mono text-xs break-all text-muted-foreground"
        />

        <div class="mt-3 flex flex-wrap items-center gap-3">
          <Button
            type="button"
            variant="outline"
            size="sm"
            data-test="donation-copy"
            @click="copyCode"
          >
            <Copy
              class="size-4"
              aria-hidden="true"
            />
            {{ t('donations.pix.copy') }}
          </Button>
          <a
            v-if="donation.ticketUrl !== null"
            :href="donation.ticketUrl"
            target="_blank"
            rel="noopener noreferrer"
            class="text-sm text-brand hover:underline"
            data-test="donation-ticket"
          >
            {{ t('donations.pix.ticket') }}
          </a>
          <span
            v-if="copied"
            data-test="donation-copied"
            class="text-sm text-emerald-600"
          >
            {{ t('donations.pix.copied') }}
          </span>
          <span
            v-if="copyFailed"
            data-test="donation-copy-failed"
            class="text-sm text-destructive"
          >
            {{ t('donations.pix.copy_failed') }}
          </span>
        </div>
      </div>

      <p class="mt-6 text-sm text-muted-foreground">
        {{
          donation.static
            ? t('donations.pix.static_note')
            : t('donations.pix.dynamic_note')
        }}
      </p>
    </section>

    <p class="mt-8 text-sm text-muted-foreground">
      {{ t('donations.status.receipt') }}
    </p>

    <Link
      :href="routes.donate.value"
      data-test="donation-new"
      class="mt-6 inline-block text-sm text-brand hover:underline"
    >
      {{ t('donations.status.new') }}
    </Link>
  </div>
</template>
