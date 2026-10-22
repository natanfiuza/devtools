<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { HeartHandshake, ShieldCheck } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import PageHead from '@/components/PageHead.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useRoutes } from '@/composables/useRoutes';
import type { DonatePageProps } from '@/types';

/**
 * Doacao por Pix (`/{locale}/donate`, F6).
 *
 * Publica e indexavel: doar nao exige conta. O valor sugerido vem do servidor
 * (`config/donations.php`) e o livre e validado de novo no POST. O nome e
 * opcional (doacao anonima e valida) e o e-mail so serve para o recibo.
 *
 * Com credenciais do provedor o Pix e dinamico; sem elas, o fallback estatico
 * continua funcionando, mas sem confirmacao automatica - o texto diz isso.
 */
const props = defineProps<DonatePageProps>();

const { t } = useI18n();
const routes = useRoutes();

const amountCents = ref<number | null>(props.suggested[0]?.cents ?? null);
const custom = ref<string>('');
const anonymous = ref<boolean>(false);

const form = useForm({
    amount_cents: props.suggested[0]?.cents ?? 0,
    name: props.name ?? '',
    email: props.email ?? '',
});

const available = computed<boolean>(() => props.online || props.staticPix);

const chosenCents = computed<number | null>(() => {
    if (amountCents.value !== null) {
        return amountCents.value;
    }

    return parseAmount(custom.value);
});

const amountValid = computed<boolean>(() => {
    const cents = chosenCents.value;

    return cents !== null && cents >= props.minCents && cents <= props.maxCents;
});

const hint = computed<string>(() =>
    t('donations.amount.custom_hint', { min: props.min, max: props.max }),
);

watch(anonymous, (value) => {
    if (value) {
        form.name = '';
    }
});

function parseAmount(value: string): number | null {
    const normalized = value.trim().replace(',', '.');

    if (normalized === '') {
        return null;
    }

    const parsed = Number(normalized);

    return Number.isFinite(parsed) && parsed > 0
        ? Math.round(parsed * 100)
        : null;
}

function selectSuggestion(cents: number): void {
    amountCents.value = cents;
    custom.value = '';
    form.clearErrors('amount_cents');
}

function selectCustom(): void {
    amountCents.value = null;
}

function submit(): void {
    const cents = chosenCents.value;

    if (cents === null || !amountValid.value) {
        form.setError('amount_cents', hint.value);

        return;
    }

    form.amount_cents = cents;
    form.name = anonymous.value ? '' : form.name;
    form.post(routes.form.donate.value.action, { preserveScroll: true });
}
</script>

<template>
    <PageHead />

    <div class="mx-auto w-full max-w-[1120px] px-4 py-16 sm:py-24">
        <header class="max-w-3xl">
            <h1
                class="text-4xl leading-tight font-semibold tracking-tight sm:text-[48px]"
            >
                {{ t('donations.title') }}
            </h1>
            <p class="mt-4 text-lg text-muted-foreground">
                {{ t('donations.subtitle') }}
            </p>
        </header>

        <div
            v-if="!available"
            data-test="donate-unavailable"
            class="mt-10 rounded-xl border border-amber-500/40 bg-amber-500/5 p-6 text-sm"
        >
            <h2 class="text-base font-medium text-foreground">
                {{ t('donations.info.title') }}
            </h2>
            <p class="mt-2 text-muted-foreground">
                {{ t('donations.info.description') }}
            </p>
        </div>

        <form
            v-else
            data-test="donate-form"
            class="mt-10 grid items-start gap-8 lg:grid-cols-12"
            @submit.prevent="submit"
        >
            <div class="rounded-xl border bg-card p-6 sm:p-8 lg:col-span-7">
                <fieldset>
                    <legend class="text-sm font-medium text-foreground">
                        {{ t('donations.amount.label') }}
                    </legend>

                    <div
                        v-if="suggested.length > 0"
                        class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4"
                    >
                        <button
                            v-for="option in suggested"
                            :key="option.cents"
                            type="button"
                            :data-test="`donate-amount-${option.cents}`"
                            :aria-pressed="amountCents === option.cents"
                            :class="[
                                'rounded-xl border px-3 py-3 text-sm font-medium transition-colors',
                                amountCents === option.cents
                                    ? 'border-brand bg-brand/5 text-foreground'
                                    : 'border-border bg-surface text-muted-foreground hover:border-border-strong',
                            ]"
                            @click="selectSuggestion(option.cents)"
                        >
                            {{ option.formatted }}
                        </button>
                    </div>

                    <div class="mt-4">
                        <Label for="donate-custom">
                            {{ t('donations.amount.custom') }}
                        </Label>
                        <Input
                            id="donate-custom"
                            v-model="custom"
                            data-test="donate-custom"
                            type="text"
                            inputmode="decimal"
                            autocomplete="off"
                            :placeholder="hint"
                            :aria-invalid="
                                form.errors.amount_cents !== undefined
                            "
                            class="mt-1"
                            @focus="selectCustom"
                            @input="
                                amountCents = null;
                                form.clearErrors('amount_cents');
                            "
                        />
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ hint }}
                        </p>
                        <InputError
                            class="mt-2"
                            :message="form.errors.amount_cents"
                        />
                    </div>
                </fieldset>

                <fieldset class="mt-8">
                    <legend class="text-sm font-medium text-foreground">
                        {{ t('donations.donor.title') }}
                    </legend>

                    <div class="mt-3 space-y-4">
                        <div class="flex items-start gap-3">
                            <Checkbox
                                id="donate-anonymous"
                                data-test="donate-anonymous"
                                :model-value="anonymous"
                                @update:model-value="
                                    anonymous = $event === true
                                "
                            />
                            <div>
                                <Label for="donate-anonymous">
                                    {{ t('donations.donor.anonymous') }}
                                </Label>
                                <p class="text-xs text-muted-foreground">
                                    {{ t('donations.donor.anonymous_hint') }}
                                </p>
                            </div>
                        </div>

                        <div v-if="!anonymous">
                            <Label for="donate-name">
                                {{ t('donations.donor.name') }}
                            </Label>
                            <Input
                                id="donate-name"
                                v-model="form.name"
                                data-test="donate-name"
                                type="text"
                                autocomplete="name"
                                :placeholder="
                                    t('donations.donor.name_placeholder')
                                "
                                class="mt-1"
                            />
                            <InputError
                                class="mt-2"
                                :message="form.errors.name"
                            />
                        </div>

                        <div>
                            <Label for="donate-email">
                                {{ t('donations.donor.email') }}
                            </Label>
                            <Input
                                id="donate-email"
                                v-model="form.email"
                                data-test="donate-email"
                                type="email"
                                autocomplete="email"
                                class="mt-1"
                            />
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ t('donations.donor.email_hint') }}
                            </p>
                            <InputError
                                class="mt-2"
                                :message="form.errors.email"
                            />
                        </div>
                    </div>
                </fieldset>

                <Button
                    type="submit"
                    data-test="donate-submit"
                    class="mt-8"
                    :disabled="form.processing"
                >
                    {{
                        form.processing
                            ? t('donations.processing')
                            : t('donations.submit')
                    }}
                </Button>
            </div>

            <aside
                class="rounded-xl border bg-surface p-6 text-sm text-muted-foreground lg:col-span-5"
            >
                <div class="flex items-center gap-2 text-foreground">
                    <HeartHandshake
                        class="size-5 text-brand"
                        aria-hidden="true"
                    />
                    <span class="font-medium">{{
                        t('donations.pix.title')
                    }}</span>
                </div>
                <p class="mt-3">
                    {{
                        online
                            ? t('donations.pix.dynamic_note')
                            : t('donations.pix.static_note')
                    }}
                </p>
                <p class="mt-4 flex items-center gap-2">
                    <ShieldCheck class="size-4" aria-hidden="true" />
                    {{ t('donations.donor.email_hint') }}
                </p>
            </aside>
        </form>
    </div>
</template>
