<script setup lang="ts">
import { ChevronDown, ChevronRight, Copy } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { isContainer, leafText, type JsonNode } from './lib/tree';
import type { JsonNodeKind } from './lib/types';
import { ROW_HEIGHT } from './lib/window';

/*
 * Uma linha da arvore.
 *
 * A altura e fixa (`ROW_HEIGHT`) porque a virtualizacao e uma conta, e nao uma
 * medida: e o que permite dizer quantas linhas cabem na tela sem medir nenhuma.
 * A cor sai dos mesmos tokens do editor (`--json-*`), entao a mesma chave tem o
 * mesmo tom nos dois paineis e trocar de tema nao passa por JavaScript.
 */

const props = defineProps<{
    node: JsonNode;
    /** Caminho JSONPath do no, ja montado por quem lista. */
    path: string;
    /** `true` quando o container esta aberto; so o botao inverte isto. */
    open: boolean;
    /** `true` quando a linha e um resultado da busca. */
    matched?: boolean;
}>();

const emit = defineEmits<{
    toggle: [];
    copy: [];
}>();

const { t } = useI18n();

const KIND_CLASS: Record<JsonNodeKind, string> = {
    object: 'text-json-punct',
    array: 'text-json-punct',
    string: 'text-json-string',
    number: 'text-json-number',
    boolean: 'text-json-literal',
    null: 'text-json-literal',
};

const container = computed(() => isContainer(props.node));

/** Folha nao colapsa: sem filhos, o botao nao teria o que fazer. */
const collapsible = computed(() => container.value && props.node.childCount > 0);

/** Item de array nao tem chave - o rotulo e a posicao. */
const label = computed(() =>
    props.node.index === null ? props.node.key : `[${props.node.index}]`,
);

const valueClass = computed(() => KIND_CLASS[props.node.kind]);

const openBracket = computed(() => (props.node.kind === 'array' ? '[' : '{'));

const closeBracket = computed(() => (props.node.kind === 'array' ? ']' : '}'));

const indent = computed(() => `${props.node.depth * 14 + 4}px`);
const rowHeight = `${ROW_HEIGHT}px`;
</script>

<template>
    <div
        class="group flex items-center gap-1"
        :class="matched === true ? 'bg-surface-2' : ''"
        :style="{ height: rowHeight, paddingLeft: indent }"
        data-test="json-tree-row"
    >
        <button
            v-if="collapsible"
            type="button"
            class="flex size-4 shrink-0 items-center justify-center rounded text-subtle hover:text-foreground"
            :aria-expanded="open"
            :aria-label="t('tools.json-viewer.tree.toggle')"
            data-test="json-tree-toggle"
            @click="emit('toggle')"
        >
            <component :is="open ? ChevronDown : ChevronRight" class="size-3.5" />
        </button>
        <span v-else class="size-4 shrink-0" aria-hidden="true"></span>

        <span class="min-w-0 truncate font-mono text-xs leading-none">
            <span v-if="label !== null" class="text-json-key">{{ label }}</span>
            <span class="text-json-punct">{{ label === null ? '' : ': ' }}</span>
            <span v-if="container" class="text-json-punct">
                {{ openBracket }}{{ node.childCount }}{{ closeBracket }}
            </span>
            <span v-else :class="valueClass">{{ leafText(node) }}</span>
        </span>

        <button
            type="button"
            class="ml-auto flex size-5 shrink-0 items-center justify-center rounded text-subtle opacity-0 group-hover:opacity-100 focus-visible:opacity-100"
            :title="path"
            :aria-label="t('tools.json-viewer.tree.copy_path')"
            data-test="json-tree-copy"
            @click="emit('copy')"
        >
            <Copy class="size-3" />
        </button>
    </div>
</template>
