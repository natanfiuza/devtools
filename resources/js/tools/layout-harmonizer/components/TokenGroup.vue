<script setup lang="ts">
import { useId } from 'vue';
import type { Rgba } from '@/lib/color';
import type { LayoutPairCheck, LayoutToken, LayoutTokenId } from '../engine';
import type { LayoutOverride } from '../lib/editState';
import TokenCell from './TokenCell.vue';

/**
 * Uma familia do quadro (marca, superficies, feedback, conteudo, interacao).
 * O titulo vem traduzido; as chaves dos tokens continuam em ingles.
 */

const props = withDefaults(
    defineProps<{
        title: string;
        tokens: LayoutToken[];
        checksByToken: Record<LayoutTokenId, LayoutPairCheck[]>;
        purposeOf: (id: LayoutTokenId) => string;
        copy: (value: string, success?: string) => Promise<boolean>;
        locks?: Partial<Record<LayoutTokenId, true>>;
        overrides?: Partial<Record<LayoutTokenId, LayoutOverride>>;
        suggestedTokens?: readonly LayoutTokenId[];
        editable?: boolean;
        applyColor?: (token: LayoutTokenId, color: Rgba) => void;
        toggleLock?: (token: LayoutTokenId) => void;
        restoreToken?: (token: LayoutTokenId) => void;
    }>(),
    {
        locks: () => ({}),
        overrides: () => ({}),
        suggestedTokens: () => [],
        editable: true,
        applyColor: () => {},
        toggleLock: () => {},
        restoreToken: () => {},
    },
);

const headingId = useId();

function isLocked(id: LayoutTokenId): boolean {
    return props.locks[id] === true;
}

function isEdited(id: LayoutTokenId): boolean {
    return props.overrides[id] !== undefined;
}

function isSuggested(id: LayoutTokenId): boolean {
    return props.suggestedTokens.includes(id);
}

function apply(id: LayoutTokenId, color: Rgba): void {
    props.applyColor(id, color);
}

function toggle(id: LayoutTokenId): void {
    props.toggleLock(id);
}

function restore(id: LayoutTokenId): void {
    props.restoreToken(id);
}
</script>

<template>
  <section
    class="space-y-2"
    :aria-labelledby="headingId"
  >
    <h3
      :id="headingId"
      class="text-sm font-medium"
    >
      {{ title }}
    </h3>

    <ul class="space-y-2">
      <TokenCell
        v-for="token in tokens"
        :key="token.id"
        :token="token"
        :purpose="purposeOf(token.id)"
        :checks="checksByToken[token.id] ?? []"
        :copy="copy"
        :locked="isLocked(token.id)"
        :edited="isEdited(token.id)"
        :suggested="isSuggested(token.id)"
        :editable="editable"
        :apply-color="(color) => apply(token.id, color)"
        :toggle-lock="() => toggle(token.id)"
        :restore-color="() => restore(token.id)"
      />
    </ul>
  </section>
</template>
