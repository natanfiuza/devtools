<script setup lang="ts">
import type { Extension } from '@codemirror/state';
import type { EditorView } from '@codemirror/view';
import {
    getCurrentInstance,
    onBeforeUnmount,
    onMounted,
    ref,
    shallowRef,
    watch,
} from 'vue';
import {
    createEditor,
    setEditorDoc,
    setEditorError,
    type EditorError,
    type EditorOptions,
} from '@/lib/editor/createEditor';
import { setLineHighlights, type LineHighlight } from '@/lib/editor/highlights';

/*
 * A casca Vue do editor: ele nasce no `onMounted` e morre no
 * `onBeforeUnmount`, porque o CodeMirror precisa do DOM pronto. O texto entra e
 * sai por `v-model`, e a marca de erro por prop - o componente nao decide nada
 * sobre validacao, sobre linguagem nem sobre traducao.
 */

const props = withDefaults(
    defineProps<{
        modelValue: string;
        placeholder?: string;
        error?: EditorError | null;
        /** Linguagem e afins: entram uma vez, na montagem. */
        extensions?: Extension[];
        /**
         * Linhas pintadas por quem sabe o que elas significam - hoje, o diff.
         * Linha zero-based; a lista pode trocar a qualquer momento.
         */
        lineHighlights?: LineHighlight[];
        /** Gancho de teste do host do editor. */
        testid?: string;
    }>(),
    {
        error: null,
        extensions: () => [],
        lineHighlights: () => [],
        testid: 'code-editor',
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
    find: [];
    format: [];
    paste: [event: ClipboardEvent];
}>();

const host = ref<HTMLElement | null>(null);
// `shallowRef`: instancia do motor nao pode virar reativa - o Vue perseguindo
// as estruturas internas dela custa caro e nao serve para nada.
const view = shallowRef<EditorView | null>(null);

/*
| Quem escuta ganha a tecla.
|
| O `Mod-Enter` ja tem dono no teclado do motor (inserir linha em branco) e o
| `Mod-f` abriria um painel com rotulo em ingles. Prender as duas teclas numa
| acao que ninguem escuta tiraria a tecla do teclado sem por nada no lugar -
| por isso elas so entram quando ha ouvinte. E o ouvinte se descobre pelo
| `vnode`: um evento declarado em `defineEmits` sai dos `attrs`.
*/
const listeners = getCurrentInstance()?.vnode.props ?? {};
const bindsFormat = listeners.onFormat !== undefined;
const bindsFind = listeners.onFind !== undefined;
const bindsPaste = listeners.onPaste !== undefined;

onMounted(() => {
    if (host.value === null) {
        return;
    }

    const options: EditorOptions = {
        doc: props.modelValue,
        extensions: props.extensions,
        onDocChange: (doc) => emit('update:modelValue', doc),
    };

    if (props.placeholder !== undefined) {
        options.placeholder = props.placeholder;
    }

    if (bindsFormat) {
        options.onFormat = () => emit('format');
    }

    if (bindsFind) {
        options.onFind = () => emit('find');
    }

    if (bindsPaste) {
        options.onPaste = (event) => emit('paste', event);
    }

    view.value = createEditor(host.value, options);

    setEditorError(view.value, props.error ?? null);
    setLineHighlights(view.value, props.lineHighlights);
});

onBeforeUnmount(() => {
    view.value?.destroy();
    view.value = null;
});

/*
| Texto que vem de fora (colar, abrir arquivo, exemplo, formatar). O `if`
| compara com o que o editor ja tem: sem isso, cada tecla digitada voltaria
| como mudanca externa e o cursor pularia para o fim da linha.
*/
watch(
    () => props.modelValue,
    (value) => {
        const current = view.value;

        if (current !== null && value !== current.state.doc.toString()) {
            setEditorDoc(current, value);
        }
    },
);

watch(
    () => props.error,
    (value) => {
        if (view.value !== null) {
            setEditorError(view.value, value ?? null);
        }
    },
);

/*
| O realce e derivado do texto, entao quem chama manda uma lista nova a cada
| resultado. O `deep` existe pelo caso oposto: uma lista que so troca o tom de
| uma linha ja desenhada continua sendo a mesma referencia, e o editor precisa
| repintar.
*/
watch(
    () => props.lineHighlights,
    (value) => {
        if (view.value !== null) {
            setLineHighlights(view.value, value ?? []);
        }
    },
    { deep: true },
);

/*
| O diff precisa da instancia para rolar os dois paineis juntos e para levar o
| cursor ate a proxima diferenca. Expor o motor inteiro e deliberado: a casca
| do editor nao ganha um metodo para cada necessidade futura.
*/
defineExpose({ view });
</script>

<template>
    <div
        ref="host"
        class="h-full min-h-0 font-mono text-sm"
        :data-test="testid"
    />
</template>
