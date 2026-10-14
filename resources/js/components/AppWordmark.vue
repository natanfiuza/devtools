<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

/**
 * Wordmark `devtools_` do DESIGN.md: monospace, com o cursor em --brand.
 * Nao e um link: quem envolve decide (Link do Inertia, por exemplo).
 */
defineOptions({ inheritAttrs: false });

type Props = {
    /** Tamanho do texto; o header usa o padrao. */
    size?: 'sm' | 'md' | 'lg';
    class?: HTMLAttributes['class'];
};

const { size = 'md' } = defineProps<Props>();

const sizes: Record<NonNullable<Props['size']>, string> = {
    sm: 'text-sm',
    md: 'text-base',
    lg: 'text-xl',
};
</script>

<template>
    <span
        v-bind="$attrs"
        :class="['font-mono font-semibold tracking-tight', sizes[size], $props.class]"
    >
        <span>devtools</span><span class="text-brand" aria-hidden="true">_</span>
    </span>
</template>
