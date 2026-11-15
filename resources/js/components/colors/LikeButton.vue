<script setup lang="ts">
import { Heart } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { usePaletteLike } from '@/composables/usePaletteLike';

/**
 * Curtida de uma paleta publica (F6, ADR-135).
 *
 * O dono ve so a contagem: curtir a propria paleta nao existe. Os demais -
 * com ou sem conta - curtem e descurem pelo mesmo botao; o visitante anonimo
 * recebe o cookie na resposta da API. O texto de contagem fica fora do botao
 * para nao sumir quando ele nao aparece.
 */

const props = defineProps<{
    paletteId: string;
    liked: boolean;
    likesCount: number;
    canLike: boolean;
}>();

const { t } = useI18n();

const { liked, likesCount, pending, failed, toggle } = usePaletteLike({
    paletteId: props.paletteId,
    liked: props.liked,
    likesCount: props.likesCount,
});

const label = computed(() =>
    liked.value ? t('colors.social.liked') : t('colors.social.like'),
);

const countLabel = computed(() => {
    if (likesCount.value === 0) {
        return t('colors.social.likes_zero');
    }

    if (likesCount.value === 1) {
        return t('colors.social.likes_one');
    }

    return t('colors.social.likes_many', { count: likesCount.value });
});
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <Button
            v-if="canLike"
            type="button"
            :variant="liked ? 'secondary' : 'outline'"
            :disabled="pending"
            :aria-pressed="liked"
            data-test="palette-like"
            @click="toggle"
        >
            <Heart class="size-4" :class="liked ? 'fill-current' : ''" />
            {{ label }}
        </Button>

        <span
            class="text-xs text-muted-foreground"
            data-test="palette-likes-count"
        >
            {{ countLabel }}
        </span>

        <p
            v-if="failed"
            class="text-xs text-destructive"
            role="alert"
            data-test="palette-like-error"
        >
            {{ t('colors.social.like_error') }}
        </p>
    </div>
</template>
