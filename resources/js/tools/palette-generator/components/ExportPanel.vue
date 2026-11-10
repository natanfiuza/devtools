<script setup lang="ts">
import { Download } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import CopyStatusIcon from '@/components/colors/CopyStatusIcon.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useCopyConfirmation } from '@/composables/useCopyFeedback';
import type { PaletteScale } from '@/lib/color';
import { downloadText } from '@/lib/download';
import {
    PALETTE_EXPORT_FORMATS,
    buildAllExports,
    sanitizePaletteName,
    type PaletteExportFile,
    type PaletteExportFormat,
} from '../lib/exporters';

/**
 * Exportacao: nome, cinco formatos e as duas saidas (copiar e baixar).
 *
 * O nome e livre, mas tudo que sai daqui usa o slug sanitizado; quando o texto
 * difere do slug, a pagina avisa qual nome sera usado. O conteudo de cada aba
 * e gerado na hora, sem estado escondido.
 */

const props = defineProps<{
    scale: PaletteScale;
    copy: (value: string, success?: string) => Promise<boolean>;
}>();

const { t } = useI18n();
const { copied, confirm } = useCopyConfirmation();

const name = ref('primary');
const active = ref<PaletteExportFormat>('css');

const slug = computed(() => sanitizePaletteName(name.value));
const renamed = computed(() => slug.value !== name.value);
const files = computed(() => buildAllExports(props.scale, name.value));
const activeFile = computed<PaletteExportFile | undefined>(() =>
    files.value.find((file) => file.format === active.value),
);

function tabKey(format: PaletteExportFormat): string {
    return format.replace('-', '_');
}

function fileFor(format: PaletteExportFormat): PaletteExportFile | undefined {
    return files.value.find((file) => file.format === format);
}

function onTabChange(value: string | number): void {
    const match = PALETTE_EXPORT_FORMATS.find((format) => format === value);

    if (match !== undefined) {
        active.value = match;
    }
}

async function onCopy(): Promise<void> {
    const file = activeFile.value;

    if (file === undefined) {
        return;
    }

    const ok = await props.copy(
        file.contents,
        t('tools.palette-generator.export.copied', { name: file.filename }),
    );

    if (ok) {
        confirm();
    }
}

function onDownload(): void {
    const file = activeFile.value;

    if (file === undefined) {
        return;
    }

    downloadText(file.filename, file.contents, file.mime);
}
</script>

<template>
  <section
    class="flex flex-col gap-4 rounded-xl border border-border bg-card p-4"
    data-test="export-panel"
  >
    <div>
      <h2 class="text-sm font-semibold">
        {{ t('tools.palette-generator.export.title') }}
      </h2>
      <p class="mt-1 text-xs text-muted-foreground">
        {{ t('tools.palette-generator.export.description') }}
      </p>
    </div>

    <div>
      <label
        class="text-sm font-medium"
        for="palette-export-name"
      >
        {{ t('tools.palette-generator.export.name_label') }}
      </label>
      <Input
        id="palette-export-name"
        v-model="name"
        class="mt-1"
        data-test="export-name"
      />
      <p
        class="mt-1 text-xs text-muted-foreground"
        data-test="export-slug"
      >
        {{
          t('tools.palette-generator.export.name_hint', {
            slug,
          })
        }}
      </p>
      <p
        v-if="renamed"
        class="mt-1 text-xs text-muted-foreground"
        data-test="export-name-warning"
      >
        {{
          t('tools.palette-generator.export.name_invalid', {
            slug,
          })
        }}
      </p>
    </div>

    <Tabs
      :model-value="active"
      @update:model-value="onTabChange"
    >
      <TabsList class="flex w-full flex-wrap">
        <TabsTrigger
          v-for="format in PALETTE_EXPORT_FORMATS"
          :key="format"
          :value="format"
          :data-test="`export-tab-${format}`"
        >
          {{ t(`tools.palette-generator.export.tabs.${tabKey(format)}`) }}
        </TabsTrigger>
      </TabsList>

      <TabsContent
        v-for="format in PALETTE_EXPORT_FORMATS"
        :key="format"
        :value="format"
        class="mt-3"
      >
        <pre
          tabindex="0"
          class="max-h-80 overflow-auto rounded-lg bg-muted p-3 font-mono text-xs leading-relaxed"
          data-test="export-code"
        >{{ fileFor(format)?.contents }}</pre>
      </TabsContent>
    </Tabs>

    <div class="flex flex-wrap gap-2">
      <Button
        type="button"
        data-test="export-copy"
        @click="onCopy"
      >
        <CopyStatusIcon :copied="copied" />
        {{ t('tools.palette-generator.export.copy') }}
      </Button>
      <Button
        type="button"
        variant="outline"
        data-test="export-download"
        @click="onDownload"
      >
        <Download aria-hidden="true" />
        {{ t('tools.palette-generator.export.download') }}
      </Button>
    </div>
  </section>
</template>
