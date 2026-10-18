<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import { useI18n } from 'vue-i18n';
import AdminTable from '@/components/admin/AdminTable.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import admin from '@/routes/admin';
import type { AdminColumn, AdminIntentRow, Paginator } from '@/types';

const props = defineProps<{
    intents: Paginator<AdminIntentRow>;
    filters: { status: string };
    total: number;
}>();

const { t } = useI18n();

const columns = computed<AdminColumn[]>(() => [
    { key: 'account', label: t('admin.intents.account') },
    { key: 'username', label: t('admin.intents.username'), mono: true },
    { key: 'plan', label: t('admin.intents.plan') },
    { key: 'contact', label: t('admin.intents.contact') },
    { key: 'paymentMethod', label: t('admin.intents.payment_method') },
    { key: 'status', label: t('admin.intents.status') },
    { key: 'createdAt', label: t('admin.intents.created'), mono: true },
]);

const ALL = 'all';

const form = reactive({
    status: props.filters.status === '' ? ALL : props.filters.status,
});

function query(extra: Record<string, string | number> = {}) {
    return {
        status: form.status === ALL ? '' : form.status,
        ...extra,
    };
}

function apply(): void {
    router.get(admin.intents.index.url(), query(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function clear(): void {
    form.status = ALL;
    apply();
}

function goToPage(page: number): void {
    router.get(admin.intents.index.url(), query({ page }), {
        preserveState: true,
        preserveScroll: true,
    });
}

function fulfill(row: AdminIntentRow): void {
    router.post(
        admin.intents.fulfill.url(row.id),
        {},
        { preserveScroll: true },
    );
}

function cancel(row: AdminIntentRow): void {
    router.post(admin.intents.cancel.url(row.id), {}, { preserveScroll: true });
}
</script>

<template>
    <Head :title="t('admin.intents.title')" />

    <div class="space-y-4">
        <header>
            <h2 class="text-lg font-medium">
                {{ t('admin.intents.title') }}
            </h2>
            <p class="mt-1 text-sm text-subtle">
                {{ t('admin.intents.description') }}
            </p>
        </header>

        <form class="flex flex-wrap items-center gap-2" @submit.prevent="apply">
            <Select v-model="form.status">
                <SelectTrigger
                    class="h-9 w-48"
                    :aria-label="t('admin.intents.status')"
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem :value="ALL">
                        {{ t('admin.intents.status_all') }}
                    </SelectItem>
                    <SelectItem value="pending">
                        {{ t('admin.intents.status_pending') }}
                    </SelectItem>
                    <SelectItem value="fulfilled">
                        {{ t('admin.intents.status_fulfilled') }}
                    </SelectItem>
                    <SelectItem value="canceled">
                        {{ t('admin.intents.status_canceled') }}
                    </SelectItem>
                </SelectContent>
            </Select>

            <Button type="submit" variant="outline" size="sm">
                {{ t('admin.common.search') }}
            </Button>
            <Button type="button" variant="ghost" size="sm" @click="clear">
                {{ t('admin.common.clear') }}
            </Button>
        </form>

        <AdminTable
            :columns="columns"
            :rows="intents"
            :empty="t('admin.intents.empty')"
            @page="goToPage"
        >
            <template #cell-account="{ row }">
                <Link
                    :href="admin.users.show.url(row.userId)"
                    class="font-medium hover:underline"
                >
                    {{ row.userName }}
                </Link>
                <div class="text-xs text-subtle">
                    {{ row.userEmail }}
                </div>
            </template>

            <template #cell-username="{ row }"> @{{ row.username }} </template>

            <template #cell-plan="{ row }">
                <span>{{ row.planName }}</span>
                <span v-if="row.interval" class="text-xs text-subtle">
                    ({{ row.planSlug }})
                </span>
            </template>

            <template #cell-contact="{ row }">
                <div class="font-mono text-xs">
                    {{ row.phone }}
                </div>
                <div class="font-mono text-xs text-subtle">
                    {{ row.document }}
                </div>
                <div
                    v-if="row.notes"
                    class="mt-0.5 max-w-[14rem] truncate text-xs text-subtle"
                    :title="row.notes"
                >
                    {{ row.notes }}
                </div>
            </template>

            <template #cell-paymentMethod="{ row }">
                {{ t(`home.subscribe.payment_methods.${row.paymentMethod}`) }}
            </template>

            <template #cell-status="{ row }">
                <Badge
                    :variant="
                        row.status === 'fulfilled'
                            ? 'secondary'
                            : row.status === 'canceled'
                              ? 'outline'
                              : 'default'
                    "
                >
                    {{ t(`admin.intents.status_${row.status}`) }}
                </Badge>
            </template>

            <template #cell-createdAt="{ row }">
                {{ row.createdAt }}
            </template>

            <template #actions="{ row }">
                <div class="flex items-center justify-end gap-1">
                    <Button
                        v-if="row.status !== 'fulfilled'"
                        variant="outline"
                        size="sm"
                        :data-test="`intent-fulfill-${row.id}`"
                        @click="fulfill(row)"
                    >
                        {{ t('admin.intents.fulfill') }}
                    </Button>
                    <Button
                        v-if="row.status !== 'canceled'"
                        variant="ghost"
                        size="sm"
                        :data-test="`intent-cancel-${row.id}`"
                        @click="cancel(row)"
                    >
                        {{ t('admin.intents.cancel') }}
                    </Button>
                </div>
            </template>
        </AdminTable>
    </div>
</template>
