<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import admin from '@/routes/admin';
import type { AdminOverviewStats, AdminRecentAccount } from '@/types';

const props = defineProps<{
    stats: AdminOverviewStats;
    recent: AdminRecentAccount[];
}>();

const { t } = useI18n();

/** Quatro numeros, sem grafico (DESIGN: Painel administrativo). */
const cards = computed<Array<{ label: string; value: string }>>(() => [
    {
        label: t('admin.overview.accounts'),
        value: String(props.stats.accounts),
    },
    {
        label: t('admin.overview.subscribers'),
        value: String(props.stats.subscribers),
    },
    {
        label: t('admin.overview.revenue'),
        value: props.stats.revenue.formatted,
    },
    {
        label: t('admin.overview.suspended'),
        value: String(props.stats.suspended),
    },
]);
</script>

<template>
  <Head :title="t('admin.overview.title')" />

  <div class="space-y-6">
    <header>
      <h2 class="text-lg font-medium">
        {{ t('admin.overview.title') }}
      </h2>
      <p class="mt-1 text-sm text-subtle">
        {{ t('admin.overview.description') }}
      </p>
    </header>

    <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <div
        v-for="card in cards"
        :key="card.label"
        class="rounded-lg border border-border p-4"
      >
        <dt class="text-[12px] tracking-wide text-subtle uppercase">
          {{ card.label }}
        </dt>
        <dd class="mt-2 font-mono text-2xl">
          {{ card.value }}
        </dd>
      </div>
    </dl>

    <section class="space-y-2">
      <h3 class="text-sm font-medium">
        {{ t('admin.overview.recent') }}
      </h3>

      <div class="rounded-lg border border-border">
        <p
          v-if="recent.length === 0"
          class="px-4 py-10 text-center text-sm text-subtle"
        >
          {{ t('admin.overview.empty') }}
        </p>

        <ul
          v-else
          class="divide-y divide-border"
        >
          <li
            v-for="account in recent"
            :key="account.id"
            class="flex h-12 items-center justify-between gap-4 px-4"
          >
            <div class="min-w-0">
              <Link
                :href="admin.users.show.url(account.id)"
                class="truncate text-sm hover:underline"
              >
                {{ account.name }}
              </Link>
              <p class="truncate text-sm text-subtle">
                {{ account.email }}
              </p>
            </div>

            <div class="flex shrink-0 items-center gap-2">
              <Badge
                v-if="account.suspended"
                variant="destructive"
              >
                {{ t('admin.users.status_suspended') }}
              </Badge>
              <span class="font-mono text-[13px] text-subtle">
                {{ account.createdAt }}
              </span>
            </div>
          </li>
        </ul>
      </div>
    </section>
  </div>
</template>
