<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import AppWordmark from '@/components/AppWordmark.vue';
</script>

<template>
    <div
        class="flex aspect-square size-8 items-center justify-center overflow-hidden rounded-md"
    >
        <AppLogoIcon class="size-8" />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <AppWordmark size="md" />
    </div>
</template>
