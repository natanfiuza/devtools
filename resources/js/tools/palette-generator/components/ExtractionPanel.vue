<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import FileDropzone from '@/components/tool/FileDropzone.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { Rgba } from '@/lib/color';
import { acceptAttribute } from '@/lib/file';
import { ACCEPTED_IMAGE_TYPES, decodeImage } from '../lib/decodeImage';
import type { ExtractedPalette } from '../lib/extractedPalette';
import { EXTRACTION_COUNTS, type ExtractionCount } from '../lib/extractContract';
import {
    createExtractionClient,
    type ExtractionClient,
} from '../lib/extractionClient';
import { spawnExtractionWorker } from '../lib/extract.workerHost';
import { fetchImage } from '../lib/fetchImage';
import type { ImageErrorCode } from '../lib/imageErrors';
import type { ImageLimits } from '../lib/imageLimits';
import ExtractedSwatch from './ExtractedSwatch.vue';

/**
 * Importacao de imagem e extracao das cores dominantes (ADR-122).
 *
 * Dois caminhos, um so resultado: arquivo local ou URL HTTPS (busca direta na
 * origem, com CORS; nunca proxy). O trabalho pesado roda no worker; esta tela
 * so orquestra e pinta - e e a unica dona do `runId`, que garante que a
 * resposta de uma imagem antiga nao sobrescreva a atual.
 *
 * A paleta extraida sai por `update:modelValue` e vive em `Tool.vue`; aqui nao
 * existe storage nem upload.
 */

const props = defineProps<{
    image: ImageLimits;
    modelValue: ExtractedPalette | null;
    copy: (value: string, success?: string) => Promise<boolean>;
}>();

const emit = defineEmits<{
    'update:modelValue': [palette: ExtractedPalette | null];
    'use-base': [rgba: Rgba];
}>();

const { t, locale } = useI18n();

type Status = 'idle' | 'reading' | 'decoding' | 'analyzing';

const ACCEPT = acceptAttribute({
    extensions: [],
    types: [...ACCEPTED_IMAGE_TYPES],
});

const count = ref<ExtractionCount>(11);
const url = ref('');
const status = ref<Status>('idle');
const error = ref<ImageErrorCode | null>(null);
const previewUrl = ref<string | null>(null);
const dropzone = ref<{ pick: () => void } | null>(null);

let client: ExtractionClient | null = null;
let runId = 0;
let abort: AbortController | null = null;

const busy = computed(() => status.value !== 'idle');
const fileHint = computed(() =>
    t('tools.palette-generator.extraction.file_hint', {
        size: formatBytes(props.image.maxBytes),
    }),
);
const statusLabel = computed(() => {
    switch (status.value) {
        case 'reading':
            return t('tools.palette-generator.extraction.status.reading');
        case 'decoding':
            return t('tools.palette-generator.extraction.status.decoding');
        case 'analyzing':
            return t('tools.palette-generator.extraction.status.analyzing');
        default:
            return '';
    }
});

/** Teto em MB pelo locale atual; o numero vem do servidor, nunca daqui. */
function formatBytes(bytes: number): string {
    return new Intl.NumberFormat(locale.value, {
        style: 'unit',
        unit: 'megabyte',
        unitDisplay: 'short',
        maximumFractionDigits: 1,
    }).format(bytes / 1024 / 1024);
}

function paletteClient(): ExtractionClient {
    client ??= createExtractionClient(spawnExtractionWorker);

    return client;
}

function setPreview(blob: Blob): void {
    revokePreview();
    previewUrl.value = URL.createObjectURL(blob);
}

function revokePreview(): void {
    if (previewUrl.value !== null) {
        URL.revokeObjectURL(previewUrl.value);
        previewUrl.value = null;
    }
}

function fail(code: ImageErrorCode): void {
    error.value = code;
    status.value = 'idle';
}

function hostOf(raw: string): string {
    try {
        return new URL(raw).host;
    } catch {
        return '';
    }
}

type AnalyzeInput =
    | { kind: 'file'; blob: Blob; source: ExtractedPalette['source'] }
    | { kind: 'url'; raw: string; source: ExtractedPalette['source'] };

async function analyze(input: AnalyzeInput): Promise<void> {
    runId += 1;
    const current = runId;

    abort?.abort();

    const controller = new AbortController();
    abort = controller;
    status.value = input.kind === 'url' ? 'reading' : 'decoding';
    error.value = null;
    revokePreview();

    try {
        let blob: Blob;

        if (input.kind === 'url') {
            const fetched = await fetchImage(
                input.raw,
                props.image,
                controller.signal,
            );

            if (current !== runId) {
                return;
            }

            if (!fetched.ok) {
                fail(fetched.error);

                return;
            }

            blob = fetched.blob;
        } else {
            blob = input.blob;
        }

        status.value = 'decoding';

        const decoded = await decodeImage(blob, props.image);

        if (current !== runId) {
            return;
        }

        if (!decoded.ok) {
            fail(decoded.error);

            return;
        }

        setPreview(blob);
        status.value = 'analyzing';

        const outcome = await paletteClient().run({
            data: decoded.image.data,
            width: decoded.image.sampledWidth,
            height: decoded.image.sampledHeight,
            count: count.value,
        });

        if (current !== runId) {
            return;
        }

        if (outcome === null) {
            // Fila perdida (worker morto) ou cancelada: nao ha o que pintar.
            fail('unavailable');

            return;
        }

        if (!outcome.ok) {
            fail(
                outcome.error === 'empty_image'
                    ? 'empty_image'
                    : 'unavailable',
            );

            return;
        }

        emit('update:modelValue', {
            colors: outcome.result.colors,
            requested: outcome.result.requested,
            algorithmVersion: outcome.result.algorithmVersion,
            sufficient: outcome.result.sufficient,
            visiblePixels: outcome.result.visiblePixels,
            source: input.source,
        });
        status.value = 'idle';
    } catch {
        if (current === runId) {
            fail('unavailable');
        }
    }
}

async function analyzeFile(file: File): Promise<void> {
    await analyze({
        kind: 'file',
        blob: file,
        source: { kind: 'file', label: file.name },
    });
}

async function importUrl(): Promise<void> {
    const raw = url.value.trim();

    if (raw === '' || busy.value) {
        return;
    }

    await analyze({
        kind: 'url',
        raw,
        source: { kind: 'url', host: hostOf(raw) },
    });
}

function cancel(): void {
    runId += 1;
    abort?.abort();
    abort = null;
    client?.cancel();
    status.value = 'idle';
    error.value = null;
}

onBeforeUnmount(() => {
    runId += 1;
    abort?.abort();
    revokePreview();
    client?.dispose();
});
</script>

<template>
  <section
    class="flex flex-col gap-4 rounded-xl border border-border bg-card p-4"
    data-test="extraction-panel"
  >
    <div>
      <h2 class="text-sm font-semibold">
        {{ t('tools.palette-generator.extraction.title') }}
      </h2>
      <p class="mt-1 text-xs text-muted-foreground">
        {{ t('tools.palette-generator.extraction.description') }}
      </p>
    </div>

    <div class="grid gap-4 md:grid-cols-2">
      <div class="flex flex-col gap-2">
        <span class="text-sm font-medium">
          {{ t('tools.palette-generator.extraction.file_label') }}
        </span>
        <FileDropzone
          ref="dropzone"
          :accept="ACCEPT"
          testid="extraction"
          @file="analyzeFile"
        >
          <div
            class="flex flex-col items-center justify-center gap-1 rounded-lg border border-dashed border-border p-4 text-center"
          >
            <Button
              type="button"
              variant="outline"
              size="sm"
              data-test="extraction-pick"
              @click.stop="dropzone?.pick()"
            >
              {{ t('tools.palette-generator.extraction.file_label') }}
            </Button>
            <p class="text-xs text-muted-foreground">
              {{ fileHint }}
            </p>
          </div>
        </FileDropzone>
      </div>

      <div class="flex flex-col gap-2">
        <label
          class="text-sm font-medium"
          for="palette-extraction-url"
        >
          {{ t('tools.palette-generator.extraction.url_label') }}
        </label>
        <div class="flex gap-2">
          <Input
            id="palette-extraction-url"
            v-model="url"
            type="url"
            inputmode="url"
            :placeholder="t('tools.palette-generator.extraction.url_placeholder')"
            data-test="extraction-url"
          />
          <Button
            type="button"
            :disabled="url.trim() === '' || busy"
            data-test="extraction-import"
            @click="importUrl"
          >
            {{
              busy
                ? t('tools.palette-generator.extraction.importing')
                : t('tools.palette-generator.extraction.import_url')
            }}
          </Button>
        </div>
        <p class="text-xs text-muted-foreground">
          {{ t('tools.palette-generator.extraction.url_hint') }}
        </p>
      </div>
    </div>

    <div class="flex flex-col gap-2">
      <span class="text-sm font-medium">
        {{ t('tools.palette-generator.extraction.count_label') }}
      </span>
      <div class="flex flex-wrap gap-1">
        <Button
          v-for="value in EXTRACTION_COUNTS"
          :key="value"
          type="button"
          size="sm"
          :variant="count === value ? 'default' : 'outline'"
          :aria-pressed="count === value"
          :data-test="`extraction-count-${value}`"
          @click="count = value"
        >
          {{
            t('tools.palette-generator.extraction.count_option', {
              count: value,
            })
          }}
        </Button>
      </div>
    </div>

    <div
      v-if="busy"
      class="flex flex-wrap items-center gap-2 rounded-lg border border-border px-3 py-2 text-xs"
      role="status"
      aria-live="polite"
      data-test="extraction-status"
    >
      <span>{{ statusLabel }}</span>
      <Button
        type="button"
        variant="outline"
        size="sm"
        data-test="extraction-cancel"
        @click="cancel"
      >
        {{ t('tools.palette-generator.extraction.cancel') }}
      </Button>
    </div>

    <p
      v-if="error !== null"
      class="rounded-lg border border-destructive/40 px-3 py-2 text-xs text-destructive"
      role="alert"
      data-test="extraction-error"
    >
      {{ t(`tools.palette-generator.extraction.errors.${error}`) }}
      <Button
        v-if="error === 'cors'"
        type="button"
        variant="outline"
        size="sm"
        class="mt-2"
        data-test="extraction-use-file"
        @click="dropzone?.pick()"
      >
        {{ t('tools.palette-generator.extraction.use_file') }}
      </Button>
    </p>

    <img
      v-if="previewUrl !== null"
      :src="previewUrl"
      :alt="t('tools.palette-generator.extraction.preview_alt')"
      class="max-h-48 w-auto max-w-full self-start rounded-lg border border-border object-contain"
      data-test="extraction-preview"
    >

    <section
      v-if="modelValue !== null"
      class="flex flex-col gap-3"
      data-test="extraction-results"
    >
      <div class="flex flex-wrap items-baseline justify-between gap-2">
        <h3 class="text-sm font-semibold">
          {{
            t('tools.palette-generator.extraction.results_title', {
              count: modelValue.colors.length,
            })
          }}
        </h3>
        <span
          class="text-[10px] text-muted-foreground"
          data-test="extraction-version"
        >
          {{
            t('tools.palette-generator.extraction.algorithm_version', {
              version: modelValue.algorithmVersion,
            })
          }}
        </span>
      </div>

      <p
        class="text-xs text-muted-foreground"
        data-test="extraction-source"
      >
        {{
          modelValue.source.kind === 'file'
            ? t('tools.palette-generator.extraction.source_file', {
              name: modelValue.source.label,
            })
            : t('tools.palette-generator.extraction.source_url', {
              host: modelValue.source.host,
            })
        }}
      </p>

      <p
        v-if="!modelValue.sufficient"
        class="rounded-lg border border-dashed border-border px-3 py-2 text-xs text-muted-foreground"
        data-test="extraction-insufficient"
      >
        {{
          t('tools.palette-generator.extraction.insufficient', {
            requested: modelValue.requested,
            found: modelValue.colors.length,
          })
        }}
      </p>

      <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4">
        <ExtractedSwatch
          v-for="(color, index) in modelValue.colors"
          :key="color.hex"
          :color="color"
          :index="index"
          :copy="copy"
          @use="emit('use-base', color.rgba)"
        />
      </div>
    </section>
  </section>
</template>
