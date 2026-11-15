<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import PlanForm from '@/components/admin/PlanForm.vue';
import type { AdminPlanRow } from '@/types';

defineProps<{ plans: AdminPlanRow[] }>();

const { t } = useI18n();
</script>

<template>
    <Head :title="t('admin.plans.title')" />

    <div class="space-y-4">
        <header>
            <h2 class="text-lg font-medium">
                {{ t('admin.plans.title') }}
            </h2>
            <p class="mt-1 text-sm text-subtle">
                {{ t('admin.plans.description') }}
            </p>
        </header>

        <p v-if="plans.length === 0" class="text-sm text-subtle">
            {{ t('admin.plans.empty') }}
        </p>

        <div v-else class="grid gap-4 xl:grid-cols-2">
            <PlanForm v-for="plan in plans" :key="plan.id" :plan="plan" />
        </div>
    </div>
</template>
