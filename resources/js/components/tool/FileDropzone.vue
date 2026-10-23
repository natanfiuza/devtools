<script setup lang="ts">
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

/*
 * A caixa de entrada por arquivo: o seletor escondido, a area de arrasto e o
 * aviso que cobre o painel enquanto o arquivo esta no ar.
 *
 * O componente **nao** abre nem interpreta o arquivo - ele entrega o `File` e
 * quem sabe as regras da tool e o pai, que as aplica com `readTextFile`
 * (lib/file.ts) e decide o recado. Assim a mesma caixa serve para o JSON, para
 * o SQL e para o texto do Regex Tester sem nenhum `if` por tipo de arquivo.
 *
 * O que entra por slot e a area de entrada da tool, que fica **dentro** da
 * area de arrasto: soltar o arquivo em qualquer ponto do painel vale.
 */

defineProps<{
    /** O `accept` do seletor, montado por `acceptAttribute` (lib/file.ts). */
    accept: string;
    /** Prefixo dos ganchos de teste: `-dropzone`, `-dropzone-overlay`, `-file`. */
    testid: string;
}>();

const emit = defineEmits<{ file: [file: File] }>();

const { t } = useI18n();

const dragging = ref(false);
const field = ref<HTMLInputElement | null>(null);

/** Abre o seletor de arquivo - e o que o botao "abrir" da barra de acoes faz. */
function pick(): void {
    field.value?.click();
}

function onFileChosen(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    // Zerar o campo e o que permite escolher o mesmo arquivo duas vezes
    // seguidas: com ele preenchido, o segundo `change` nao acontece.
    input.value = '';

    if (file !== undefined) {
        emit('file', file);
    }
}

function onDrop(event: DragEvent): void {
    dragging.value = false;

    const file = event.dataTransfer?.files?.[0];

    if (file !== undefined) {
        emit('file', file);
    }
}

defineExpose({ pick });
</script>

<template>
  <div
    class="relative flex min-h-0 flex-1 flex-col"
    :data-test="`${testid}-dropzone`"
    @dragover.prevent="dragging = true"
    @dragleave="dragging = false"
    @drop.prevent="onDrop"
  >
    <slot />

    <div
      v-if="dragging"
      class="pointer-events-none absolute inset-0 z-10 flex flex-col items-center justify-center gap-1 border-2 border-dashed border-ring bg-surface/90 p-4 text-center"
      :data-test="`${testid}-dropzone-overlay`"
    >
      <p class="text-sm font-medium">
        {{ t('tools.ui.drop') }}
      </p>
      <p class="text-xs text-subtle">
        {{ t('tools.ui.states.drop_hint') }}
      </p>
    </div>

    <input
      ref="field"
      type="file"
      :accept="accept"
      class="sr-only"
      :data-test="`${testid}-file`"
      @change="onFileChosen"
    >
  </div>
</template>
