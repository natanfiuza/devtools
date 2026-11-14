<script setup lang="ts">
import { UserCheck, UserPlus } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { useUserFollow } from '@/composables/useUserFollow';

/**
 * Seguir a conta dona de um perfil ou paleta (F6, ADR-136).
 *
 * Sem conta o botao nao existe (`canFollow` falso): seguir exige identidade
 * persistente. Na pagina de perfil a contagem aparece ao lado do botao e
 * acompanha a escrita otimista.
 */

const props = withDefaults(
    defineProps<{
        userId: number;
        following: boolean;
        followersCount?: number;
        canFollow: boolean;
        showCount?: boolean;
    }>(),
    { followersCount: 0, showCount: false },
);

const { t } = useI18n();

const { following, followersCount, pending, failed, toggle } = useUserFollow({
    userId: props.userId,
    following: props.following,
    followersCount: props.followersCount,
});

const countLabel = computed(() => {
    if (followersCount.value === 0) {
        return t('colors.social.followers_zero');
    }

    if (followersCount.value === 1) {
        return t('colors.social.followers_one');
    }

    return t('colors.social.followers_many', {
        count: followersCount.value,
    });
});
</script>

<template>
  <div
    v-if="canFollow"
    class="flex flex-wrap items-center gap-2"
  >
    <Button
      type="button"
      :variant="following ? 'outline' : 'default'"
      :disabled="pending"
      :aria-pressed="following"
      data-test="follow-button"
      @click="toggle"
    >
      <UserCheck
        v-if="following"
        class="size-4"
      />
      <UserPlus
        v-else
        class="size-4"
      />
      {{
        following ? t('colors.social.following') : t('colors.social.follow')
      }}
    </Button>

    <span
      v-if="showCount === true"
      class="text-xs text-muted-foreground"
      data-test="followers-count"
    >
      {{ countLabel }}
    </span>

    <p
      v-if="failed"
      class="text-xs text-destructive"
      role="alert"
      data-test="follow-error"
    >
      {{ t('colors.social.follow_error') }}
    </p>
  </div>
</template>
