<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Titulo da aba das paginas indexaveis.
 *
 * O titulo e a descricao das metas sao resolvidos no servidor
 * (App\Support\Seo::pageMeta) e escritos pelo app.blade.php. Aqui o titulo
 * chega pelas props compartilhadas e o <Head> do Inertia o aplica na
 * navegacao do cliente e no SSR. Nenhuma meta e escrita aqui: no SSR elas
 * duplicariam as do Blade.
 */
const page = usePage();
const title = computed(() => page.props.seo.meta.title);
</script>

<template>
  <Head :title="title" />
</template>
