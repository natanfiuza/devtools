<script setup lang="ts">
import { useId } from 'vue';
import type { LayoutPairCheck, LayoutToken, LayoutTokenId } from '../engine';
import TokenCell from './TokenCell.vue';

/**
 * Uma familia do quadro (marca, superficies, feedback, conteudo, interacao).
 * O titulo vem traduzido; as chaves dos tokens continuam em ingles.
 */

defineProps<{
    title: string;
    tokens: LayoutToken[];
    checksByToken: Record<LayoutTokenId, LayoutPairCheck[]>;
    purposeOf: (id: LayoutTokenId) => string;
    copy: (value: string, success?: string) => Promise<boolean>;
}>();

const headingId = useId();
</script>

<template>
    <section class="space-y-2" :aria-labelledby="headingId">
        <h3 :id="headingId" class="text-sm font-medium">{{ title }}</h3>

        <ul class="space-y-2">
            <TokenCell
                v-for="token in tokens"
                :key="token.id"
                :token="token"
                :purpose="purposeOf(token.id)"
                :checks="checksByToken[token.id] ?? []"
                :copy="copy"
            />
        </ul>
    </section>
</template>
