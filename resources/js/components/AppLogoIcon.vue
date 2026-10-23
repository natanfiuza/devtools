<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
  <svg
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 32 32"
    role="img"
    aria-label="devtools"
    :class="className"
    v-bind="$attrs"
  >
    <rect
      width="32"
      height="32"
      rx="7"
      fill="#111318"
    />
    <circle
      cx="13.5"
      cy="15.5"
      r="5.25"
      fill="none"
      stroke="#e8e9ee"
      stroke-width="3"
    />
    <rect
      x="17.5"
      y="5.5"
      width="3"
      height="20"
      rx="1.5"
      fill="#e8e9ee"
    />
    <rect
      x="6.5"
      y="25.5"
      width="13"
      height="3"
      rx="1.2"
      fill="#66ced6"
    />
  </svg>
</template>
