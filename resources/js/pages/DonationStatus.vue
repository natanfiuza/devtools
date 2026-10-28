<script setup lang="ts">
import { Head, Link, router, usePoll } from '@inertiajs/vue3';
import { Check, Clock, Copy, RefreshCw, TriangleAlert } from '@lucide/vue';
import { computed, onUnmounted, ref, watch } from 'vue';
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
 *
 * O auto-refresh relê apenas o banco local - quem confirma é o webhook. O
 * botao "Ja paguei" é a unica chamada ao provedor, sob demanda e com limite
 * por IP (ADR-059).
 */
const props = defineProps<DonationStatusProps>();

const { t } = useI18n();
const routes = useRoutes();

const copied = ref<boolean>(false);
const copyFailed = ref<boolean>(false);
const checking = ref<boolean>(false);
const checkFailed = ref<boolean>(false);

const poll = usePoll(
    Math.max(1000, props.pollMs),
    { only: ['donation'] },
    { autoStart: false, background: 'pause' },
);

const shouldPoll = computed<boolean>(
    () => props.pollMs > 0 && props.donation.status === 'pending',
);

watch(
    shouldPoll,
    (active: boolean) => {
        if (active) {
            poll.start();
        } else {
            poll.stop();
        }
    },
    { immediate: true },
);

onUnmounted(() => poll.stop());

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

/**
 * O motivo da recusa vem pronto do servidor; o codigo cru (`status_detail`)
 * nunca chega aqui. Sem motivo especifico, vale o aviso generico do estado.
 */
const statusHint = computed<string>(
    () =>
        props.donation.failureReason ??
        t(`donations.status.${statusKey.value}_hint`),
);

const showPix = computed<boolean>(
    () =>
        props.donation.status === 'pending' &&
        props.donation.qrCode !== null &&
        props.donation.qrImage !== null,
);

function checkPayment(): void {
    if (checking.value || !props.donation.canCheck) {
        return;
    }

    checking.value = true;
    checkFailed.value = false;

    router.post(
        routes.donationCheck(props.donation.ulid),
        {},
        {
            preserveScroll: true,
            onError: () => {
                checkFailed.value = true;
            },
            onFinish: () => {
                checking.value = false;
            },
        },
    );
}

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
                    <p
                        data-test="donation-status-hint"
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        {{ statusHint }}
                    </p>
                </div>
            </div>

            <div v-if="donation.canCheck" class="mt-6">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    data-test="donation-check"
                    :disabled="checking"
                    :aria-busy="checking"
                    @click="checkPayment"
                >
                    <RefreshCw
                        :class="['size-4', checking ? 'animate-spin' : '']"
                        aria-hidden="true"
                    />
                    {{
                        checking
                            ? t('donations.check.loading')
                            : t('donations.check.button')
                    }}
                </Button>
                <p class="mt-2 text-sm text-muted-foreground">
                    {{ t('donations.check.hint') }}
                </p>
                <p
                    v-if="checkFailed"
                    data-test="donation-check-failed"
                    class="mt-2 text-sm text-destructive"
                    aria-live="polite"
                >
                    {{ t('donations.check.error') }}
                </p>
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
            />

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
                        <Copy class="size-4" aria-hidden="true" />
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
