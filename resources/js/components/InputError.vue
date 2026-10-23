<script setup lang="ts">
defineProps<{
    message?: string;
}>();
</script>

<template>
  <!-- role="alert": o leitor de tela anuncia o erro quando ele aparece. -->
  <div
    v-show="message"
    role="alert"
  >
    <p class="text-sm text-destructive">
      {{ message }}
    </p>
  </div>
</template>
