<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import type { HslChannel, RgbChannel } from '@/composables/useColorState';
import { normalizeHue, type Hsl, type Rgba } from '@/lib/color';
import { cssColor } from './lib/style';

/**
 * Sliders de canal: RGB/RGBA e HSL/HSLA.
 *
 * Cada linha tem o slider e o numero - o slider e para a mao, o numero para o
 * valor exato e acessivel. O "A" de RGBA/HSLA nao aparece aqui: o AlphaSlider
 * logo acima e a unica fonte de opacidade, e dois controles para o mesmo dado
 * so criariam divergencia. `finalize` sai no fim do gesto, nunca a cada quadro.
 */

const props = defineProps<{
    rgba: Rgba;
    hsl: Hsl;
}>();

const emit = defineEmits<{
    'set-rgb': [channel: RgbChannel, value: number];
    'set-hsl': [channel: HslChannel, value: number];
    finalize: [];
}>();

const { t } = useI18n();

interface ChannelRow<C extends string> {
    channel: C;
    label: string;
    value: number;
    max: number;
    gradient: string;
}

const thumb = computed(() => cssColor({ ...props.rgba, a: 1 }));

const rgbRows = computed<ChannelRow<RgbChannel>[]>(() => [
    rgbRow('r', 'red'),
    rgbRow('g', 'green'),
    rgbRow('b', 'blue'),
]);

const hslRows = computed<ChannelRow<HslChannel>[]>(() => [
    {
        channel: 'h',
        label: channelLabel('hue'),
        value: Math.round(props.hsl.h) % 360,
        max: 360,
        gradient:
            'linear-gradient(to right, #ff0000 0%, #ffff00 17%, #00ff00 33%, #00ffff 50%, #0000ff 67%, #ff00ff 83%, #ff0000 100%)',
    },
    {
        channel: 's',
        label: channelLabel('saturation'),
        value: Math.round(props.hsl.s),
        max: 100,
        gradient: `linear-gradient(to right, ${hslCss(0)}, ${hslCss(100)})`,
    },
    {
        channel: 'l',
        label: channelLabel('lightness'),
        value: Math.round(props.hsl.l),
        max: 100,
        gradient: `linear-gradient(to right, ${lightnessCss(0)}, ${lightnessCss(50)}, ${lightnessCss(100)})`,
    },
]);

function channelLabel(key: string): string {
    return t(`tools.shared.color_picker.channels.${key}`);
}

function rgbRow(
    channel: RgbChannel,
    key: string,
): ChannelRow<RgbChannel> {
    const from = withChannel(props.rgba, channel, 0);
    const to = withChannel(props.rgba, channel, 255);

    return {
        channel,
        label: channelLabel(key),
        value: Math.round(props.rgba[channel]),
        max: 255,
        gradient: `linear-gradient(to right, ${cssColor({ ...from, a: 1 })}, ${cssColor({ ...to, a: 1 })})`,
    };
}

function withChannel(color: Rgba, channel: RgbChannel, value: number): Rgba {
    const next: Rgba = { ...color };

    next[channel] = value;

    return next;
}

/** Matiz e saturacao atuais; so o canal pedido muda no degradado. */
function hslCss(saturation: number): string {
    return `hsl(${Math.round(normalizeHue(props.hsl.h))} ${saturation}% ${Math.round(props.hsl.l)}%)`;
}

function lightnessCss(lightness: number): string {
    return `hsl(${Math.round(normalizeHue(props.hsl.h))} ${Math.round(props.hsl.s)}% ${lightness}%)`;
}

function valueOf(event: Event): number | null {
    const raw = (event.target as HTMLInputElement).value.trim();

    if (raw === '') {
        return null;
    }

    const value = Number(raw);

    return Number.isFinite(value) ? value : null;
}

function onRgbInput(channel: RgbChannel, event: Event): void {
    const value = valueOf(event);

    if (value !== null) {
        emit('set-rgb', channel, value);
    }
}

function onHslInput(channel: HslChannel, event: Event): void {
    const value = valueOf(event);

    if (value !== null) {
        emit('set-hsl', channel, value);
    }
}

function onNumberChange(
    apply: () => void,
    event: Event,
): void {
    if (valueOf(event) === null) {
        return;
    }

    apply();
    emit('finalize');
}

function onRgbNumber(channel: RgbChannel, event: Event): void {
    onNumberChange(() => onRgbInput(channel, event), event);
}

function onHslNumber(channel: HslChannel, event: Event): void {
    onNumberChange(() => onHslInput(channel, event), event);
}
</script>

<template>
  <div class="flex flex-col gap-3">
    <span class="text-sm font-medium">
      {{ t('tools.shared.color_picker.channels.title') }}
    </span>

    <Tabs default-value="rgb">
      <TabsList
        class="grid w-full grid-cols-2"
        data-test="channels-tabs"
      >
        <TabsTrigger
          value="rgb"
          data-test="channel-tab-rgb"
        >
          {{ t('tools.shared.color_picker.channels.rgb') }}
        </TabsTrigger>
        <TabsTrigger
          value="hsl"
          data-test="channel-tab-hsl"
        >
          {{ t('tools.shared.color_picker.channels.hsl') }}
        </TabsTrigger>
      </TabsList>

      <TabsContent
        value="rgb"
        class="mt-3 flex flex-col gap-2"
      >
        <div
          v-for="row in rgbRows"
          :key="row.channel"
          class="grid grid-cols-[minmax(0,5rem)_minmax(0,1fr)_4.5rem] items-center gap-2"
        >
          <span
            class="truncate text-xs font-medium"
            :title="row.label"
          >
            {{ row.label }}
          </span>
          <input
            type="range"
            min="0"
            :max="row.max"
            step="1"
            :value="row.value"
            :aria-label="row.label"
            class="h-2 w-full cursor-pointer appearance-none rounded-full outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 [&::-moz-range-thumb]:size-4 [&::-moz-range-thumb]:rounded-full [&::-moz-range-thumb]:border-2 [&::-moz-range-thumb]:border-white [&::-moz-range-thumb]:bg-[var(--thumb-color)] [&::-moz-range-thumb]:shadow-md [&::-webkit-slider-thumb]:size-4 [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:border-2 [&::-webkit-slider-thumb]:border-white [&::-webkit-slider-thumb]:bg-[var(--thumb-color)] [&::-webkit-slider-thumb]:shadow-md"
            :style="{ backgroundImage: row.gradient, '--thumb-color': thumb }"
            :data-test="`channel-slider-${row.channel}`"
            @input="onRgbInput(row.channel, $event)"
            @change="emit('finalize')"
            @blur="emit('finalize')"
          >
          <input
            type="number"
            min="0"
            :max="row.max"
            step="1"
            :value="row.value"
            :aria-label="row.label"
            class="h-8 w-full min-w-0 rounded-md border border-input bg-transparent px-2 text-right text-sm tabular-nums outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            :data-test="`channel-value-${row.channel}`"
            @change="onRgbNumber(row.channel, $event)"
            @keydown.enter.prevent="emit('finalize')"
          >
        </div>
      </TabsContent>

      <TabsContent
        value="hsl"
        class="mt-3 flex flex-col gap-2"
      >
        <div
          v-for="row in hslRows"
          :key="row.channel"
          class="grid grid-cols-[minmax(0,5rem)_minmax(0,1fr)_4.5rem] items-center gap-2"
        >
          <span
            class="truncate text-xs font-medium"
            :title="row.label"
          >
            {{ row.label }}
          </span>
          <input
            type="range"
            min="0"
            :max="row.max"
            step="1"
            :value="row.value"
            :aria-label="row.label"
            class="h-2 w-full cursor-pointer appearance-none rounded-full outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 [&::-moz-range-thumb]:size-4 [&::-moz-range-thumb]:rounded-full [&::-moz-range-thumb]:border-2 [&::-moz-range-thumb]:border-white [&::-moz-range-thumb]:bg-[var(--thumb-color)] [&::-moz-range-thumb]:shadow-md [&::-webkit-slider-thumb]:size-4 [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:border-2 [&::-webkit-slider-thumb]:border-white [&::-webkit-slider-thumb]:bg-[var(--thumb-color)] [&::-webkit-slider-thumb]:shadow-md"
            :style="{ backgroundImage: row.gradient, '--thumb-color': thumb }"
            :data-test="`channel-slider-${row.channel}`"
            @input="onHslInput(row.channel, $event)"
            @change="emit('finalize')"
            @blur="emit('finalize')"
          >
          <input
            type="number"
            min="0"
            :max="row.max"
            step="1"
            :value="row.value"
            :aria-label="row.label"
            class="h-8 w-full min-w-0 rounded-md border border-input bg-transparent px-2 text-right text-sm tabular-nums outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            :data-test="`channel-value-${row.channel}`"
            @change="onHslNumber(row.channel, $event)"
            @keydown.enter.prevent="emit('finalize')"
          >
        </div>
      </TabsContent>
    </Tabs>
  </div>
</template>
