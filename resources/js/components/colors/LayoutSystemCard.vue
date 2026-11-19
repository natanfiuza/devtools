<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import systems from '@/routes/dashboard/colors/systems';
import type { LayoutSystemRow } from '@/types/layoutSystem';
import LayoutSystemActions from './LayoutSystemActions.vue';

/**
 * Cartao de um sistema de layout em Minhas Paletas (F4, ADR-174).
 *
 * A previa sao as primeiras cores do snapshot gravado; os metadados vem do
 * presenter. `PaletteCard` nao e reaproveitado: o sistema nao tem `kind` nem
 * curtidas, e a contagem e de tokens.
 */

defineProps<{
    system: LayoutSystemRow;
}>();

const emit = defineEmits<{
    delete: [system: LayoutSystemRow];
}>();

const { t, locale } = useI18n();

function formatDate(value: string | null): string | null {
    if (value === null) {
        return null;
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return null;
    }

    return new Intl.DateTimeFormat(locale.value.replace('_', '-'), {
        dateStyle: 'medium',
    }).format(date);
}
</script>

<template>
    <article
        class="flex flex-col gap-3 rounded-xl border border-border bg-surface p-4"
        data-test="layout-system-card"
    >
        <div
            class="flex h-14 overflow-hidden rounded-lg border border-border"
            role="img"
            :aria-label="
                t('colors.systems.list.preview_alt', { name: system.name })
            "
            data-test="layout-system-preview"
        >
            <span
                v-for="(hex, index) in system.preview"
                :key="`${hex}-${index}`"
                class="h-full flex-1"
                :style="{ backgroundColor: hex }"
                :title="hex"
            />
        </div>

        <div class="flex flex-wrap items-start justify-between gap-2">
            <div class="min-w-0">
                <h3 class="truncate text-base font-semibold">
                    <Link
                        :href="systems.edit.url({ layoutSystem: system.id })"
                        class="hover:underline"
                        data-test="layout-system-card-name"
                    >
                        {{ system.name }}
                    </Link>
                </h3>
                <p
                    v-if="system.description !== null"
                    class="mt-1 line-clamp-2 text-sm text-muted-foreground"
                    data-test="layout-system-card-description"
                >
                    {{ system.description }}
                </p>
            </div>

            <div class="flex flex-wrap gap-1">
                <Badge variant="secondary">
                    {{
                        system.visibility === 'public'
                            ? t('colors.list.badge_public')
                            : t('colors.list.badge_private')
                    }}
                </Badge>
                <Badge
                    v-if="system.archived"
                    variant="destructive"
                    data-test="layout-system-card-archived"
                >
                    {{ t('colors.list.badge_archived') }}
                </Badge>
            </div>
        </div>

        <dl
            class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground"
        >
            <div>
                <dt class="sr-only">
                    {{ t('colors.systems.list.tokens_label') }}
                </dt>
                <dd data-test="layout-system-card-tokens">
                    {{
                        t('colors.systems.list.tokens_count', {
                            count: system.tokens_count,
                        })
                    }}
                </dd>
            </div>
            <div>
                <dt class="sr-only">
                    {{ t('colors.list.sort_duplications_count') }}
                </dt>
                <dd data-test="layout-system-card-usage">
                    {{
                        system.duplications_count === 0
                            ? t('colors.list.usage_zero')
                            : t('colors.list.usage', {
                                  count: system.duplications_count,
                              })
                    }}
                </dd>
            </div>
            <div v-if="formatDate(system.updated_at) !== null">
                <dt class="sr-only">
                    {{ t('colors.list.sort_updated_at') }}
                </dt>
                <dd>
                    {{
                        t('colors.list.updated_at', {
                            date: formatDate(system.updated_at),
                        })
                    }}
                </dd>
            </div>
        </dl>

        <ul
            v-if="system.tags.length > 0"
            class="flex flex-wrap gap-1"
            data-test="layout-system-card-tags"
        >
            <li
                v-for="tag in system.tags"
                :key="tag.id"
                class="rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground"
            >
                {{ tag.name }}
            </li>
        </ul>

        <LayoutSystemActions
            :system="system"
            @delete="emit('delete', system)"
        />
    </article>
</template>
