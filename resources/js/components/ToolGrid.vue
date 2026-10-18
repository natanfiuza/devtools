<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import ToolCard from '@/components/ToolCard.vue';
import { cn } from '@/lib/utils';
import { categoryById } from '@/tools/categories';
import { toolIcon } from '@/tools/icons';
import { categoriesInUse, toolsByCategory } from '@/tools/registry';
import type { ToolCategoryId, ToolManifest } from '@/tools/types';

/**
 * Grid de ferramentas com os chips de categoria (DESIGN: 3 colunas).
 *
 * O filtro e do cliente e nao mexe na URL: sao poucos cards, todos ja no HTML,
 * e nenhuma ferramenta do site pede nada ao servidor.
 *
 * A lista de tools chega por prop - quem le o registro e a pagina - para o
 * grid nao ter uma segunda fonte de verdade.
 */
const props = defineProps<{ tools: ToolManifest[] }>();

const { t } = useI18n();

/** `null` = "Todas". */
const selected = ref<ToolCategoryId | null>(null);

const chips = computed<ToolCategoryId[]>(() => categoriesInUse(props.tools));

const visible = computed<ToolManifest[]>(() =>
    toolsByCategory(selected.value, props.tools),
);

function chipClass(active: boolean): string {
    return cn(
        'inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-sm transition-colors',
        active
            ? 'border-brand/40 bg-surface-2 text-foreground'
            : 'text-muted-foreground hover:text-foreground',
    );
}
</script>

<template>
    <div
        class="mt-6 flex flex-wrap gap-2"
        role="group"
        :aria-label="t('home.tools.filter')"
    >
        <button
            type="button"
            data-test="tool-chip-all"
            :aria-pressed="selected === null"
            :class="chipClass(selected === null)"
            @click="selected = null"
        >
            {{ t('home.tools.all') }}
        </button>

        <button
            v-for="id in chips"
            :key="id"
            type="button"
            :data-test="`tool-chip-${id}`"
            :aria-pressed="selected === id"
            :class="chipClass(selected === id)"
            @click="selected = id"
        >
            <component
                :is="toolIcon(categoryById(id)?.icon ?? '')"
                class="size-3.5"
                aria-hidden="true"
            />
            {{ t(`tools.categories.${id}`) }}
        </button>
    </div>

    <ul
        v-if="visible.length > 0"
        class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
        data-test="tool-grid"
    >
        <ToolCard v-for="tool in visible" :key="tool.slug" :tool="tool" />
    </ul>

    <p
        v-else
        class="mt-6 text-sm text-muted-foreground"
        data-test="tool-grid-empty"
    >
        {{ t('home.tools.empty') }}
    </p>
</template>
