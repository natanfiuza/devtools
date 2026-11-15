<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import colorsPalettes from '@/routes/colors/palettes';
import colorsProfiles from '@/routes/colors/profiles';
import {
    index as notificationsIndex,
    preferences as preferencesPatch,
    read as markReadRoute,
    readAll as markAllReadRoute,
} from '@/routes/dashboard/notifications';
import type {
    AccountNotification,
    NotificationsPageProps,
    NotificationPreferences,
} from '@/types';

/**
 * Sino e preferencias de notificacao da conta (F7, ADR-142).
 *
 * A pagina e unica: a lista paginada e os quatro toggles de e-mail. O canal do
 * sino e sempre ligado; o toggle so decide se aquele tipo tambem chega por
 * e-mail. O payload nunca traz o corpo do comentario - o aviso leva ao
 * recurso, nao o reproduz.
 */

const props = defineProps<NotificationsPageProps>();

const { t } = useI18n();
const page = usePage();

const localeSlug = computed(() => page.props.localeSlug ?? 'en');

const items = computed(() => props.notifications.data);
const hasPages = computed(() => props.notifications.last_page > 1);
const unreadCount = computed(
    () => items.value.filter((item) => !item.read).length,
);

const preferences = ref<NotificationPreferences>({ ...props.preferences });
const saved = ref(false);

watch(
    () => props.preferences,
    (value) => {
        preferences.value = { ...value };
    },
);

/** Texto do fato; o anonimo nunca vira nome de conta. */
function text(item: AccountNotification): string {
    const data = item.data;
    const actor = data.follower_name ?? data.actor_name ?? t('notifications.someone');
    const palette = data.palette_name ?? '';

    const key =
        item.kind === 'palette_liked' && data.anonymous
            ? 'palette_liked_anonymous'
            : item.kind;

    return t(`notifications.kinds.${key}`, { actor, palette });
}

function paletteUrl(item: AccountNotification): string | null {
    if (item.data.palette_slug === null) {
        return null;
    }

    return colorsPalettes.show.url({
        locale: localeSlug.value,
        slug: item.data.palette_slug,
    });
}

function actorUrl(item: AccountNotification): string | null {
    const username =
        item.data.follower_username ??
        (item.data.anonymous ? null : item.data.actor_username);

    if (username === null) {
        return null;
    }

    return colorsProfiles.show.url({
        locale: localeSlug.value,
        username,
    });
}

function markRead(item: AccountNotification): void {
    if (item.read) {
        return;
    }

    router.post(markReadRoute.url({ notification: item.id }), {}, {
        preserveScroll: true,
        preserveState: true,
    });
}

function markAllRead(): void {
    router.post(markAllReadRoute.url(), {}, {
        preserveScroll: true,
        preserveState: true,
    });
}

function goToPage(target: number): void {
    if (target < 1 || target > props.notifications.last_page) {
        return;
    }

    router.get(
        notificationsIndex.url({ query: { page: target } }),
        {},
        { preserveScroll: true, preserveState: true },
    );
}

function updatePreference(
    key: keyof NotificationPreferences,
    value: boolean,
): void {
    const previous = preferences.value[key];

    preferences.value[key] = value;
    saved.value = false;

    router.patch(
        preferencesPatch.url(),
        { ...preferences.value },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                saved.value = true;
            },
            onError: () => {
                preferences.value[key] = previous;
            },
        },
    );
}
</script>

<template>
  <Head :title="t('notifications.title')" />

  <div class="flex flex-col gap-8">
    <header class="flex flex-col gap-1">
      <h2 class="text-xl font-semibold">
        {{ t('notifications.title') }}
      </h2>
      <p class="text-sm text-muted-foreground">
        {{ t('notifications.description') }}
      </p>
    </header>

    <section class="flex flex-col gap-3">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <h3 class="text-base font-medium">
          {{ t('notifications.title') }}
        </h3>
        <Button
          v-if="unreadCount > 0"
          type="button"
          variant="outline"
          size="sm"
          data-test="notifications-mark-all"
          @click="markAllRead"
        >
          {{ t('notifications.mark_all_read') }}
        </Button>
      </div>

      <p
        v-if="items.length === 0"
        class="text-sm text-muted-foreground"
        data-test="notifications-empty"
      >
        {{ t('notifications.empty') }}
      </p>

      <ul
        v-else
        class="flex flex-col divide-y divide-border rounded-xl border border-border"
        data-test="notifications-list"
      >
        <li
          v-for="item in items"
          :key="item.id"
          class="flex flex-wrap items-start justify-between gap-3 px-4 py-3"
          :class="item.read ? '' : 'bg-accent/40'"
          data-test="notification-row"
        >
          <div class="flex flex-col gap-1">
            <p class="text-sm">
              {{ text(item) }}
            </p>
            <div class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
              <span data-test="notification-created-at">
                {{ item.created_at }}
              </span>
              <Badge
                v-if="!item.read"
                variant="secondary"
                data-test="notification-unread"
              >
                {{ t('notifications.unread_badge') }}
              </Badge>
              <a
                v-if="paletteUrl(item) !== null"
                :href="paletteUrl(item) ?? '#'"
                class="hover:underline"
                data-test="notification-palette-link"
              >
                {{ item.data.palette_name }}
              </a>
              <a
                v-if="actorUrl(item) !== null"
                :href="actorUrl(item) ?? '#'"
                class="hover:underline"
                data-test="notification-actor-link"
              >
                @{{
                  item.data.follower_username ?? item.data.actor_username
                }}
              </a>
            </div>
          </div>

          <Button
            v-if="!item.read"
            type="button"
            variant="ghost"
            size="sm"
            data-test="notification-mark-read"
            @click="markRead(item)"
          >
            {{ t('notifications.mark_read') }}
          </Button>
        </li>
      </ul>

      <div
        v-if="hasPages"
        class="flex flex-wrap items-center justify-center gap-2"
        data-test="notifications-pagination"
      >
        <Button
          type="button"
          variant="outline"
          size="sm"
          :disabled="notifications.current_page <= 1"
          data-test="notifications-page-prev"
          @click="goToPage(notifications.current_page - 1)"
        >
          {{ t('notifications.pagination.previous') }}
        </Button>

        <span class="text-xs text-muted-foreground">
          {{
            t('notifications.pagination.status', {
              page: notifications.current_page,
              pages: notifications.last_page,
            })
          }}
        </span>

        <Button
          type="button"
          variant="outline"
          size="sm"
          :disabled="notifications.current_page >= notifications.last_page"
          data-test="notifications-page-next"
          @click="goToPage(notifications.current_page + 1)"
        >
          {{ t('notifications.pagination.next') }}
        </Button>
      </div>
    </section>

    <section
      class="flex flex-col gap-3"
      data-test="notification-preferences"
    >
      <div class="flex flex-col gap-1">
        <h3 class="text-base font-medium">
          {{ t('notifications.preferences.title') }}
        </h3>
        <p class="text-sm text-muted-foreground">
          {{ t('notifications.preferences.description') }}
        </p>
      </div>

      <div
        v-for="key in (
          [
            'email_on_like',
            'email_on_follow',
            'email_on_comment',
            'email_on_reply',
          ] as const
        )"
        :key="key"
        class="flex items-center gap-2"
      >
        <Checkbox
          :id="`notification-preference-${key}`"
          :data-test="`notification-preference-${key}`"
          :model-value="preferences[key]"
          @update:model-value="
            (value) => updatePreference(key, value === true)
          "
        />
        <Label
          :for="`notification-preference-${key}`"
          class="text-sm"
        >
          {{ t(`notifications.preferences.${key}`) }}
        </Label>
      </div>

      <p
        v-if="saved"
        class="text-xs text-muted-foreground"
        role="status"
        data-test="notification-preferences-saved"
      >
        {{ t('notifications.preferences.saved') }}
      </p>
    </section>
  </div>
</template>
