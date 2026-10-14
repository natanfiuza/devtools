<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { Search } from '@lucide/vue';
import { onKeyStroke } from '@vueuse/core';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useCommandPalette } from '@/composables/useCommandPalette';
import { cn } from '@/lib/utils';
import { toolIcon } from '@/tools/icons';
import { allTools, statusKey, toolKeys, toolPath } from '@/tools/registry';
import { searchTools } from '@/tools/search';
import type { ToolManifest } from '@/tools/types';

/**
 * Palette de busca (Cmd+K / Ctrl+K) sobre o registro de tools.
 *
 * Montada uma vez no PublicLayout: o gatilho do header, o do hero e o atalho
 * global compartilham o mesmo estado. Sem dependencia nova - e o Dialog + o
 * Input que o projeto ja tem, com a semantica de combobox escrita na mao
 * (`role`, `aria-activedescendant`), porque nao ha primitivo `command` aqui.
 *
 * Tool "soon" aparece na busca com o selo e **nao** navega: a pagina da tool
 * so existe quando ela vira `available` (F2).
 */
const { t } = useI18n();
const page = usePage();
const { isOpen, open, close } = useCommandPalette();

const LIST_ID = 'command-palette-list';
const FIELD_ID = 'command-palette-field';

const query = ref('');
const activeIndex = ref(0);

const localeSlug = computed<string>(() => page.props.localeSlug ?? 'en');

/** O `t` do vue-i18n tem sobrecargas; a busca quer a forma simples. */
const translate = (key: string): string => t(key);

const results = computed<ToolManifest[]>(() =>
    searchTools(allTools, query.value, translate),
);

const activeTool = computed<ToolManifest | undefined>(
    () => results.value[activeIndex.value],
);

const activeOptionId = computed<string | undefined>(() => {
    const tool = activeTool.value;

    return tool === undefined ? undefined : optionId(tool);
});

function optionId(tool: ToolManifest): string {
    return `command-palette-option-${tool.slug}`;
}

function move(delta: number): void {
    const total = results.value.length;

    if (total === 0) {
        return;
    }

    activeIndex.value = (activeIndex.value + delta + total) % total;
}

function openTool(tool: ToolManifest): void {
    const path = toolPath(tool, localeSlug.value);

    // "Em breve" nao navega - o selo na linha explica o por que.
    if (path === null) {
        return;
    }

    close();
    router.visit(path);
}

function openActive(): void {
    const tool = activeTool.value;

    if (tool !== undefined) {
        openTool(tool);
    }
}

/** O "/" abre a busca, mas nao quando o foco esta num campo de texto. */
function isEditable(target: EventTarget | null): boolean {
    return (
        target instanceof HTMLElement &&
        (target.isContentEditable ||
            ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName))
    );
}

// Ao abrir: consulta limpa e primeiro resultado ativo. O foco vai para o
// campo (o FocusScope do Dialog foca o primeiro focavel, que e o input).
watch(isOpen, (isNowOpen) => {
    if (!isNowOpen) {
        return;
    }

    query.value = '';
    activeIndex.value = 0;

    void nextTick(() => document.getElementById(FIELD_ID)?.focus());
});

// Consulta nova reordena a lista: o ativo volta para o primeiro.
watch(query, () => {
    activeIndex.value = 0;
});

// A lista acompanha o teclado: o item ativo precisa estar visivel.
watch(activeOptionId, (id) => {
    const element = id === undefined ? null : document.getElementById(id);

    if (element !== null && typeof element.scrollIntoView === 'function') {
        element.scrollIntoView({ block: 'nearest' });
    }
});

onKeyStroke('k', (event) => {
    if (!event.metaKey && !event.ctrlKey) {
        return;
    }

    event.preventDefault();

    if (isOpen.value) {
        close();
    } else {
        open();
    }
});

onKeyStroke('/', (event) => {
    if (isEditable(event.target)) {
        return;
    }

    event.preventDefault();
    open();
});
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogContent
            :show-close-button="false"
            class="top-[15%] translate-y-0 gap-0 overflow-hidden p-0 sm:max-w-xl"
        >
            <DialogTitle class="sr-only">
                {{ t('app.search.title') }}
            </DialogTitle>
            <DialogDescription class="sr-only">
                {{ t('app.search.description') }}
            </DialogDescription>

            <div class="flex items-center gap-3 border-b px-4 py-3">
                <Search
                    class="size-4 shrink-0 text-muted-foreground"
                    aria-hidden="true"
                />
                <Input
                    :id="FIELD_ID"
                    v-model="query"
                    type="search"
                    role="combobox"
                    aria-autocomplete="list"
                    :aria-controls="LIST_ID"
                    :aria-expanded="true"
                    :aria-activedescendant="activeOptionId"
                    :aria-label="t('app.search.trigger')"
                    :placeholder="t('app.search.placeholder')"
                    autocomplete="off"
                    spellcheck="false"
                    class="h-8 rounded-none border-0 px-0 text-base shadow-none focus-visible:ring-0 md:text-sm"
                    @keydown.down.prevent="move(1)"
                    @keydown.up.prevent="move(-1)"
                    @keydown.enter.prevent="openActive()"
                />
            </div>

            <p
                v-if="results.length === 0"
                data-test="command-palette-empty"
                class="px-4 py-8 text-center text-sm text-muted-foreground"
            >
                {{ t('app.search.empty', { query }) }}
            </p>

            <ul
                v-else
                :id="LIST_ID"
                role="listbox"
                :aria-label="t('app.search.title')"
                class="max-h-80 overflow-y-auto p-2"
            >
                <li
                    v-for="(tool, index) in results"
                    :id="optionId(tool)"
                    :key="tool.slug"
                    role="option"
                    :aria-selected="index === activeIndex"
                    :aria-disabled="
                        tool.status === 'available' ? undefined : 'true'
                    "
                    :data-test="`command-palette-option-${tool.slug}`"
                    :class="
                        cn(
                            'flex items-center gap-3 rounded-md px-3 py-2 text-sm',
                            tool.status === 'available'
                                ? 'cursor-pointer'
                                : 'cursor-default',
                            index === activeIndex && 'bg-surface-2 text-foreground',
                        )
                    "
                    @click="openTool(tool)"
                    @mouseenter="activeIndex = index"
                >
                    <component
                        :is="toolIcon(tool.icon)"
                        class="size-4 shrink-0 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <span class="truncate font-medium">
                        {{ t(toolKeys.name(tool)) }}
                    </span>
                    <span class="truncate text-xs text-muted-foreground">
                        {{ t(`tools.categories.${tool.category}`) }}
                    </span>
                    <Badge
                        v-if="tool.status !== 'available'"
                        variant="secondary"
                        class="ml-auto"
                        data-test="command-palette-badge"
                    >
                        {{ t(statusKey(tool)) }}
                    </Badge>
                </li>
            </ul>

            <div
                data-test="command-palette-footer"
                class="flex items-center gap-4 border-t px-4 py-2 text-xs text-muted-foreground"
            >
                <span class="flex items-center gap-1">
                    <kbd class="rounded border bg-surface-2 px-1 font-mono">
                        &uarr;
                    </kbd>
                    <kbd class="rounded border bg-surface-2 px-1 font-mono">
                        &darr;
                    </kbd>
                    {{ t('app.search.hint_navigate') }}
                </span>
                <span class="flex items-center gap-1">
                    <kbd class="rounded border bg-surface-2 px-1 font-mono">
                        &crarr;
                    </kbd>
                    {{ t('app.search.hint_select') }}
                </span>
                <span class="flex items-center gap-1">
                    <kbd class="rounded border bg-surface-2 px-1 font-mono">
                        {{ t('app.search.key_escape') }}
                    </kbd>
                    {{ t('app.search.hint_close') }}
                </span>
                <span class="ml-auto" data-test="command-palette-count">
                    {{ t('app.search.count', { count: results.length }) }}
                </span>
            </div>
        </DialogContent>
    </Dialog>
</template>
