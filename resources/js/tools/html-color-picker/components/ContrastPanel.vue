<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    BLACK,
    WHITE,
    contrastReport,
    formatHex,
    parseColor,
    type ContrastVerdict,
    type Rgb,
    type Rgba,
} from '@/lib/color';
import { checkerboardStyle, cssColor } from '@/components/colors/lib/style';

/**
 * Contraste WCAG contra preto e branco, sobre um fundo explicito.
 *
 * O alpha e composto no fundo antes da conta - uma cor a 50% nao tem o
 * contraste da cor cheia. A razao exibida tem 2 casas (arredondamento de
 * exibicao), mas a classificacao AA/AAA usa o valor cru.
 */

const props = defineProps<{
    rgba: Rgba;
}>();

const { t } = useI18n();

type BackdropMode = 'white' | 'black' | 'custom';

const mode = ref<BackdropMode>('white');
const custom = ref('#7c3aed');

const customRgb = computed<Rgb>(() => {
    const result = parseColor(custom.value);

    if (!result.ok) {
        return WHITE;
    }

    return {
        r: result.color.rgba.r,
        g: result.color.rgba.g,
        b: result.color.rgba.b,
    };
});

const background = computed<Rgb>(() => {
    switch (mode.value) {
        case 'black':
            return BLACK;
        case 'custom':
            return customRgb.value;
        default:
            return WHITE;
    }
});

const report = computed(() => contrastReport(props.rgba, background.value));

const backgroundHex = computed(() => formatHex({ ...background.value, a: 1 }));

const rows = computed(() => [
    {
        key: 'black' as const,
        verdict: report.value.black,
    },
    {
        key: 'white' as const,
        verdict: report.value.white,
    },
]);

function ratioText(verdict: ContrastVerdict): string {
    return verdict.ratio.toFixed(2);
}

function emoji(verdict: ContrastVerdict): string {
    return verdict.aaNormal ? '👍' : '👎';
}

function labelFor(verdict: ContrastVerdict): string {
    const key = verdict === report.value.black ? 'black_text' : 'white_text';

    return t(`tools.html-color-picker.contrast.${key}`, {
        ratio: ratioText(verdict),
        verdict: emoji(verdict),
    });
}

function rationale(verdict: ContrastVerdict): string {
    const word = (pass: boolean): string =>
        pass
            ? t('tools.html-color-picker.contrast.pass_word')
            : t('tools.html-color-picker.contrast.fail_word');

    return [
        `AA: ${word(verdict.aaNormal)}`,
        `AAA: ${word(verdict.aaaNormal)}`,
        `${t('tools.html-color-picker.contrast.aa_large')}: ${word(verdict.aaLarge)}`,
        `${t('tools.html-color-picker.contrast.aaa_large')}: ${word(verdict.aaaLarge)}`,
    ].join('. ');
}

const badgeClass = 'text-[10px]';
</script>

<template>
  <section class="rounded-xl border bg-card p-4">
    <h2 class="text-sm font-semibold">
      {{ t('tools.html-color-picker.contrast.title') }}
    </h2>
    <p class="mt-1 text-xs text-muted-foreground">
      {{ t('tools.html-color-picker.contrast.description') }}
    </p>

    <div class="mt-3 flex flex-col gap-2">
      <span class="text-xs font-medium">
        {{ t('tools.html-color-picker.contrast.backdrop') }}
      </span>
      <div class="flex flex-wrap items-center gap-2">
        <Button
          type="button"
          size="sm"
          :variant="mode === 'white' ? 'default' : 'outline'"
          @click="mode = 'white'"
        >
          {{ t('tools.html-color-picker.contrast.white') }}
        </Button>
        <Button
          type="button"
          size="sm"
          :variant="mode === 'black' ? 'default' : 'outline'"
          @click="mode = 'black'"
        >
          {{ t('tools.html-color-picker.contrast.black') }}
        </Button>
        <Button
          type="button"
          size="sm"
          :variant="mode === 'custom' ? 'default' : 'outline'"
          @click="mode = 'custom'"
        >
          {{ t('tools.html-color-picker.contrast.custom') }}
        </Button>
      </div>

      <div
        v-if="mode === 'custom'"
        class="flex items-end gap-2"
      >
        <div class="flex-1">
          <Label for="contrast-background">
            {{ t('tools.html-color-picker.contrast.custom') }}
          </Label>
          <Input
            id="contrast-background"
            v-model="custom"
            class="mt-1"
            autocomplete="off"
            spellcheck="false"
          />
        </div>
        <span
          class="mb-1 flex items-center gap-2 text-xs text-muted-foreground"
        >
          <span
            class="relative size-5 overflow-hidden rounded border"
            :style="checkerboardStyle"
            aria-hidden="true"
          >
            <span
              class="absolute inset-0"
              :style="{
                backgroundColor: cssColor({
                  ...background,
                  a: 1,
                }),
              }"
            />
          </span>
          {{ backgroundHex }}
        </span>
      </div>
    </div>

    <ul class="mt-4 flex flex-col gap-3">
      <li
        v-for="row in rows"
        :key="row.key"
        class="flex flex-col gap-1"
      >
        <span class="text-sm font-medium">
          {{ labelFor(row.verdict) }}
        </span>
        <span class="flex flex-wrap gap-1">
          <Badge
            :variant="
              row.verdict.aaNormal ? 'secondary' : 'outline'
            "
            :class="badgeClass"
          >
            {{ t('tools.html-color-picker.contrast.aa_normal') }}
          </Badge>
          <Badge
            :variant="
              row.verdict.aaaNormal ? 'secondary' : 'outline'
            "
            :class="badgeClass"
          >
            {{ t('tools.html-color-picker.contrast.aaa_normal') }}
          </Badge>
          <Badge
            :variant="row.verdict.aaLarge ? 'secondary' : 'outline'"
            :class="badgeClass"
          >
            {{ t('tools.html-color-picker.contrast.aa_large') }}
          </Badge>
          <Badge
            :variant="
              row.verdict.aaaLarge ? 'secondary' : 'outline'
            "
            :class="badgeClass"
          >
            {{ t('tools.html-color-picker.contrast.aaa_large') }}
          </Badge>
        </span>
        <span class="sr-only">
          {{ rationale(row.verdict) }}
        </span>
      </li>
    </ul>

    <p class="mt-3 text-xs text-muted-foreground">
      {{ t('tools.html-color-picker.contrast.large_note') }}
    </p>
  </section>
</template>
