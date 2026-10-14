<script setup lang="ts" generic="T extends { id: number }">
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import type { AdminColumn, Paginator } from '@/types';

/**
 * Tabela densa do painel (DESIGN: AdminTable).
 *
 * Borda de 1px entre linhas, 40px de altura, cabecalho em 12px maiusculo e
 * numero alinhado a direita em mono - sem sombra, sem zebra. A celula de cada
 * coluna vem por slot (`#cell-<key>`), entao o badge de situacao e o link da
 * conta ficam na pagina, que e quem conhece o dado.
 */
withDefaults(
    defineProps<{
        columns: AdminColumn[];
        rows: Paginator<T>;
        /** Linha de ajuda do estado vazio. */
        empty: string;
        loading?: boolean;
    }>(),
    { loading: false },
);

defineSlots<Record<string, (props: { row: T }) => unknown>>();

const emit = defineEmits<{ page: [page: number] }>();

const { t } = useI18n();
</script>

<template>
    <div class="rounded-lg border border-border">
        <div v-if="loading" class="space-y-3 p-4" aria-busy="true">
            <Skeleton v-for="row in 5" :key="row" class="h-6 w-full" />
        </div>

        <div v-else-if="rows.data.length === 0" class="px-4 py-10 text-center">
            <p class="text-sm font-medium">
                {{ t('admin.common.empty_title') }}
            </p>
            <p class="mt-1 text-sm text-subtle">{{ empty }}</p>
        </div>

        <div v-else class="overflow-x-auto">
            <table class="w-full border-collapse text-sm">
                <thead>
                    <tr class="border-b border-border">
                        <th
                            v-for="column in columns"
                            :key="column.key"
                            scope="col"
                            class="px-3 py-2 text-[12px] font-medium tracking-wide text-subtle uppercase"
                            :class="
                                column.align === 'right'
                                    ? 'text-right'
                                    : 'text-left'
                            "
                        >
                            {{ column.label }}
                        </th>
                        <th
                            v-if="$slots.actions !== undefined"
                            scope="col"
                            class="px-3 py-2 text-right text-[12px] font-medium tracking-wide text-subtle uppercase"
                        >
                            {{ t('admin.common.actions') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in rows.data"
                        :key="row.id"
                        class="h-10 border-b border-border last:border-b-0"
                    >
                        <td
                            v-for="column in columns"
                            :key="column.key"
                            class="px-3 py-1 align-middle"
                            :class="[
                                column.align === 'right'
                                    ? 'text-right'
                                    : 'text-left',
                                { 'font-mono text-[13px]': column.mono },
                            ]"
                        >
                            <slot :name="`cell-${column.key}`" :row="row" />
                        </td>
                        <td
                            v-if="$slots.actions !== undefined"
                            class="px-3 py-1 text-right align-middle"
                        >
                            <div class="flex justify-end gap-2">
                                <slot name="actions" :row="row" />
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-if="!loading && rows.data.length > 0"
            class="flex items-center justify-between border-t border-border px-3 py-2 text-sm text-subtle"
        >
            <span>{{ t('admin.common.total', { total: rows.total }) }}</span>
            <div class="flex items-center gap-3">
                <span>{{
                    t('admin.common.page_of', {
                        page: rows.current_page,
                        last: rows.last_page,
                    })
                }}</span>
                <div class="flex gap-1">
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="rows.current_page <= 1"
                        @click="emit('page', rows.current_page - 1)"
                    >
                        {{ t('admin.common.previous') }}
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="rows.current_page >= rows.last_page"
                        @click="emit('page', rows.current_page + 1)"
                    >
                        {{ t('admin.common.next') }}
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
