<script setup lang="ts">
import { Pipette } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatColor, type Rgba } from '@/lib/color';
import { isEyeDropperSupported, pickScreenColor } from '@/lib/eyedropper';
import { checkerboardStyle, cssColor } from '@/components/colors/lib/style';
import ImageSampler from './ImageSampler.vue';

/**
 * Previa: o quadrado grande, o conta-gotas e o aviso de gamut.
 *
 * O conta-gotas nativo, quando existe, mede a tela e devolve cor opaca; quando
 * nao existe, o mesmo botao abre o amostrador por imagem.
 */

const props = defineProps<{
    rgba: Rgba;
    outOfGamut: boolean;
    gamutSource: string | null;
}>();

const emit = defineEmits<{
    /** HEX vindo da tela ou da imagem (cor opaca). */
    picked: [hex: string];
}>();

const { t } = useI18n();

const supported = isEyeDropperSupported();
const imageOpen = ref(false);
const busy = ref(false);

const value = computed(() =>
    formatColor(props.rgba, props.rgba.a < 1 ? 'hex8' : 'hex'),
);

const alphaPercent = computed(() => Math.round(props.rgba.a * 100));

async function eyedropper(): Promise<void> {
    if (!supported) {
        imageOpen.value = true;

        return;
    }

    busy.value = true;

    try {
        const hex = await pickScreenColor();

        if (hex !== null) {
            emit('picked', hex);
        }
    } finally {
        busy.value = false;
    }
}

function onImagePicked(hex: string): void {
    emit('picked', hex);
    imageOpen.value = false;
}
</script>

<template>
  <div class="flex flex-col gap-3">
    <div class="flex items-center gap-3">
      <div
        class="relative size-20 shrink-0 overflow-hidden rounded-lg border shadow-xs"
        :style="checkerboardStyle"
        aria-hidden="true"
      >
        <div
          class="absolute inset-0"
          :style="{ backgroundColor: cssColor(rgba) }"
        />
      </div>
      <div class="min-w-0 flex-1">
        <p class="truncate font-mono text-lg font-medium tabular-nums">
          {{ value }}
        </p>
        <p
          v-if="rgba.a < 1"
          class="text-xs text-muted-foreground"
        >
          {{
            t('tools.html-color-picker.preview.alpha', {
              percent: alphaPercent,
            })
          }}
        </p>
        <div class="mt-2 flex flex-wrap items-center gap-2">
          <Button
            type="button"
            variant="outline"
            size="sm"
            :disabled="busy"
            data-test="color-eyedropper"
            @click="eyedropper"
          >
            <Pipette aria-hidden="true" />
            {{
              supported
                ? t(
                  'tools.html-color-picker.preview.eyedropper',
                )
                : t(
                  'tools.html-color-picker.preview.eyedropper_image',
                )
            }}
          </Button>
        </div>
      </div>
    </div>

    <p
      v-if="outOfGamut && gamutSource !== null"
      class="rounded-md border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-sm text-amber-700 dark:text-amber-300"
      role="status"
    >
      {{
        t('tools.html-color-picker.preview.gamut', {
          source: gamutSource,
          value: formatColor(rgba, 'oklch'),
        })
      }}
    </p>

    <Dialog v-model:open="imageOpen">
      <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-xl">
        <DialogHeader>
          <DialogTitle>
            {{ t('tools.html-color-picker.image.title') }}
          </DialogTitle>
          <DialogDescription>
            {{ t('tools.html-color-picker.image.description') }}
          </DialogDescription>
        </DialogHeader>
        <ImageSampler @picked="onImagePicked" />
      </DialogContent>
    </Dialog>
  </div>
</template>
