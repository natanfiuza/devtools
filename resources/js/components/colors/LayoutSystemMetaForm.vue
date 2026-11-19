<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type {
    LayoutSystemFormLimits,
    LayoutSystemTag,
    LayoutSystemVisibility,
} from '@/types/layoutSystem';
import TagInput from './TagInput.vue';

/**
 * Metadados editaveis do sistema de layout no editor (F4, ADR-159).
 *
 * Mesmo bloco do modal de salvar; os limites vem do servidor e o valor so vira
 * payload quando o editor salva. Os textos de visibilidade sao os da paleta.
 */

const props = withDefaults(
    defineProps<{
        name: string;
        description: string;
        visibility: LayoutSystemVisibility;
        tags: string[];
        limits: LayoutSystemFormLimits;
        suggestions?: LayoutSystemTag[];
        nameError?: string | null;
        descriptionError?: string | null;
        tagsError?: string | null;
    }>(),
    {
        suggestions: () => [],
        nameError: null,
        descriptionError: null,
        tagsError: null,
    },
);

const emit = defineEmits<{
    'update:name': [value: string];
    'update:description': [value: string];
    'update:visibility': [value: LayoutSystemVisibility];
    'update:tags': [value: string[]];
}>();

const { t } = useI18n();

const nameMessage = computed<string | null>(() => {
    const value = props.name.trim();

    if (value === '') {
        return t('colors.form.errors.name_required');
    }

    if (value.length > props.limits.nameMaxLength) {
        return t('colors.form.errors.name_too_long', {
            max: props.limits.nameMaxLength,
        });
    }

    return props.nameError;
});

const descriptionMessage = computed<string | null>(() => {
    if (props.description.length > props.limits.descriptionMaxLength) {
        return t('colors.form.errors.description_too_long', {
            max: props.limits.descriptionMaxLength,
        });
    }

    return props.descriptionError;
});
</script>

<template>
    <section
        class="flex flex-col gap-4 rounded-xl border border-border bg-surface p-4"
        data-test="layout-system-meta-form"
    >
        <div class="flex flex-col gap-2">
            <div class="flex items-baseline justify-between gap-2">
                <Label for="layout-system-meta-name">
                    {{ t('colors.form.name_label') }}
                </Label>
                <span class="text-xs text-muted-foreground">
                    {{ name.trim().length }}/{{ limits.nameMaxLength }}
                </span>
            </div>
            <Input
                id="layout-system-meta-name"
                :model-value="name"
                type="text"
                autocomplete="off"
                :maxlength="limits.nameMaxLength"
                :aria-invalid="nameMessage !== null"
                :placeholder="t('colors.form.name_placeholder')"
                data-test="layout-system-meta-name"
                @update:model-value="emit('update:name', String($event))"
            />
            <InputError :message="nameMessage ?? undefined" />
        </div>

        <div class="flex flex-col gap-2">
            <div class="flex items-baseline justify-between gap-2">
                <Label for="layout-system-meta-description">
                    {{ t('colors.form.description_label') }}
                </Label>
                <span class="text-xs text-muted-foreground">
                    {{ description.length }}/{{ limits.descriptionMaxLength }}
                </span>
            </div>
            <textarea
                id="layout-system-meta-description"
                :value="description"
                :maxlength="limits.descriptionMaxLength"
                :aria-invalid="descriptionMessage !== null"
                :placeholder="t('colors.form.description_placeholder')"
                class="min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 aria-invalid:border-destructive"
                data-test="layout-system-meta-description"
                @input="
                    emit(
                        'update:description',
                        ($event.target as HTMLTextAreaElement).value,
                    )
                "
            />
            <InputError :message="descriptionMessage ?? undefined" />
        </div>

        <fieldset class="flex flex-col gap-2">
            <legend class="text-sm font-medium">
                {{ t('colors.form.visibility_label') }}
            </legend>
            <div class="grid gap-2 sm:grid-cols-2">
                <button
                    type="button"
                    role="radio"
                    :aria-checked="visibility === 'private'"
                    class="rounded-lg border px-3 py-2 text-left text-sm"
                    :class="
                        visibility === 'private'
                            ? 'border-primary bg-primary/5'
                            : 'border-border'
                    "
                    data-test="layout-system-meta-visibility-private"
                    @click="emit('update:visibility', 'private')"
                >
                    <span class="block font-medium">
                        {{ t('colors.form.visibility_private') }}
                    </span>
                    <span class="block text-xs text-muted-foreground">
                        {{ t('colors.form.visibility_private_hint') }}
                    </span>
                </button>
                <button
                    type="button"
                    role="radio"
                    :aria-checked="visibility === 'public'"
                    class="rounded-lg border px-3 py-2 text-left text-sm"
                    :class="
                        visibility === 'public'
                            ? 'border-primary bg-primary/5'
                            : 'border-border'
                    "
                    data-test="layout-system-meta-visibility-public"
                    @click="emit('update:visibility', 'public')"
                >
                    <span class="block font-medium">
                        {{ t('colors.form.visibility_public') }}
                    </span>
                    <span class="block text-xs text-muted-foreground">
                        {{ t('colors.form.visibility_public_hint') }}
                    </span>
                </button>
            </div>
        </fieldset>

        <div class="flex flex-col gap-1">
            <TagInput
                id="layout-system-meta-tags"
                :model-value="tags"
                :suggestions="suggestions"
                :max="limits.tagsPerSystem"
                :name-max-length="limits.tagNameMaxLength"
                data-test="layout-system-meta-tags"
                @update:model-value="emit('update:tags', $event)"
            />
            <InputError :message="tagsError ?? undefined" />
        </div>
    </section>
</template>
