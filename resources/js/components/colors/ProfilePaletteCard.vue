<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import colorsPalettes from '@/routes/colors/palettes';
import type { ProfilePaletteRow } from '@/types/colors';

/**
 * Cartao de uma paleta no perfil publico (F6, ADR-134).
 *
 * So leitura: previa, nome, descricao, contagem de cores e curtidas ativas.
 * O destino e a pagina publica da paleta - o perfil nunca leva ao editor.
 */

const props = defineProps<{
    palette: ProfilePaletteRow;
}>();

const { t, locale } = useI18n();
const page = usePage();

const localeSlug = computed(() => page.props.localeSlug ?? 'en');

const publicUrl = computed<string | null>(() =>
    props.palette.slug === null
        ? null
        : colorsPalettes.show.url({
              locale: localeSlug.value,
              slug: props.palette.slug,
          }),
);

const likesLabel = computed(() => {
    if (props.palette.likes_count === 0) {
        return t('colors.social.likes_zero');
    }

    if (props.palette.likes_count === 1) {
        return t('colors.social.likes_one');
    }

    return t('colors.social.likes_many', { count: props.palette.likes_count });
});

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
        data-test="profile-palette-card"
    >
        <div
            class="flex h-14 overflow-hidden rounded-lg border border-border"
            role="img"
            :aria-label="t('colors.list.preview_alt', { name: palette.name })"
            data-test="profile-palette-preview"
        >
            <span
                v-for="(hex, index) in palette.preview"
                :key="`${hex}-${index}`"
                class="h-full flex-1"
                :style="{ backgroundColor: hex }"
                :title="hex"
            />
        </div>

        <div class="min-w-0">
            <h3 class="truncate text-base font-semibold">
                <Link
                    v-if="publicUrl !== null"
                    :href="publicUrl"
                    class="hover:underline"
                    data-test="profile-palette-name"
                >
                    {{ palette.name }}
                </Link>
                <span v-else data-test="profile-palette-name">{{
                    palette.name
                }}</span>
            </h3>
            <p
                v-if="palette.description !== null"
                class="mt-1 line-clamp-2 text-sm text-muted-foreground"
                data-test="profile-palette-description"
            >
                {{ palette.description }}
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-1">
            <Badge variant="outline">
                {{ t(`colors.list.kind_${palette.kind}`) }}
            </Badge>
            <Badge variant="secondary">
                {{
                    t('colors.list.colors_count', {
                        count: palette.colors_count,
                    })
                }}
            </Badge>
        </div>

        <dl
            class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground"
        >
            <div>
                <dt class="sr-only">
                    {{ t('colors.social.like') }}
                </dt>
                <dd data-test="profile-palette-likes">
                    {{ likesLabel }}
                </dd>
            </div>
            <div v-if="formatDate(palette.updated_at) !== null">
                <dt class="sr-only">
                    {{ t('colors.list.sort_updated_at') }}
                </dt>
                <dd>
                    {{
                        t('colors.list.updated_at', {
                            date: formatDate(palette.updated_at),
                        })
                    }}
                </dd>
            </div>
        </dl>
    </article>
</template>
