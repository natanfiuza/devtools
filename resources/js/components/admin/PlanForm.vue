<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
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
import admin from '@/routes/admin';
import type { AdminPlanRow } from '@/types';

/**
 * Edicao de um plano (ADR-027).
 *
 * Um formulario por plano, com os numeros como texto: o input number devolve
 * string, e converter na saida deixa o campo vazio virar erro de validacao em
 * vez de zero silencioso.
 */
const props = defineProps<{ plan: AdminPlanRow }>();

const { t } = useI18n();

// O Select do reka-ui trata string vazia como "sem valor".
const NONE = 'none';

const intervals = [NONE, 'month', 'year'];

const form = useForm({
    name: props.plan.name,
    price_cents: String(props.plan.priceCents),
    interval: props.plan.interval ?? NONE,
    monthly_tokens: String(props.plan.monthlyTokens),
    max_schema_bytes: String(props.plan.maxSchemaBytes),
    max_prompt_chars: String(props.plan.maxPromptChars),
    lifetime_free_uses: String(props.plan.lifetimeFreeUses),
    active: props.plan.active,
    sort: String(props.plan.sort),
});

/** Campo vazio ou invalido vira erro de validacao, nunca zero calado. */
function toInt(value: string): number | string {
    const parsed = Number(value);

    return Number.isInteger(parsed) ? parsed : value;
}

function submit(): void {
    form.transform((data) => ({
        ...data,
        price_cents: toInt(data.price_cents),
        monthly_tokens: toInt(data.monthly_tokens),
        max_schema_bytes: toInt(data.max_schema_bytes),
        max_prompt_chars: toInt(data.max_prompt_chars),
        lifetime_free_uses: toInt(data.lifetime_free_uses),
        sort: toInt(data.sort),
        interval: data.interval === NONE ? '' : data.interval,
    })).patch(admin.plans.update.url(props.plan.id), { preserveScroll: true });
}
</script>

<template>
    <article class="rounded-lg border border-border p-4">
        <header class="flex items-start justify-between gap-3">
            <div>
                <h3 class="flex items-center gap-2 text-sm font-medium">
                    {{ plan.name }}
                    <Badge v-if="plan.isFree" variant="secondary">
                        {{ t('admin.plans.free_tag') }}
                    </Badge>
                </h3>
                <p class="mt-1 text-xs text-subtle">
                    {{ t('admin.plans.slug_help', { slug: plan.slug }) }}
                </p>
            </div>

            <p class="shrink-0 text-xs text-subtle">
                {{ t('admin.plans.subscribers') }}:
                <span class="font-mono">{{ plan.subscribers }}</span>
            </p>
        </header>

        <form class="mt-4 grid gap-3 sm:grid-cols-2" @submit.prevent="submit">
            <div class="grid gap-2 sm:col-span-2">
                <Label :for="`plan-name-${plan.id}`">
                    {{ t('admin.plans.name') }}
                </Label>
                <Input
                    :id="`plan-name-${plan.id}`"
                    v-model="form.name"
                    type="text"
                />
                <InputError :message="form.errors.name" />
            </div>

            <div class="grid gap-2">
                <Label :for="`plan-price-${plan.id}`">
                    {{ t('admin.plans.price_cents') }}
                </Label>
                <Input
                    :id="`plan-price-${plan.id}`"
                    v-model="form.price_cents"
                    type="number"
                    min="0"
                />
                <p class="text-xs text-subtle">
                    {{ t('admin.plans.price') }}: {{ plan.price }}
                    {{ plan.currency }}
                </p>
                <InputError :message="form.errors.price_cents" />
            </div>

            <div class="grid gap-2">
                <Label :for="`plan-interval-${plan.id}`">
                    {{ t('admin.plans.interval') }}
                </Label>
                <Select v-model="form.interval">
                    <SelectTrigger :id="`plan-interval-${plan.id}`">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="interval in intervals"
                            :key="interval"
                            :value="interval"
                        >
                            {{ t(`admin.plans.interval_${interval}`) }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.interval" />
            </div>

            <div class="grid gap-2">
                <Label :for="`plan-tokens-${plan.id}`">
                    {{ t('admin.plans.monthly_tokens') }}
                </Label>
                <Input
                    :id="`plan-tokens-${plan.id}`"
                    v-model="form.monthly_tokens"
                    type="number"
                    min="0"
                />
                <InputError :message="form.errors.monthly_tokens" />
            </div>

            <div class="grid gap-2">
                <Label :for="`plan-uses-${plan.id}`">
                    {{ t('admin.plans.lifetime_free_uses') }}
                </Label>
                <Input
                    :id="`plan-uses-${plan.id}`"
                    v-model="form.lifetime_free_uses"
                    type="number"
                    min="0"
                />
                <InputError :message="form.errors.lifetime_free_uses" />
            </div>

            <div class="grid gap-2">
                <Label :for="`plan-schema-${plan.id}`">
                    {{ t('admin.plans.max_schema_bytes') }}
                </Label>
                <Input
                    :id="`plan-schema-${plan.id}`"
                    v-model="form.max_schema_bytes"
                    type="number"
                    min="0"
                />
                <InputError :message="form.errors.max_schema_bytes" />
            </div>

            <div class="grid gap-2">
                <Label :for="`plan-prompt-${plan.id}`">
                    {{ t('admin.plans.max_prompt_chars') }}
                </Label>
                <Input
                    :id="`plan-prompt-${plan.id}`"
                    v-model="form.max_prompt_chars"
                    type="number"
                    min="0"
                />
                <InputError :message="form.errors.max_prompt_chars" />
            </div>

            <div class="grid gap-2">
                <Label :for="`plan-sort-${plan.id}`">
                    {{ t('admin.plans.sort') }}
                </Label>
                <Input
                    :id="`plan-sort-${plan.id}`"
                    v-model="form.sort"
                    type="number"
                    min="0"
                />
                <InputError :message="form.errors.sort" />
            </div>

            <div class="flex items-center gap-2 sm:col-span-2">
                <Checkbox
                    :id="`plan-active-${plan.id}`"
                    :model-value="form.active"
                    @update:model-value="
                        (value) => (form.active = value === true)
                    "
                />
                <Label :for="`plan-active-${plan.id}`">
                    {{ t('admin.plans.active') }}
                </Label>
                <InputError :message="form.errors.active" />
            </div>

            <div class="sm:col-span-2">
                <Button
                    type="submit"
                    variant="outline"
                    size="sm"
                    :disabled="form.processing"
                >
                    {{ t('admin.plans.save') }}
                </Button>
            </div>
        </form>
    </article>
</template>
