<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { cssColor } from '@/components/colors/lib/style';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatHex } from '@/lib/color';
import type { LayoutTheme, LayoutTokenId } from '../engine';
import { formatRatio } from '../lib/boardView';
import type { LayoutEditTarget } from '../lib/editState';
import type { LayoutSuggestion } from '../lib/suggestions';

/**
 * Sugestoes de contraste, abaixo dos quadros.
 *
 * Nada e aplicado sozinho: cada linha propoe a menor mudanca de luminosidade
 * que passa na meta escolhida contra todos os fundos daquele token no tema.
 * A meta nunca altera cores; ela so decide o que a sugestao precisa atingir.
 */

const props = defineProps<{
    suggestions: LayoutSuggestion[];
    accept: (theme: LayoutTheme, token: LayoutTokenId) => void;
    acceptBoth: (token: LayoutTokenId) => void;
}>();

const target = defineModel<LayoutEditTarget>('target', { required: true });

const { t, locale } = useI18n();

const TARGETS: readonly LayoutEditTarget[] = ['aa', 'aaa'];

function themeLabel(theme: LayoutTheme): string {
    return t(`tools.layout-harmonizer.view.${theme}`);
}

function ratio(value: number): string {
    return formatRatio(value, locale.value);
}

function currentHex(suggestion: LayoutSuggestion): string {
    return formatHex({ ...suggestion.current, a: 1 });
}

function proposalHex(suggestion: LayoutSuggestion): string {
    return suggestion.proposal
        ? formatHex({ ...suggestion.proposal, a: 1 })
        : '';
}

/** "Aceitar nos dois temas" so aparece quando os dois falham no token. */
function hasBoth(suggestion: LayoutSuggestion): boolean {
    return props.suggestions.some(
        (other) =>
            other.token === suggestion.token &&
            other.theme !== suggestion.theme &&
            other.proposal !== null,
    );
}

function deltaLightness(suggestion: LayoutSuggestion): string {
    return new Intl.NumberFormat(locale.value.replace('_', '-'), {
        minimumFractionDigits: 3,
        maximumFractionDigits: 3,
        signDisplay: 'exceptZero',
    }).format(suggestion.deltaLightness);
}
</script>

<template>
  <section
    class="space-y-3 rounded-xl border p-4"
    :aria-label="t('tools.layout-harmonizer.a11y.suggestions')"
    data-test="suggestions-panel"
  >
    <div class="flex flex-wrap items-center justify-between gap-2">
      <div>
        <h2 class="text-base font-semibold">
          {{ t('tools.layout-harmonizer.suggestions.title') }}
        </h2>
        <p class="text-xs text-muted-foreground">
          {{ t('tools.layout-harmonizer.suggestions.description') }}
        </p>
      </div>

      <div
        class="flex flex-wrap gap-1"
        role="group"
        :aria-label="
          t('tools.layout-harmonizer.a11y.suggestion_target')
        "
        data-test="suggestion-target"
      >
        <Button
          v-for="option in TARGETS"
          :key="option"
          type="button"
          size="sm"
          :variant="target === option ? 'secondary' : 'outline'"
          :aria-pressed="target === option"
          :data-test="`suggestion-target-${option}`"
          @click="target = option"
        >
          {{
            t(
              `tools.layout-harmonizer.suggestions.target.${option}`,
            )
          }}
        </Button>
      </div>
    </div>

    <p
      v-if="suggestions.length === 0"
      class="text-xs text-muted-foreground"
      data-test="suggestions-none"
    >
      {{ t('tools.layout-harmonizer.suggestions.none') }}
    </p>

    <ul
      v-else
      class="space-y-2"
    >
      <li
        v-for="suggestion in suggestions"
        :key="`${suggestion.theme}-${suggestion.token}`"
        class="space-y-2 rounded-lg border p-3 text-xs"
        data-test="suggestion-row"
        :data-theme="suggestion.theme"
        :data-token="suggestion.token"
      >
        <div class="flex flex-wrap items-center gap-2">
          <Badge variant="secondary">
            {{ themeLabel(suggestion.theme) }}
          </Badge>
          <code class="font-mono text-sm font-medium">{{
            suggestion.token
          }}</code>

          <template v-if="suggestion.proposal !== null">
            <span
              class="size-5 rounded-sm border"
              :style="{
                backgroundColor: cssColor(suggestion.current),
              }"
              aria-hidden="true"
            />
            <span class="font-mono text-muted-foreground">
              {{ currentHex(suggestion) }}
            </span>
            <span aria-hidden="true">→</span>
            <span
              class="size-5 rounded-sm border"
              :style="{
                backgroundColor: cssColor(suggestion.proposal),
              }"
              aria-hidden="true"
            />
            <span
              class="font-mono"
              data-test="suggestion-hex"
            >
              {{ proposalHex(suggestion) }}
            </span>
            <span
              class="text-muted-foreground"
              data-test="suggestion-delta"
            >
              {{
                t(
                  'tools.layout-harmonizer.suggestions.delta_lightness',
                  { delta: deltaLightness(suggestion) },
                )
              }}
            </span>
          </template>

          <Badge
            v-if="suggestion.proposal === null"
            variant="outline"
            data-test="suggestion-inviable-badge"
          >
            {{
              t(
                'tools.layout-harmonizer.suggestions.inviable_badge',
              )
            }}
          </Badge>
        </div>

        <ul class="flex flex-wrap gap-x-4 gap-y-1">
          <li
            v-for="entry in suggestion.ratios"
            :key="entry.background"
            class="flex items-baseline gap-1"
            :data-test="`suggestion-ratio-${entry.background}`"
          >
            <code class="font-mono">{{ entry.background }}</code>
            <span class="text-muted-foreground">
              {{ ratio(entry.current) }}
            </span>
            <template v-if="suggestion.proposal !== null">
              <span aria-hidden="true">→</span>
              <span class="font-medium">
                {{ ratio(entry.projected) }}
              </span>
            </template>
          </li>
        </ul>

        <p
          v-if="suggestion.proposal === null"
          class="text-muted-foreground"
          data-test="suggestion-inviable"
        >
          {{
            t('tools.layout-harmonizer.suggestions.inviable', {
              target: t(
                `tools.layout-harmonizer.suggestions.target.${suggestion.target}`,
              ),
            })
          }}
        </p>

        <div
          v-else
          class="flex flex-wrap gap-2"
        >
          <Button
            type="button"
            size="sm"
            data-test="suggestion-accept"
            @click="accept(suggestion.theme, suggestion.token)"
          >
            {{
              t(
                'tools.layout-harmonizer.suggestions.accept_theme',
                {
                  theme: themeLabel(suggestion.theme),
                },
              )
            }}
          </Button>
          <Button
            v-if="hasBoth(suggestion)"
            type="button"
            variant="outline"
            size="sm"
            data-test="suggestion-accept-both"
            @click="acceptBoth(suggestion.token)"
          >
            {{
              t('tools.layout-harmonizer.suggestions.accept_both')
            }}
          </Button>
        </div>
      </li>
    </ul>
  </section>
</template>
