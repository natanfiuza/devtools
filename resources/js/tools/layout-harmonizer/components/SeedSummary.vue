<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { formatHex, formatHex8 } from '@/lib/color';
import type { LayoutSeedInfo, LayoutStrategy } from '../engine';

/**
 * Resumo da seed usada no quadro: original (com alpha), efetiva, fundo da
 * composicao e estrategia. Mostra o ultimo resultado valido, que e o que o
 * quadro exibe enquanto a entrada atual estiver invalida.
 */

const props = defineProps<{
    seed: LayoutSeedInfo;
    strategy: LayoutStrategy;
}>();

const { t } = useI18n();

const original = computed(() =>
    props.seed.original.a < 1
        ? formatHex8(props.seed.original)
        : formatHex(props.seed.original),
);

const effective = computed(() => formatHex(props.seed.effective));

const background = computed(() =>
    props.seed.background === null
        ? null
        : formatHex({ ...props.seed.background, a: 1 }),
);

const hasAlpha = computed(() => props.seed.original.a < 1);

const strategyName = computed(() =>
    t(`tools.layout-harmonizer.strategy.${props.strategy}.name`),
);
</script>

<template>
    <dl
        class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-xs"
        data-test="seed-summary"
    >
        <dt class="text-muted-foreground">
            {{ t('tools.layout-harmonizer.seed.summary.original') }}
        </dt>
        <dd class="font-mono">{{ original }}</dd>

        <template v-if="hasAlpha">
            <dt class="text-muted-foreground">
                {{ t('tools.layout-harmonizer.seed.summary.effective') }}
            </dt>
            <dd class="font-mono">{{ effective }}</dd>

            <dt class="text-muted-foreground">
                {{ t('tools.layout-harmonizer.seed.summary.background') }}
            </dt>
            <dd class="font-mono">{{ background }}</dd>
        </template>

        <dt class="text-muted-foreground">
            {{ t('tools.layout-harmonizer.seed.summary.strategy') }}
        </dt>
        <dd>{{ strategyName }}</dd>
    </dl>

    <p
        v-if="hasAlpha"
        class="text-xs text-muted-foreground"
        data-test="seed-alpha-note"
    >
        {{ t('tools.layout-harmonizer.seed.summary.alpha_note') }}
    </p>

    <p
        v-if="seed.achromatic"
        class="text-xs text-muted-foreground"
        data-test="seed-achromatic-note"
    >
        {{ t('tools.layout-harmonizer.seed.summary.achromatic') }}
    </p>
</template>
