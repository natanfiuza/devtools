<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Copy } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import PageHead from '@/components/PageHead.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useCopyFeedback } from '@/composables/useCopyFeedback';
import { useInitials } from '@/composables/useInitials';
import { formatHex } from '@/lib/color';
import colorsProfiles from '@/routes/colors/profiles';
import publicSystems from '@/routes/colors/systems';
import ThemeComparison from '@/tools/layout-harmonizer/components/ThemeComparison.vue';
import { strategyMessageKey } from '@/tools/layout-harmonizer/lib/save';
import { boardsFromSnapshot } from '@/tools/layout-harmonizer/lib/state';
import type { PublicLayoutSystemProps } from '@/types/layoutSystem';

/**
 * Pagina publica de um sistema de layout (F4, ADR-174).
 *
 * Vitrine somente leitura: os dois quadros vem do snapshot gravado, sem
 * recalcular nada, e a copia de HEX/OKLCH continua funcionando. Nao ha
 * curtidas, comentarios, favoritos nem exportacao nesta fase. O credito da
 * origem aparece so quando a paleta era publica no salvamento; sem curtidas.
 * "Duplicar" exige conta: anonimo passa pelo intent, volta com `duplicate=1` e
 * o POST da copia privada leva ao editor dela.
 */

const props = defineProps<PublicLayoutSystemProps>();

const { t, locale } = useI18n();
const page = usePage();
const copyFeedback = useCopyFeedback();
const feedback = copyFeedback.message;
const { getInitials } = useInitials();

const localeSlug = computed(() => page.props.localeSlug ?? 'en');

const authorUrl = computed<string | null>(() =>
    props.system.author === null
        ? null
        : colorsProfiles.show.url({
              locale: localeSlug.value,
              username: props.system.author.username,
          }),
);

const boards = computed(() => boardsFromSnapshot(props.system.state.snapshot));

const strategyLabel = computed(() =>
    t(strategyMessageKey(props.system.state.inputs.strategy)),
);

const seedHex = computed(() =>
    formatHex(props.system.state.snapshot.seed.effective),
);

const updatedAt = computed<string | null>(() => {
    if (props.system.updated_at === null) {
        return null;
    }

    const date = new Date(props.system.updated_at);

    if (Number.isNaN(date.getTime())) {
        return null;
    }

    return new Intl.DateTimeFormat(locale.value.replace('_', '-'), {
        dateStyle: 'medium',
    }).format(date);
});

const duplicateUrl = computed(() =>
    publicSystems.duplicate.store.url({
        locale: localeSlug.value,
        slug: props.system.slug,
    }),
);

const duplicateIntentUrl = computed(() =>
    publicSystems.duplicate.create.url({
        locale: localeSlug.value,
        slug: props.system.slug,
    }),
);

const duplicating = ref(false);

function duplicate(): void {
    if (!props.authenticated || !props.canDuplicate || duplicating.value) {
        return;
    }

    duplicating.value = true;

    router.post(
        duplicateUrl.value,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                duplicating.value = false;
            },
        },
    );
}

onMounted(() => {
    // Volta do login com a intencao de duplicar: a copia acontece uma vez e a
    // query sai da URL antes do envio, para o recarregamento nao repetir. O
    // replaceState exige mesma origem, entao a limpeza usa o caminho relativo.
    const url = new URL(page.url, window.location.origin);

    if (url.searchParams.get('duplicate') !== '1') {
        return;
    }

    url.searchParams.delete('duplicate');
    window.history.replaceState(
        window.history.state,
        '',
        `${url.pathname}${url.search}${url.hash}`,
    );

    duplicate();
});
</script>

<template>
    <PageHead />

    <article
        class="mx-auto flex w-full max-w-[1120px] flex-col gap-8 px-4 py-10"
        data-test="public-layout-system"
    >
        <header class="flex flex-col gap-3">
            <h1
                class="text-3xl font-semibold tracking-tight"
                data-test="public-layout-system-name"
            >
                {{ system.name }}
            </h1>

            <p
                v-if="system.description !== null"
                class="max-w-2xl text-base text-muted-foreground"
                data-test="public-layout-system-description"
            >
                {{ system.description }}
            </p>

            <div
                v-if="system.author !== null"
                class="flex flex-wrap items-center gap-2"
                data-test="public-layout-system-author"
            >
                <img
                    v-if="system.author.avatar !== null"
                    :src="system.author.avatar"
                    :alt="system.author.name"
                    class="size-8 rounded-full border border-border object-cover"
                />
                <span
                    v-else
                    class="flex size-8 items-center justify-center rounded-full border border-border bg-muted text-xs font-semibold text-muted-foreground"
                    :aria-label="system.author.name"
                >
                    {{ getInitials(system.author.name) }}
                </span>

                <Link
                    :href="authorUrl ?? '#'"
                    class="text-sm font-medium hover:underline"
                    :aria-label="
                        t('colors.public.by', { name: system.author.name })
                    "
                    data-test="public-layout-system-author-link"
                >
                    {{ system.author.name }}
                </Link>
                <span class="text-xs text-muted-foreground">
                    @{{ system.author.username }}
                </span>
            </div>

            <div
                class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground"
            >
                <Badge
                    variant="outline"
                    data-test="public-layout-system-strategy"
                >
                    {{ strategyLabel }}
                </Badge>
                <span class="flex items-center gap-1">
                    <span
                        class="inline-block size-3 rounded-full border border-border"
                        :style="{ backgroundColor: seedHex }"
                        aria-hidden="true"
                    />
                    <span class="font-mono">{{ seedHex }}</span>
                </span>
                <span data-test="public-layout-system-tokens">
                    {{
                        t('colors.systems.list.tokens_count', {
                            count: system.tokens_count,
                        })
                    }}
                </span>
                <span data-test="public-layout-system-usage">
                    {{
                        system.duplications_count === 0
                            ? t('colors.list.usage_zero')
                            : t('colors.list.usage', {
                                  count: system.duplications_count,
                              })
                    }}
                </span>
                <span
                    v-if="updatedAt !== null"
                    data-test="public-layout-system-updated"
                >
                    {{ t('colors.list.updated_at', { date: updatedAt }) }}
                </span>
            </div>

            <p
                class="text-xs text-muted-foreground"
                data-test="public-layout-system-versions"
            >
                {{
                    t('colors.systems.public.versions', {
                        schema: system.schema_version,
                        algorithm: system.algorithm_version,
                        suggestions: system.suggestions_version,
                        snapshot: system.snapshot_version,
                    })
                }}
            </p>

            <p
                v-if="system.credit !== null"
                class="text-xs text-muted-foreground"
                data-test="public-layout-system-credit"
            >
                {{ t('colors.systems.public.credit_from') }}
                <Link
                    v-if="system.credit.palette_url !== null"
                    :href="system.credit.palette_url"
                    class="font-medium underline-offset-4 hover:underline"
                    data-test="public-layout-system-credit-link"
                >
                    {{ system.credit.palette_name }}
                </Link>
                <span v-else class="font-medium">
                    {{ system.credit.palette_name }}
                </span>
            </p>

            <ul
                v-if="system.tags.length > 0"
                class="flex flex-wrap gap-1"
                data-test="public-layout-system-tags"
            >
                <li
                    v-for="tag in system.tags"
                    :key="tag.id"
                    class="rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground"
                >
                    {{ tag.name }}
                </li>
            </ul>
        </header>

        <div v-if="canDuplicate" class="flex flex-wrap items-center gap-2">
            <Button
                v-if="authenticated"
                type="button"
                :disabled="duplicating"
                data-test="public-layout-system-duplicate"
                @click="duplicate"
            >
                <Copy class="size-4" />
                {{ t('colors.public.duplicate') }}
            </Button>
            <Button v-else as-child>
                <Link
                    :href="duplicateIntentUrl"
                    data-test="public-layout-system-duplicate-login"
                >
                    <Copy class="size-4" />
                    {{ t('colors.public.duplicate_login') }}
                </Link>
            </Button>
        </div>

        <section
            class="flex flex-col gap-3"
            data-test="public-layout-system-boards"
        >
            <ThemeComparison
                :light="boards.light"
                :dark="boards.dark"
                :copy="copyFeedback.copy"
                :editable="false"
            />

            <p
                v-if="feedback !== null"
                class="text-xs text-muted-foreground"
                role="status"
                aria-live="polite"
                data-test="public-layout-system-copy-feedback"
            >
                {{ feedback }}
            </p>
        </section>
    </article>
</template>
