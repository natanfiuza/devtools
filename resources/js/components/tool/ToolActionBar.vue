<script setup lang="ts">
/**
 * A barra de acoes de uma tool (DESIGN: "barra de acoes" no topo da pagina).
 *
 * Duas faixas: as acoes que **mexem** no documento a esquerda (exemplo, abrir,
 * limpar) e as que so tiram uma copia dele a direita (copiar, baixar). A
 * separacao e espacial de proposito: sao as duas familias de botao que a pessoa
 * mais confunde, e o "limpar" do lado do "copiar" e um clique errado caro.
 */
</script>

<template>
    <div
        class="flex w-full flex-wrap items-center gap-x-2 gap-y-2"
        data-test="tool-action-bar"
    >
        <slot />

        <div v-if="$slots.end" class="ml-auto flex flex-wrap items-center gap-2">
            <slot name="end" />
        </div>
    </div>
</template>
