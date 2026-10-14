<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

/**
 * Cabecalho de uma pagina indexavel: o titulo da aba e a descricao.
 *
 * As duas frases vao em dois lugares cada uma - o titulo tambem no card de
 * compartilhamento (`og:title`) e a descricao tambem no resumo que a busca
 * mostra (`meta description`). Quem le esses campos e o robo; o texto da tela
 * continua no template da pagina, com as mesmas chaves de traducao.
 *
 * O `<title>` continua sendo o do componente do Inertia, que escapa o texto e
 * ignora a prop se um titulo ja vier no slot.
 *
 * As metas do site inteiro (`og:type`, `og:url`, `og:image`, `og:locale`)
 * ficam no app.blade.php: elas nao mudam de pagina para pagina.
 */
defineProps<{
    /** Titulo curto: o da aba do navegador e o do card. */
    title: string;
    /** Uma ou duas frases sobre a pagina. */
    description: string;
}>();
</script>

<template>
    <Head :title="title">
        <meta name="description" :content="description" />
        <meta property="og:title" :content="title" />
        <meta property="og:description" :content="description" />
    </Head>
</template>
