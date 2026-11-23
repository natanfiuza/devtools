<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import { useI18n } from 'vue-i18n';
import AdminTable from '@/components/admin/AdminTable.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import admin from '@/routes/admin';
import type { AdminColumn, AdminUserRow, Paginator } from '@/types';

const props = defineProps<{
    users: Paginator<AdminUserRow>;
    filters: { q: string; role: string; status: string };
    total: number;
}>();

const { t } = useI18n();

const columns = computed<AdminColumn[]>(() => [
    { key: 'account', label: t('admin.users.account') },
    { key: 'plan', label: t('admin.users.plan') },
    { key: 'role', label: t('admin.users.role') },
    { key: 'status', label: t('admin.users.status') },
    { key: 'createdAt', label: t('admin.users.created'), mono: true },
]);

// `all` no lugar de string vazia: o Select do reka-ui trata vazio como
// "sem valor" e nao renderiza o rotulo escolhido.
const ALL = 'all';

const form = reactive({
    q: props.filters.q,
    role: props.filters.role === '' ? ALL : props.filters.role,
    status: props.filters.status === '' ? ALL : props.filters.status,
});

function query(extra: Record<string, string | number> = {}) {
    return {
        q: form.q,
        role: form.role === ALL ? '' : form.role,
        status: form.status === ALL ? '' : form.status,
        ...extra,
    };
}

/** Filtro sempre na barra acima da tabela, nunca no cabecalho (DESIGN). */
function apply(): void {
    router.get(admin.users.index.url(), query(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function clear(): void {
    form.q = '';
    form.role = ALL;
    form.status = ALL;

    apply();
}

function goToPage(page: number): void {
    router.get(admin.users.index.url(), query({ page }), {
        preserveState: true,
        preserveScroll: true,
    });
}
</script>

<template>
  <Head :title="t('admin.users.title')" />

  <div class="space-y-4">
    <header>
      <h2 class="text-lg font-medium">
        {{ t('admin.users.title') }}
      </h2>
      <p class="mt-1 text-sm text-subtle">
        {{ t('admin.users.description') }}
      </p>
    </header>

    <form
      class="flex flex-wrap items-center gap-2"
      @submit.prevent="apply"
    >
      <Input
        v-model="form.q"
        class="h-9 max-w-xs"
        type="search"
        :placeholder="t('admin.users.search_placeholder')"
        :aria-label="t('admin.common.search')"
      />

      <Select v-model="form.role">
        <SelectTrigger
          class="h-9 w-40"
          :aria-label="t('admin.users.role')"
        >
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          <SelectItem :value="ALL">
            {{ t('admin.users.role_all') }}
          </SelectItem>
          <SelectItem value="user">
            {{ t('admin.users.role_user') }}
          </SelectItem>
          <SelectItem value="admin">
            {{ t('admin.users.role_admin') }}
          </SelectItem>
        </SelectContent>
      </Select>

      <Select v-model="form.status">
        <SelectTrigger
          class="h-9 w-44"
          :aria-label="t('admin.users.status')"
        >
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          <SelectItem :value="ALL">
            {{ t('admin.users.status_all') }}
          </SelectItem>
          <SelectItem value="active">
            {{ t('admin.users.status_active') }}
          </SelectItem>
          <SelectItem value="suspended">
            {{ t('admin.users.status_suspended') }}
          </SelectItem>
          <SelectItem value="unverified">
            {{ t('admin.users.status_unverified') }}
          </SelectItem>
        </SelectContent>
      </Select>

      <Button
        type="submit"
        variant="outline"
        size="sm"
      >
        {{ t('admin.common.search') }}
      </Button>
      <Button
        type="button"
        variant="ghost"
        size="sm"
        @click="clear"
      >
        {{ t('admin.common.clear') }}
      </Button>
    </form>

    <AdminTable
      :columns="columns"
      :rows="users"
      :empty="t('admin.users.empty')"
      @page="goToPage"
    >
      <template #cell-account="{ row }">
        <Link
          :href="admin.users.show.url(row.id)"
          class="hover:underline"
        >
          {{ row.name }}
        </Link>
        <span class="block text-sm text-subtle">{{ row.email }}</span>
      </template>

      <template #cell-plan="{ row }">
        {{ row.plan }}
      </template>

      <template #cell-role="{ row }">
        {{ t(`admin.users.role_${row.role}`) }}
      </template>

      <template #cell-status="{ row }">
        <Badge
          v-if="row.suspended"
          variant="destructive"
        >
          {{ t('admin.users.status_suspended') }}
        </Badge>
        <Badge
          v-else-if="!row.verified"
          variant="outline"
        >
          {{ t('admin.users.unverified') }}
        </Badge>
        <Badge
          v-else
          variant="secondary"
        >
          {{ t('admin.users.status_active') }}
        </Badge>
      </template>

      <template #cell-createdAt="{ row }">
        {{ row.createdAt }}
      </template>

      <template #actions="{ row }">
        <Button
          variant="outline"
          size="sm"
          as-child
        >
          <Link :href="admin.users.show.url(row.id)">
            {{ t('admin.users.open') }}
          </Link>
        </Button>
      </template>
    </AdminTable>
  </div>
</template>
