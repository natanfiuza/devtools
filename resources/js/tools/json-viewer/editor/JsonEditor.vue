<script setup lang="ts">
import type { EditorView } from '@codemirror/view';
import { onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue';
import { createEditor, setEditorDoc, setEditorError, type EditorError } from './createEditor';

/*
 * A casca Vue do editor: ele nasce no `onMounted` e morre no
 * `onBeforeUnmount`, porque o CodeMirror precisa do DOM pronto. O texto entra e
 * sai por `v-model`, e a marca de erro por prop - o componente nao decide nada
 * sobre validacao nem sobre traducao.
 */

const props = defineProps<{
    modelValue: string;
    placeholder?: string;
    error?: EditorError | null;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
    find: [];
}>();

const host = ref<HTMLElement | null>(null);
// `shallowRef`: a instancia do editor nao pode virar reativa - o Vue
// perseguindo as estruturas internas dela custa caro e nao serve para nada.
const view = shallowRef<EditorView | null>(null);

onMounted(() => {
    if (host.value === null) {
        return;
    }

    view.value = createEditor(host.value, {
        doc: props.modelValue,
        placeholder: props.placeholder,
        onDocChange: (doc) => emit('update:modelValue', doc),
        onFind: () => emit('find'),
    });

    setEditorError(view.value, props.error ?? null);
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
</script>

<template>
    <div ref="host" class="h-full min-h-0 font-mono text-sm" data-test="json-editor"></div>
</template>
