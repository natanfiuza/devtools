<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import DeleteLayoutSystemDialog from '@/components/colors/DeleteLayoutSystemDialog.vue';
import LayoutSystemCard from '@/components/colors/LayoutSystemCard.vue';
import PalettePagination from '@/components/colors/PalettePagination.vue';
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
import systemRoutes from '@/routes/dashboard/colors/systems';
import type {
    LayoutSystemRow,
    LayoutSystemSort,
    LayoutSystemsIndexProps,
} from '@/types/layoutSystem';

/**
 * Meus Sistemas de Layout (F4, ADR-170).
 *
 * Espelha Minhas Paletas: busca, status, tag e ordenacao sao do servidor, cada
 * mudanca refaz a visita com `preserveState`/`replace`, e a busca espera 300 ms.
 * A cota e conjunta com as paletas e arquivados continuam ocupando vaga.
 */

const props = defineProps<LayoutSystemsIndexProps>();

const { t } = useI18n();

/** `all` no lugar de vazio: o Select do reka-ui trata "" como sem valor. */
const ALL = 'all';

const search = ref(props.filters.q);
const status = ref(props.filters.status);
const tag = ref(props.filters.tag ?? ALL);
const sort = ref<LayoutSystemSort>(props.filters.sort);
const direction = ref<'asc' | 'desc'>(props.filters.direction);
const deleting = ref<LayoutSystemRow | null>(null);

let timer = 0;

function query(
    overrides: Partial<Record<string, string | number | null>>,
): Record<string, string | number> {
    const values: Record<string, string | number | null> = {
        q: search.value.trim(),
        status: status.value,
        tag: tag.value === ALL ? null : tag.value,
        sort: sort.value,
        direction: direction.value,
        ...overrides,
    };

    const result: Record<string, string | number> = {};

    for (const [key, value] of Object.entries(values)) {
        if (value === null || value === '') {
            continue;
        }

        result[key] = value;
    }

    return result;
}

let lastVisited = JSON.stringify(query({}));

function visit(
    overrides: Partial<Record<string, string | number | null>> = {},
    only?: string[],
): void {
    const values = query(overrides);
    const key = JSON.stringify(values);

    if (key === lastVisited && only === undefined) {
        return;
    }

    lastVisited = key;

    router.get(systemRoutes.index.url(), values, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only,
    });
}

function applyFilters(): void {
    window.clearTimeout(timer);
    visit({ page: 1 });
}

watch(search, () => {
    window.clearTimeout(timer);
    timer = window.setTimeout(() => {
        visit({ page: 1 });
    }, 300);
});

onBeforeUnmount(() => {
    window.clearTimeout(timer);
});

const hasFilters = computed(
    () =>
        props.filters.q !== '' ||
        props.filters.tag !== null ||
        props.filters.status === 'archived',
);

const quotaText = computed(() =>
    props.limits.limit === null
        ? t('colors.systems.list.description_unlimited', {
              used: props.limits.used,
          })
        : t('colors.systems.list.description', {
              used: props.limits.used,
              limit: props.limits.limit,
          }),
);

function setStatus(value: string): void {
    status.value = value === 'archived' ? 'archived' : 'active';
    applyFilters();
}

function setTag(value: string): void {
    tag.value = value;
    applyFilters();
}

function setSort(value: string): void {
    sort.value = value as LayoutSystemSort;
    applyFilters();
}

function setDirection(value: string): void {
    direction.value = value === 'asc' ? 'asc' : 'desc';
    applyFilters();
}

function goToPage(page: number): void {
    visit({ page });
}

function clearFilters(): void {
    search.value = '';
    status.value = 'active';
    tag.value = ALL;
    sort.value = 'updated_at';
    direction.value = 'desc';
    applyFilters();
}

const SORTS: LayoutSystemSort[] = [
    'updated_at',
    'created_at',
    'name',
    'duplications_count',
];
</script>

<template>
    <Head :title="t('colors.systems.list.title')" />

    <div class="flex flex-col gap-6" data-test="layout-systems-index">
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold">
                    {{ t('colors.systems.list.title') }}
                </h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ quotaText }}
                </p>
            </div>

            <Button as-child size="sm">
                <Link
                    :href="systemRoutes.create.url()"
                    data-test="layout-systems-new"
                >
                    <Plus class="size-4" />
                    {{ t('colors.systems.list.new_system') }}
                </Link>
            </Button>
        </header>

        <section
            class="flex flex-col gap-3 rounded-xl border border-border bg-surface p-4"
            data-test="layout-systems-filters"
        >
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                <div class="flex flex-col gap-1 xl:col-span-2">
                    <Label for="layout-systems-search">
                        {{ t('colors.list.search_label') }}
                    </Label>
                    <Input
                        id="layout-systems-search"
                        v-model="search"
                        type="search"
                        autocomplete="off"
                        :placeholder="t('colors.list.search_placeholder')"
                        data-test="layout-systems-search"
                    />
                </div>

                <div class="flex flex-col gap-1">
                    <Label>{{ t('colors.list.status_label') }}</Label>
                    <Select
                        :model-value="status"
                        @update:model-value="setStatus(String($event))"
                    >
                        <SelectTrigger data-test="layout-systems-status">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="active">
                                {{ t('colors.list.status_active') }}
                            </SelectItem>
                            <SelectItem value="archived">
                                {{ t('colors.list.status_archived') }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="flex flex-col gap-1">
                    <Label>{{ t('colors.list.tag_label') }}</Label>
                    <Select
                        :model-value="tag"
                        @update:model-value="setTag(String($event))"
                    >
                        <SelectTrigger data-test="layout-systems-tag">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">
                                {{ t('colors.list.tag_all') }}
                            </SelectItem>
                            <SelectItem
                                v-for="entry in tags"
                                :key="entry.id"
                                :value="entry.id"
                            >
                                {{ entry.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="flex flex-col gap-1">
                    <Label>{{ t('colors.list.sort_label') }}</Label>
                    <div class="flex gap-2">
                        <Select
                            :model-value="sort"
                            @update:model-value="setSort(String($event))"
                        >
                            <SelectTrigger
                                class="w-full"
                                data-test="layout-systems-sort"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="value in SORTS"
                                    :key="value"
                                    :value="value"
                                >
                                    {{ t(`colors.list.sort_${value}`) }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <Select
                            :model-value="direction"
                            @update:model-value="setDirection(String($event))"
                        >
                            <SelectTrigger
                                class="w-24"
                                data-test="layout-systems-direction"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="desc">
                                    {{ t('colors.list.direction_desc') }}
                                </SelectItem>
                                <SelectItem value="asc">
                                    {{ t('colors.list.direction_asc') }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
            </div>

            <div v-if="hasFilters" class="flex justify-end">
                <Button
                    variant="ghost"
                    size="sm"
                    data-test="layout-systems-clear"
                    @click="clearFilters"
                >
                    {{ t('colors.list.clear') }}
                </Button>
            </div>
        </section>

        <div
            v-if="systems.data.length === 0"
            class="flex flex-col items-center gap-3 rounded-xl border border-dashed border-border px-6 py-10 text-center"
            data-test="layout-systems-empty"
        >
            <p class="text-base font-medium">
                {{
                    hasFilters
                        ? t('colors.list.empty_search')
                        : t('colors.systems.list.empty_title')
                }}
            </p>
            <p
                v-if="!hasFilters"
                class="max-w-md text-sm text-muted-foreground"
            >
                {{ t('colors.systems.list.empty_description') }}
            </p>
            <Button
                v-if="hasFilters"
                variant="outline"
                size="sm"
                data-test="layout-systems-empty-clear"
                @click="clearFilters"
            >
                {{ t('colors.list.clear') }}
            </Button>
            <Button v-else as-child size="sm">
                <Link :href="systemRoutes.create.url()">
                    {{ t('colors.systems.list.empty_cta') }}
                </Link>
            </Button>
        </div>

        <ul
            v-else
            class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
            data-test="layout-systems-list"
        >
            <li v-for="system in systems.data" :key="system.id">
                <LayoutSystemCard
                    :system="system"
                    @delete="deleting = $event"
                />
            </li>
        </ul>

        <PalettePagination :paginator="systems" @select="goToPage" />

        <DeleteLayoutSystemDialog
            :open="deleting !== null"
            :system-id="deleting?.id ?? ''"
            :system-name="deleting?.name ?? ''"
            @close="deleting = null"
        />
    </div>
</template>
