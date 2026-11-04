<script setup lang="ts">
import { computed } from 'vue';
import { escapeXml } from './lib/render';
import type { BadgeLogoPath } from './types';

/**
 * Previa inline de um logo personalizado multi-path (ADR-078).
 *
 * Usa o mesmo escape do renderer do badge: o SVG e montado aqui, nunca vem
 * como HTML do servidor. Uma cor so - o logo e sempre recolorido pelo
 * `logoColor` do formulario.
 */
const props = withDefaults(
    defineProps<{
        viewBox: string;
        paths: BadgeLogoPath[];
        color?: string;
        size?: number;
    }>(),
    {
        color: '#ffffff',
        size: 24,
    },
);

const svg = computed(() => {
    const body = props.paths
        .map((path) => {
            const rules = [
                path.fillRule === null
                    ? ''
                    : ` fill-rule="${path.fillRule}"`,
                path.clipRule === null
                    ? ''
                    : ` clip-rule="${path.clipRule}"`,
            ].join('');

            return `<path d="${escapeXml(path.d)}"${rules} fill="${escapeXml(props.color)}"/>`;
        })
        .join('');

    return (
        '<svg xmlns="http://www.w3.org/2000/svg"' +
        ` viewBox="${escapeXml(props.viewBox)}"` +
        ` width="${props.size}" height="${props.size}"` +
        ' aria-hidden="true" focusable="false">' +
        `${body}</svg>`
    );
});
</script>

<template>
  <!-- eslint-disable vue/no-v-html -- SVG gerado e escapado aqui -->
  <span
    class="inline-flex shrink-0"
    data-test="badge-logo-preview"
    v-html="svg"
  />
  <!-- eslint-enable vue/no-v-html -->
</template>
