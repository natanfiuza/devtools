<script setup lang="ts">
import { useElementSize } from '@vueuse/core';
import { computed, ref } from 'vue';
import { nodePath } from './lib/path';
import { visibleRows, type JsonNode } from './lib/tree';
import { VIRTUALIZE_FROM, windowRange } from './lib/window';
import TreeRow from './TreeRow.vue';

/*
 * A arvore, com janela de renderizacao.
 *
 * Colapsar esconde a subarvore inteira, entao a tela desenha uma lista **plana**
 * de indices visiveis - e a janela e um corte nessa lista. Uma lista de cem mil
 * linhas no DOM trava a pagina; a partir de `VIRTUALIZE_FROM` so a parte
 * visivel existe, com folga em cima e embaixo para o quadro seguinte chegar
 * pronto.
 *
 * O caminho de cada linha e montado aqui, uma vez por linha renderizada, e nao
 * dentro da linha: com a lista virtualizada sao dezenas de caminhos por quadro,
 * e nao um por no do documento.
 */

const props = defineProps<{
    nodes: JsonNode[];
    /** Indices dos containers fechados. */
    collapsed: Set<number>;
    /** Indices que casam com a busca, quando ha busca ativa. */
    matches?: Set<number>;
}>();

const emit = defineEmits<{
    toggle: [index: number];
    copy: [index: number];
}>();

const rows = computed(() => visibleRows(props.nodes, props.collapsed));

const scroller = ref<HTMLElement | null>(null);
const scrollTop = ref(0);

// A altura da janela vem do proprio elemento. Em ambiente sem layout (jsdom) o
// valor e zero e a janela fica so com a folga - o que nao muda nada para as
// listas pequenas, que nao passam pela virtualizacao.
const { height } = useElementSize(scroller);

const virtual = computed(() => rows.value.length > VIRTUALIZE_FROM);

const range = computed(() =>
    windowRange(scrollTop.value, height.value, rows.value.length),
);

/** Espaco reservado fora da janela: mantem a barra de rolagem do tamanho certo. */
const spacer = computed(() =>
    virtual.value
        ? {
              paddingTop: `${range.value.paddingTop}px`,
              paddingBottom: `${range.value.paddingBottom}px`,
          }
        : {},
);

/**
 * O que o `v-for` percorre. O corte so acontece quando a lista e grande: o
 * ternario tambem evita assinar a rolagem nas listas pequenas.
 */
const lines = computed(() => {
    const shown = virtual.value
        ? rows.value.slice(range.value.start, range.value.end)
        : rows.value;

    return shown.map((index) => ({
        index,
        node: props.nodes[index] as JsonNode,
        path: nodePath(props.nodes, index),
    }));
});

function onScroll(event: Event): void {
    scrollTop.value = (event.target as HTMLElement).scrollTop;
}
</script>

<template>
    <div
        ref="scroller"
        class="h-full overflow-auto"
        data-test="json-tree"
        @scroll.passive="onScroll"
    >
        <div :style="spacer">
            <TreeRow
                v-for="line in lines"
                :key="line.index"
                :node="line.node"
                :path="line.path"
                :open="!collapsed.has(line.index)"
                :matched="matches?.has(line.index) ?? false"
                @toggle="emit('toggle', line.index)"
                @copy="emit('copy', line.index)"
            />
        </div>
    </div>
</template>
