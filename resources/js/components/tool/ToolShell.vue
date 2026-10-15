<script setup lang="ts">
import { ref } from 'vue';
import { useMediaQuery } from '@vueuse/core';
import { useI18n } from 'vue-i18n';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    SPLIT_MAX,
    SPLIT_MIN,
    useSplitPane,
} from '@/composables/useSplitPane';

/**
 * Casca de uma ferramenta (DESIGN: "Pagina de tool: 2 paineis (entrada |
 * saida) redimensionaveis, barra de acoes, atalhos visiveis, mobile empilhado
 * em abas").
 *
 * Quem usa e o `Tool.vue` de cada tool - nenhuma pagina conhece esta casca. A
 * tool entrega o conteudo dos slots e nao se preocupa com layout, divisao nem
 * com o mobile: a F3 acrescenta ferramentas sem tocar aqui.
 *
 * O editor fica no slot, entao trocar de aba **nao** pode desmonta-lo: os dois
 * paineis usam `force-mount` e o inativo sai da tela por uma classe de
 * `display`. Sem isso, o historico de edicao morreria a cada troca de aba no
 * celular.
 *
 * A classe e escrita aqui (e nao deixada com o componente de abas) porque
 * `force-mount` mantem os dois paineis sempre "presentes" para o reka-ui: ele
 * so esconderia o inativo se ele estivesse desmontado, e desmontado e
 * exatamente o que nao pode acontecer. Com `force-mount` ligado, os dois
 * paineis ficariam empilhados na tela.
 */
const props = defineProps<{
    /** Identificador da tool - a divisao escolhida e lembrada por ela. */
    splitKey: string;
}>();

/*
| A casca nao aceita atributo nenhum.
|
| A pagina da tool entrega **todos** os limites da configuracao (o teto de
| entrada, o limiar da virtualizacao, o tempo do regex), e cada tool declara so
| os que usa. O que sobra chegaria aqui e viraria atributo solto no elemento
| raiz - `regex-timeout-ms="2000"` no `<div>` da casca, invisivel e sem
| sentido. Quem quiser por classe ou identificador na casca passa por prop.
*/
defineOptions({ inheritAttrs: false });

const { t } = useI18n();

const { percent, dragging, onPointerDown, onKeydown, reset } = useSplitPane(
    props.splitKey,
);

/**
 * O breakpoint e lido em JS, e nao em CSS: as duas versoes montam o **mesmo**
 * conteudo, entao manter as duas no DOM dobraria as instancias do editor.
 */
const isDesktop = useMediaQuery('(min-width: 768px)');

/** Aba aberta no mobile. */
const tab = ref('input');

const panes = ref<HTMLElement | null>(null);
</script>

<template>
    <div class="flex flex-col gap-3" data-test="tool-shell">
        <div
            v-if="$slots.actions"
            class="flex flex-wrap items-center gap-x-2 gap-y-2"
        >
            <slot name="actions" />
        </div>

        <!-- Desktop: dois paineis lado a lado, com divisor arrastavel. -->
        <div
            v-if="isDesktop"
            ref="panes"
            class="flex items-stretch"
            data-test="tool-shell-panes"
        >
            <section
                class="flex min-w-0 flex-col overflow-hidden rounded-xl border bg-surface"
                :style="{ width: `${percent}%` }"
            >
                <!-- `flex-wrap`: o painel encolhe ate 20% da largura, e os
                     botoes da tool nao cabem em uma linha so nesse tamanho. -->
                <header
                    class="flex flex-wrap items-center gap-2 border-b px-3 py-2"
                    data-test="tool-shell-input-header"
                >
                    <h2
                        class="text-[11px] font-medium tracking-wide text-subtle uppercase"
                    >
                        {{ t('tools.ui.input') }}
                    </h2>
                    <div class="ml-auto flex items-center gap-1">
                        <slot name="input-header" />
                    </div>
                </header>
                <div class="flex min-h-[380px] flex-1 flex-col">
                    <slot name="input" />
                </div>
            </section>

            <div
                role="separator"
                aria-orientation="vertical"
                :aria-label="t('tools.ui.resize')"
                :aria-valuemin="SPLIT_MIN"
                :aria-valuemax="SPLIT_MAX"
                :aria-valuenow="Math.round(percent)"
                tabindex="0"
                data-test="tool-shell-splitter"
                class="group flex w-3 shrink-0 cursor-col-resize items-center justify-center outline-none"
                @pointerdown="onPointerDown($event, panes)"
                @keydown="onKeydown"
                @dblclick="reset"
            >
                <span
                    class="h-10 w-1 rounded-full transition-colors group-hover:bg-border-strong group-focus-visible:bg-ring"
                    :class="dragging ? 'bg-ring' : 'bg-border'"
                    aria-hidden="true"
                />
            </div>

            <section
                class="flex min-w-0 flex-1 flex-col overflow-hidden rounded-xl border bg-surface"
            >
                <header
                    class="flex flex-wrap items-center gap-2 border-b px-3 py-2"
                    data-test="tool-shell-output-header"
                >
                    <h2
                        class="text-[11px] font-medium tracking-wide text-subtle uppercase"
                    >
                        {{ t('tools.ui.output') }}
                    </h2>
                    <div class="ml-auto flex items-center gap-1">
                        <slot name="output-header" />
                    </div>
                </header>
                <div class="flex min-h-[380px] flex-1 flex-col">
                    <slot name="output" />
                </div>
            </section>
        </div>

        <!-- Mobile: os mesmos dois paineis, empilhados em abas. -->
        <Tabs
            v-else
            v-model="tab"
            class="flex flex-col gap-2"
            data-test="tool-shell-tabs"
        >
            <TabsList class="w-full">
                <TabsTrigger value="input">
                    {{ t('tools.ui.input') }}
                </TabsTrigger>
                <TabsTrigger value="output">
                    {{ t('tools.ui.output') }}
                </TabsTrigger>
            </TabsList>

            <TabsContent
                value="input"
                force-mount
                class="flex-col overflow-hidden rounded-xl border bg-surface"
                :class="tab === 'input' ? 'flex' : 'hidden'"
            >
                <!-- As acoes de cada painel tambem no mobile: sem isto, a tool
                     nao teria como formatar nem buscar no celular. O titulo do
                     painel sai daqui porque o rotulo da aba ja o diz. -->
                <div
                    v-if="$slots['input-header']"
                    class="flex flex-wrap items-center justify-end gap-2 border-b px-3 py-2"
                    data-test="tool-shell-input-header"
                >
                    <slot name="input-header" />
                </div>
                <div class="flex min-h-[320px] flex-1 flex-col">
                    <slot name="input" />
                </div>
            </TabsContent>

            <TabsContent
                value="output"
                force-mount
                class="flex-col overflow-hidden rounded-xl border bg-surface"
                :class="tab === 'output' ? 'flex' : 'hidden'"
            >
                <div
                    v-if="$slots['output-header']"
                    class="flex flex-wrap items-center justify-end gap-2 border-b px-3 py-2"
                    data-test="tool-shell-output-header"
                >
                    <slot name="output-header" />
                </div>
                <div class="flex min-h-[320px] flex-1 flex-col">
                    <slot name="output" />
                </div>
            </TabsContent>
        </Tabs>

        <div v-if="$slots.status" class="text-sm" data-test="tool-shell-status">
            <slot name="status" />
        </div>

        <div
            v-if="$slots.shortcuts"
            class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-subtle"
            data-test="tool-shell-shortcuts"
        >
            <slot name="shortcuts" />
        </div>
    </div>
</template>
