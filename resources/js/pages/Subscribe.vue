<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { AtSign, Check, Clock, Cpu, Percent, Sparkles } from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
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
import type { PendingSubscriptionIntent, PublicPlan } from '@/types';

/**
 * Formulario de intencao de assinatura Pro (`/subscribe`, ADR-033).
 *
 * Acessivel apenas para contas cadastradas (visitante que tenta entrar por aqui
 * e levado ao cadastro e volta pelo `url.intended`). Reserva imediatamente o
 * `@nomedeusuario` em `users.username` e registra o pedido na fila do admin.
 */
const props = defineProps<{
    plans: PublicPlan[];
    selectedPlan: string;
    username: string;
    pendingIntent: PendingSubscriptionIntent | null;
    status: string | null;
}>();

const { t } = useI18n();
const routes = useRoutes();

const planSlug = ref<string>(props.selectedPlan);
const paymentMethod = ref<string>(props.pendingIntent?.paymentMethod ?? 'pix');

const advantages = [
    { key: 'username', icon: AtSign },
    { key: 'history', icon: Clock },
    { key: 'quota', icon: Cpu },
    { key: 'priority', icon: Sparkles },
    { key: 'discount', icon: Percent },
] as const;
</script>

<template>
    <Head :title="t('home.subscribe.title')" />

    <div class="mx-auto w-full max-w-[1120px] px-4 py-12 sm:py-20">
        <header class="max-w-3xl">
            <h1
                class="text-3xl leading-tight font-semibold tracking-tight sm:text-4xl"
            >
                {{ t('home.subscribe.title') }}
            </h1>
            <p class="mt-3 text-base text-muted-foreground">
                {{ t('home.subscribe.subtitle') }}
            </p>
        </header>

        <div
            v-if="status === 'intent-submitted' || pendingIntent !== null"
            data-test="subscribe-pending-banner"
            class="mt-6 flex items-start gap-3 rounded-xl border border-brand/40 bg-brand/5 p-4 text-sm"
        >
            <Check class="mt-0.5 size-4 shrink-0 text-brand" aria-hidden="true" />
            <div>
                <p class="font-medium text-foreground">
                    {{ t('home.subscribe.pending_title') }}
                </p>
                <p class="mt-1 text-muted-foreground">
                    {{ t('home.subscribe.pending_body') }}
                </p>
            </div>
        </div>

        <div class="mt-10 grid items-start gap-8 lg:grid-cols-12">
            <div class="rounded-xl border bg-card p-6 sm:p-8 lg:col-span-7">
                <Form
                    v-bind="routes.form.subscribe.value"
                    class="space-y-6"
                    data-test="subscribe-form"
                    v-slot="{ errors, processing }"
                >
                    <div class="space-y-3">
                        <Label>{{ t('home.subscribe.plan_label') }}</Label>
                        <input type="hidden" name="plan" :value="planSlug" />

                        <div class="grid gap-3 sm:grid-cols-2">
                            <button
                                v-for="plan in plans"
                                :key="plan.slug"
                                type="button"
                                :data-test="`subscribe-plan-${plan.slug}`"
                                :class="[
                                    'flex flex-col items-start rounded-xl border p-4 text-left transition-colors',
                                    planSlug === plan.slug
                                        ? 'border-brand bg-brand/5'
                                        : 'border-border bg-surface hover:border-border-strong',
                                ]"
                                @click="planSlug = plan.slug"
                            >
                                <div class="flex w-full items-center justify-between gap-2">
                                    <span class="text-sm font-medium">
                                        {{ plan.name }}
                                        <template v-if="plan.interval !== null">
                                            &middot;
                                            {{ t(`home.pricing.interval.${plan.interval}`) }}
                                        </template>
                                    </span>
                                    <Badge
                                        v-if="plan.interval === 'year'"
                                        variant="secondary"
                                    >
                                        {{ t('home.pricing.yearly_badge') }}
                                    </Badge>
                                </div>
                                <span class="mt-2 font-mono text-2xl font-semibold">
                                    {{ plan.price }}
                                </span>
                            </button>
                        </div>
                        <InputError :message="errors.plan" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="username">
                            {{ t('home.subscribe.username_label') }}
                        </Label>
                        <div class="relative">
                            <span
                                class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 font-mono text-sm text-muted-foreground"
                                aria-hidden="true"
                            >
                                @
                            </span>
                            <Input
                                id="username"
                                name="username"
                                class="pl-7 font-mono"
                                :default-value="username"
                                required
                                autocomplete="off"
                                :placeholder="t('home.subscribe.username_placeholder')"
                                aria-describedby="subscribe-username-help"
                            />
                        </div>
                        <p
                            id="subscribe-username-help"
                            class="text-xs text-muted-foreground"
                        >
                            {{ t('home.subscribe.username_help') }}
                        </p>
                        <InputError :message="errors.username" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="phone">
                                {{ t('home.subscribe.phone_label') }}
                            </Label>
                            <Input
                                id="phone"
                                name="phone"
                                type="tel"
                                :default-value="pendingIntent?.phone ?? ''"
                                required
                                autocomplete="tel"
                                :placeholder="t('home.subscribe.phone_placeholder')"
                            />
                            <InputError :message="errors.phone" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="document">
                                {{ t('home.subscribe.document_label') }}
                            </Label>
                            <Input
                                id="document"
                                name="document"
                                class="font-mono"
                                :default-value="pendingIntent?.document ?? ''"
                                required
                                autocomplete="off"
                                :placeholder="t('home.subscribe.document_placeholder')"
                            />
                            <InputError :message="errors.document" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="payment_method">
                            {{ t('home.subscribe.payment_method_label') }}
                        </Label>
                        <Select v-model="paymentMethod" name="payment_method">
                            <SelectTrigger id="payment_method" class="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="pix">
                                    {{ t('home.subscribe.payment_methods.pix') }}
                                </SelectItem>
                                <SelectItem value="credit_card">
                                    {{ t('home.subscribe.payment_methods.credit_card') }}
                                </SelectItem>
                                <SelectItem value="boleto">
                                    {{ t('home.subscribe.payment_methods.boleto') }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="errors.payment_method" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="notes">
                            {{ t('home.subscribe.notes_label') }}
                        </Label>
                        <textarea
                            id="notes"
                            name="notes"
                            rows="3"
                            maxlength="500"
                            :value="pendingIntent?.notes ?? ''"
                            :placeholder="t('home.subscribe.notes_placeholder')"
                            class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-colors placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        />
                        <InputError :message="errors.notes" />
                    </div>

                    <Button
                        type="submit"
                        class="w-full sm:w-auto"
                        :disabled="processing"
                        data-test="subscribe-submit"
                    >
                        {{
                            pendingIntent !== null
                                ? t('home.subscribe.update_submit')
                                : t('home.subscribe.submit')
                        }}
                    </Button>
                </Form>
            </div>

            <aside class="space-y-4 rounded-xl border bg-surface p-6 lg:col-span-5">
                <h2 class="text-lg font-medium">
                    {{ t('home.pricing.advantages.title') }}
                </h2>
                <p class="text-sm text-muted-foreground">
                    {{ t('home.pricing.advantages.subtitle') }}
                </p>

                <ul class="mt-4 space-y-4">
                    <li
                        v-for="item in advantages"
                        :key="item.key"
                        class="flex items-start gap-3"
                    >
                        <component
                            :is="item.icon"
                            class="mt-0.5 size-4 shrink-0 text-brand"
                            aria-hidden="true"
                        />
                        <div>
                            <h3 class="text-sm font-medium">
                                {{ t(`home.pricing.advantages.items.${item.key}.title`) }}
                            </h3>
                            <p class="mt-0.5 text-xs leading-relaxed text-muted-foreground">
                                {{ t(`home.pricing.advantages.items.${item.key}.body`) }}
                            </p>
                        </div>
                    </li>
                </ul>
            </aside>
        </div>
    </div>
</template>
