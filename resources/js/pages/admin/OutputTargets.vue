<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import admin from '@/routes/admin';
import type { AdminOutputTargetRow } from '@/types';

/**
 * Alvos de saida do AI SQL Builder (F7).
 *
 * O catalogo vem da migracao de seed; a tela so liga e desliga um alvo. Slug,
 * idioma, dialetos e plano exigido sao leitura: mudar isso e mudar a geracao,
 * o que pede deploy e revisao do prompt.
 */
defineProps<{ targets: AdminOutputTargetRow[] }>();

const { t } = useI18n();

function toggle(target: AdminOutputTargetRow, value: unknown): void {
    router.patch(
        admin.outputTargets.update.url(target.id),
        { enabled: value === true },
        { preserveScroll: true, preserveState: true },
    );
}
</script>

<template>
    <Head :title="t('admin.output_targets.title')" />

    <div class="space-y-4">
        <header>
            <h2 class="text-lg font-medium">
                {{ t('admin.output_targets.title') }}
            </h2>
            <p class="mt-1 text-sm text-subtle">
                {{ t('admin.output_targets.description') }}
            </p>
            <p class="mt-1 text-xs text-subtle">
                {{ t('admin.output_targets.readonly') }}
            </p>
        </header>

        <p v-if="targets.length === 0" class="text-sm text-subtle">
            {{ t('admin.output_targets.empty') }}
        </p>

        <div v-else class="overflow-x-auto rounded-lg border border-border">
            <table class="w-full border-collapse text-sm">
                <thead>
                    <tr class="border-b border-border">
                        <th
                            scope="col"
                            class="px-3 py-2 text-left text-[12px] font-medium tracking-wide text-subtle uppercase"
                        >
                            {{ t('admin.output_targets.label') }}
                        </th>
                        <th
                            scope="col"
                            class="px-3 py-2 text-left text-[12px] font-medium tracking-wide text-subtle uppercase"
                        >
                            {{ t('admin.output_targets.language') }}
                        </th>
                        <th
                            scope="col"
                            class="px-3 py-2 text-left text-[12px] font-medium tracking-wide text-subtle uppercase"
                        >
                            {{ t('admin.output_targets.dialects') }}
                        </th>
                        <th
                            scope="col"
                            class="px-3 py-2 text-left text-[12px] font-medium tracking-wide text-subtle uppercase"
                        >
                            {{ t('admin.output_targets.plan') }}
                        </th>
                        <th
                            scope="col"
                            class="px-3 py-2 text-left text-[12px] font-medium tracking-wide text-subtle uppercase"
                        >
                            {{ t('admin.output_targets.enabled') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="target in targets"
                        :key="target.id"
                        class="border-b border-border last:border-b-0"
                        :data-test="`alvo-${target.slug}`"
                    >
                        <td class="px-3 py-2 align-middle">
                            <p class="font-medium">{{ target.label }}</p>
                            <p class="font-mono text-[13px] text-subtle">
                                {{ target.slug }}
                            </p>
                        </td>
                        <td class="px-3 py-2 align-middle">
                            {{ target.language }}
                        </td>
                        <td class="px-3 py-2 align-middle">
                            {{ target.dialects.join(', ') }}
                        </td>
                        <td class="px-3 py-2 align-middle">
                            {{ target.plan ?? t('admin.output_targets.free') }}
                        </td>
                        <td class="px-3 py-2 align-middle">
                            <div class="flex items-center gap-3">
                                <Badge
                                    :variant="
                                        target.enabled ? 'secondary' : 'outline'
                                    "
                                >
                                    {{
                                        target.enabled
                                            ? t('admin.output_targets.enabled')
                                            : t('admin.output_targets.disabled')
                                    }}
                                </Badge>

                                <Checkbox
                                    :id="`target-${target.id}`"
                                    :model-value="target.enabled"
                                    @update:model-value="
                                        (value) => toggle(target, value)
                                    "
                                />
                                <Label
                                    :for="`target-${target.id}`"
                                    class="sr-only"
                                >
                                    {{ t('admin.output_targets.toggle') }}
                                </Label>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
