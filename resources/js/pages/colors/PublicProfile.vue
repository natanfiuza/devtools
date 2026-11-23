<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import PageHead from '@/components/PageHead.vue';
import { Badge } from '@/components/ui/badge';
import FollowButton from '@/components/colors/FollowButton.vue';
import PalettePagination from '@/components/colors/PalettePagination.vue';
import ProfilePaletteCard from '@/components/colors/ProfilePaletteCard.vue';
import { useInitials } from '@/composables/useInitials';
import colorsProfiles from '@/routes/colors/profiles';
import type { PublicProfileProps } from '@/types/colors';

/**
 * Perfil publico por username (F6, ADR-134).
 *
 * Vitrine da conta: nome, avatar, contadores e as paletas publicas ativas.
 * Nao lista seguidores nem expoe e-mail; a pagina e indexavel e cada cartao
 * leva a pagina publica da paleta. Seguir so aparece para quem tem conta e
 * nao e o proprio dono - o contador acompanha o botao.
 */

const props = defineProps<PublicProfileProps>();

const { t, locale } = useI18n();
const page = usePage();
const { getInitials } = useInitials();

const localeSlug = computed(() => page.props.localeSlug ?? 'en');

const memberSince = computed<string | null>(() => {
    if (props.profile.member_since === null) {
        return null;
    }

    const date = new Date(props.profile.member_since);

    if (Number.isNaN(date.getTime())) {
        return null;
    }

    return new Intl.DateTimeFormat(locale.value.replace('_', '-'), {
        month: 'long',
        year: 'numeric',
    }).format(date);
});

const followingLabel = computed(() => {
    if (props.profile.following_count === 0) {
        return t('colors.profile.following_zero');
    }

    if (props.profile.following_count === 1) {
        return t('colors.profile.following_one');
    }

    return t('colors.profile.following_many', {
        count: props.profile.following_count,
    });
});

function goToPage(target: number): void {
    if (target === props.palettes.current_page) {
        return;
    }

    router.get(
        colorsProfiles.show.url({
            locale: localeSlug.value,
            username: props.profile.username,
        }),
        { page: target },
        { preserveScroll: true, preserveState: true },
    );
}
</script>

<template>
  <PageHead />

  <article
    class="mx-auto flex w-full max-w-[1120px] flex-col gap-8 px-4 py-10"
    data-test="public-profile"
  >
    <header class="flex flex-col gap-4">
      <div class="flex flex-wrap items-center gap-4">
        <img
          v-if="profile.avatar !== null"
          :src="profile.avatar"
          :alt="profile.name"
          class="size-16 rounded-full border border-border object-cover"
          data-test="profile-avatar"
        >
        <span
          v-else
          class="flex size-16 items-center justify-center rounded-full border border-border bg-muted text-lg font-semibold text-muted-foreground"
          :aria-label="profile.name"
          data-test="profile-avatar-initials"
        >
          {{ getInitials(profile.name) }}
        </span>

        <div class="min-w-0">
          <h1
            class="text-3xl font-semibold tracking-tight"
            data-test="profile-name"
          >
            {{ profile.name }}
          </h1>
          <p
            class="text-sm text-muted-foreground"
            data-test="profile-username"
          >
            @{{ profile.username }}
          </p>
        </div>

        <Badge
          v-if="isOwnProfile"
          variant="secondary"
          data-test="profile-own-badge"
        >
          {{ t('colors.profile.own_badge') }}
        </Badge>
      </div>

      <div
        class="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-muted-foreground"
      >
        <span data-test="profile-following-count">
          {{ followingLabel }}
        </span>
        <span
          v-if="memberSince !== null"
          data-test="profile-member-since"
        >
          {{
            t('colors.profile.member_since', { date: memberSince })
          }}
        </span>
      </div>

      <FollowButton
        :user-id="profile.id"
        :following="following"
        :followers-count="profile.followers_count"
        :can-follow="canFollow"
        show-count
        data-test="profile-follow"
      />
    </header>

    <section
      class="flex flex-col gap-4"
      data-test="profile-palettes"
    >
      <h2 class="text-lg font-semibold">
        {{ t('colors.profile.palettes_title') }}
      </h2>

      <div
        v-if="palettes.data.length === 0"
        class="rounded-xl border border-dashed border-border p-8 text-center"
        data-test="profile-empty"
      >
        <p class="text-base font-medium">
          {{ t('colors.profile.empty_title') }}
        </p>
        <p class="mt-1 text-sm text-muted-foreground">
          {{ t('colors.profile.empty_description') }}
        </p>
      </div>

      <ul
        v-else
        class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3"
        data-test="profile-palette-list"
      >
        <li
          v-for="palette in palettes.data"
          :key="palette.id"
        >
          <ProfilePaletteCard :palette="palette" />
        </li>
      </ul>

      <PalettePagination
        :paginator="palettes"
        @select="goToPage"
      />
    </section>
  </article>
</template>
