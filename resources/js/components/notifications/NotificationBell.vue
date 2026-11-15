<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Bell } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { index as notificationsIndex } from '@/routes/dashboard/notifications';

/**
 * Sino da area da conta (F7, ADR-142).
 *
 * O contador vem nas props compartilhadas a cada navegacao - sem polling e
 * sem endpoint extra. Visitante nao recebe o prop, e o sino nem aparece.
 */
const { t } = useI18n();
const page = usePage();

const unread = computed(() => page.props.notifications?.unread ?? 0);
const label = computed(() =>
    t('notifications.bell.unread', { count: unread.value }),
);
</script>

<template>
  <Link
    :href="notificationsIndex.url()"
    :aria-label="unread > 0 ? label : t('notifications.bell.label')"
    class="relative inline-flex size-9 items-center justify-center rounded-md text-muted-foreground transition hover:bg-accent hover:text-foreground focus:outline-2 focus:outline-offset-2 focus:outline-ring"
    data-test="notification-bell"
  >
    <Bell class="size-4" />
    <span
      v-if="unread > 0"
      class="absolute -right-0.5 -top-0.5 min-w-4 rounded-full bg-destructive px-1 text-center text-[10px] font-semibold leading-4 text-destructive-foreground"
      data-test="notification-bell-badge"
    >
      {{ unread > 99 ? '99+' : unread }}
    </span>
  </Link>
</template>
