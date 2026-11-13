<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Tags } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import DeletePaletteDialog from '@/components/colors/DeletePaletteDialog.vue';
import FavoritePaletteCard from '@/components/colors/FavoritePaletteCard.vue';
import PaletteCard from '@/components/colors/PaletteCard.vue';
import PalettePagination from '@/components/colors/PalettePagination.vue';
import TagManagerDialog from '@/components/colors/TagManagerDialog.vue';
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
import palettesRoutes from '@/routes/dashboard/colors/palettes';
import type {
    PaletteRow,
    PaletteSort,
    PaletteTab,
    PalettesIndexProps,
} from '@/types/colors';

/**
 * Minhas Paletas (F4, ADR-104/ADR-109; F5, ADR-128).
 *
 * Busca, filtro e ordenacao sao do servidor: cada mudanca refaz a visita com
 * `preserveState` e `replace`, entao a query string conta a historia da tela e
 * o voltar do navegador funciona. A busca espera 300 ms; os selects vao na
 * hora. A cota aparece no topo porque arquivadas continuam ocupando vaga.
 *
 * A aba Favoritas tambem e do servidor (`?tab=favorites`): so paletas publicas
 * de outras contas, paginadas, sem os filtros da aba "Minhas paletas".
 */

const props = defineProps<PalettesIndexProps>();

const { t } = useI18n();

/** `all` no lugar de vazio: o Select do reka-ui trata "" como sem valor. */
const ALL = 'all';

const tab = ref<PaletteTab>(props.tab);
const search = ref(props.filters.q);
const status = ref(props.filters.status);
const tag = ref(props.filters.tag ?? ALL);
const sort = ref<PaletteSort>(props.filters.sort);
const direction = ref<'asc' | 'desc'>(props.filters.direction);
const managingTags = ref(false);
const deleting = ref<PaletteRow | null>(null);

let timer = 0;

function query(
    overrides: Partial<Record<string, string | number | null>>,
): Record<string, string | number> {
    const filters: Record<string, string | null> =
        tab.value === 'favorites'
            ? {}
            : {
                  q: search.value.trim(),
                  status: status.value,
                  tag: tag.value === ALL ? null : tag.value,
                  sort: sort.value,
                  direction: direction.value,
              };

    const values: Record<string, string | number | null> = {
        tab: tab.value === 'favorites' ? 'favorites' : null,
        ...filters,
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

/**
 * Ultima query enviada. E tambem a da tela: limpar os filtros muda o `search`
 * de forma programatica, e o debounce pendente nao pode repetir a visita.
 */
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

    router.get(palettesRoutes.index.url(), values, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only,
    });
}

/** Filtro explicito cancela a busca pendente e leva o texto atual junto. */
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
        ? t('colors.list.description_unlimited', { used: props.limits.used })
        : t('colors.list.description', {
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
    sort.value = value as PaletteSort;
    applyFilters();
}

function setDirection(value: string): void {
    direction.value = value === 'asc' ? 'asc' : 'desc';
    applyFilters();
}

function goToPage(page: number): void {
    visit({ page });
}

/** Paginador da aba visivel; o das favoritas so vem quando ela esta ativa. */
const activePaginator = computed(() =>
    tab.value === 'favorites'
        ? (props.favorites ?? props.palettes)
        : props.palettes,
);

function setTab(value: PaletteTab): void {
    if (tab.value === value) {
        return;
    }

    tab.value = value;
    window.clearTimeout(timer);
    visit({ page: 1 });
}

/** Remover um favorito recarrega a aba; pagina que esvaziou volta uma. */
function onFavoriteRemoved(): void {
    const current = props.favorites;
    const page =
        current !== null &&
        current.data.length === 1 &&
        current.current_page > 1
            ? current.current_page - 1
            : (current?.current_page ?? 1);

    visit({ page }, ['favorites']);
}

function clearFilters(): void {
    search.value = '';
    status.value = 'active';
    tag.value = ALL;
    sort.value = 'updated_at';
    direction.value = 'desc';
    applyFilters();
}

const SORTS: PaletteSort[] = [
    'updated_at',
    'created_at',
    'name',
    'colors_count',
    'duplications_count',
];
</script>

<template>
  <Head :title="t('colors.list.title')" />

  <div
    class="flex flex-col gap-6"
    data-test="palettes-index"
  >
    <header class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <h2 class="text-xl font-semibold">
          {{ t('colors.list.title') }}
        </h2>
        <p
          v-if="tab === 'mine'"
          class="mt-1 text-sm text-muted-foreground"
        >
          {{ quotaText }}
        </p>
      </div>

      <div class="flex flex-wrap gap-2">
        <Button
          variant="outline"
          size="sm"
          data-test="palettes-manage-tags"
          @click="managingTags = true"
        >
          <Tags class="size-4" />
          {{ t('colors.list.manage_tags') }}
        </Button>
        <Button
          as-child
          size="sm"
        >
          <Link
            :href="palettesRoutes.create.url()"
            data-test="palettes-new"
          >
            <Plus class="size-4" />
            {{ t('colors.list.new_palette') }}
          </Link>
        </Button>
      </div>
    </header>

    <nav
      class="flex gap-1 border-b border-border"
      role="tablist"
      data-test="palettes-tabs"
    >
      <button
        type="button"
        role="tab"
        class="-mb-px border-b-2 px-3 py-2 text-sm font-medium transition-colors"
        :class="
          tab === 'mine'
            ? 'border-primary text-foreground'
            : 'border-transparent text-muted-foreground hover:text-foreground'
        "
        :aria-selected="tab === 'mine'"
        data-test="palettes-tab-mine"
        @click="setTab('mine')"
      >
        {{ t('colors.favorites.tab_mine') }}
      </button>
      <button
        type="button"
        role="tab"
        class="-mb-px border-b-2 px-3 py-2 text-sm font-medium transition-colors"
        :class="
          tab === 'favorites'
            ? 'border-primary text-foreground'
            : 'border-transparent text-muted-foreground hover:text-foreground'
        "
        :aria-selected="tab === 'favorites'"
        data-test="palettes-tab-favorites"
        @click="setTab('favorites')"
      >
        {{ t('colors.favorites.tab_favorites') }}
      </button>
    </nav>

    <section
      v-if="tab === 'mine'"
      class="flex flex-col gap-3 rounded-xl border border-border bg-surface p-4"
      data-test="palettes-filters"
    >
      <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
        <div class="flex flex-col gap-1 xl:col-span-2">
          <Label for="palettes-search">
            {{ t('colors.list.search_label') }}
          </Label>
          <Input
            id="palettes-search"
            v-model="search"
            type="search"
            autocomplete="off"
            :placeholder="t('colors.list.search_placeholder')"
            data-test="palettes-search"
          />
        </div>

        <div class="flex flex-col gap-1">
          <Label>
            {{ t('colors.list.status_label') }}
          </Label>
          <Select
            :model-value="status"
            @update:model-value="setStatus(String($event))"
          >
            <SelectTrigger data-test="palettes-status">
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
          <Label>
            {{ t('colors.list.tag_label') }}
          </Label>
          <Select
            :model-value="tag"
            @update:model-value="setTag(String($event))"
          >
            <SelectTrigger data-test="palettes-tag">
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
          <Label>
            {{ t('colors.list.sort_label') }}
          </Label>
          <div class="flex gap-2">
            <Select
              :model-value="sort"
              @update:model-value="setSort(String($event))"
            >
              <SelectTrigger
                class="w-full"
                data-test="palettes-sort"
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
                data-test="palettes-direction"
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

      <div
        v-if="hasFilters"
        class="flex justify-end"
      >
        <Button
          variant="ghost"
          size="sm"
          data-test="palettes-clear"
          @click="clearFilters"
        >
          {{ t('colors.list.clear') }}
        </Button>
      </div>
    </section>

    <template v-if="tab === 'mine'">
      <div
        v-if="palettes.data.length === 0"
        class="flex flex-col items-center gap-3 rounded-xl border border-dashed border-border px-6 py-10 text-center"
        data-test="palettes-empty"
      >
        <p class="text-base font-medium">
          {{ hasFilters ? t('colors.list.empty_search') : t('colors.list.empty_title') }}
        </p>
        <p
          v-if="!hasFilters"
          class="max-w-md text-sm text-muted-foreground"
        >
          {{ t('colors.list.empty_description') }}
        </p>
        <Button
          v-if="hasFilters"
          variant="outline"
          size="sm"
          data-test="palettes-empty-clear"
          @click="clearFilters"
        >
          {{ t('colors.list.clear') }}
        </Button>
        <Button
          v-else
          as-child
          size="sm"
        >
          <Link :href="palettesRoutes.create.url()">
            {{ t('colors.list.empty_cta') }}
          </Link>
        </Button>
      </div>

      <ul
        v-else
        class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
        data-test="palettes-list"
      >
        <li
          v-for="palette in palettes.data"
          :key="palette.id"
        >
          <PaletteCard
            :palette="palette"
            @delete="deleting = $event"
          />
        </li>
      </ul>
    </template>

    <template v-else>
      <div
        v-if="(favorites?.data.length ?? 0) === 0"
        class="flex flex-col items-center gap-3 rounded-xl border border-dashed border-border px-6 py-10 text-center"
        data-test="favorites-empty"
      >
        <p class="text-base font-medium">
          {{ t('colors.favorites.empty_title') }}
        </p>
        <p class="max-w-md text-sm text-muted-foreground">
          {{ t('colors.favorites.empty_description') }}
        </p>
        <Button
          variant="outline"
          size="sm"
          data-test="favorites-empty-cta"
          @click="setTab('mine')"
        >
          {{ t('colors.favorites.empty_cta') }}
        </Button>
      </div>

      <ul
        v-else
        class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
        data-test="favorites-list"
      >
        <li
          v-for="entry in favorites?.data ?? []"
          :key="entry.id"
        >
          <FavoritePaletteCard
            :palette="entry"
            @removed="onFavoriteRemoved"
          />
        </li>
      </ul>
    </template>

    <PalettePagination
      :paginator="activePaginator"
      @select="goToPage"
    />

    <TagManagerDialog
      :open="managingTags"
      :tags="tags"
      :limits="form"
      @close="managingTags = false"
    />

    <DeletePaletteDialog
      :open="deleting !== null"
      :palette-id="deleting?.id ?? ''"
      :palette-name="deleting?.name ?? ''"
      @close="deleting = null"
    />
  </div>
</template>
