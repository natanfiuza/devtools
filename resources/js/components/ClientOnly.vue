<script setup lang="ts">
import { onMounted, ref } from 'vue';

/**
 * Renderiza o conteudo so no navegador.
 *
 * O que vai aqui dentro depende do DOM de verdade: o editor (CodeMirror) mede
 * o elemento para desenhar e o parser roda num Web Worker. No servidor nada
 * disso existe, entao o SSR entrega a casca da pagina - cabecalho, metas,
 * canonical - e o miolo monta no cliente.
 *
 * O `v-if` tambem e falso na **primeira** renderizacao do cliente (o
 * `onMounted` ainda nao rodou), entao a hidratacao encontra exatamente o que o
 * servidor renderizou. Sem isso, a arvore da hidratacao nao casaria com a do
 * servidor.
 */
const mounted = ref(false);

onMounted(() => {
    mounted.value = true;
});
</script>

<template>
  <slot v-if="mounted" />
</template>
