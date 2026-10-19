<script setup lang="ts">
import { markdown } from '@codemirror/lang-markdown';
import {
    Copy,
    Download,
    Eraser,
    FolderOpen,
    Loader2,
    Printer,
    Sparkles,
} from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { computed, ref, shallowRef, useTemplateRef, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import CodeEditor from '@/components/tool/CodeEditor.vue';
import FileDropzone from '@/components/tool/FileDropzone.vue';
import ToolActionBar from '@/components/tool/ToolActionBar.vue';
import ToolButton from '@/components/tool/ToolButton.vue';
import ToolShell from '@/components/tool/ToolShell.vue';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useCopyFeedback } from '@/composables/useCopyFeedback';
import { useFileError } from '@/composables/useFileError';
import { formatBytes } from '@/lib/bytes';
import { acceptAttribute, readTextFile, type FileRules } from '@/lib/file';
import { byteLength } from '@/lib/json/parse';
import { countChars, countWords, parseMarkdown } from './lib/markdown';
import { exportToPdf, printDocument } from './lib/pdf';
import {
    getFontSizeClasses,
    getPageSheetStyle,
    getThemeClasses,
} from './lib/themes';
import {
    COLOR_THEMES,
    DEFAULT_CONFIG,
    FONT_FAMILIES,
    FONT_SIZES,
    MARGIN_SIZES,
    PAGE_ORIENTATIONS,
    PAPER_FORMATS,
    type DocumentConfig,
} from './lib/types';
import sample from './sample.md?raw';

/**
 * Conversor de Markdown para PDF.
 *
 * Processamento 100% no cliente (ADR-002):
 * - Edicao e upload a esquerda com destaque de sintaxe CodeMirror 6.
 * - Renderizacao em tempo real a direita simulando a folha de papel.
 * - Personalizacao de formatos, fontes, tamanhos, margens e padroes de cores.
 * - Exportacao hibrida: download direto de arquivo .pdf e impressao vetorial nativa.
 */

const props = defineProps<{
    /** Limite de entrada em bytes, vindo de config/tools.php */
    maxInputBytes: number;
}>();

const { t, locale } = useI18n();

const FILE_RULES: Pick<FileRules, 'extensions' | 'types'> = {
    extensions: ['.md', '.markdown', '.txt'],
    types: ['text/markdown', 'text/plain'],
};

const text = ref(sample);
const config = ref<DocumentConfig>({ ...DEFAULT_CONFIG });
const isExporting = ref(false);

const dropzone = useTemplateRef<InstanceType<typeof FileDropzone>>('dropzone');
const documentSheet = ref<HTMLElement | null>(null);

const copyFeedback = useCopyFeedback();
const fileError = useFileError(
    () => props.maxInputBytes,
    () => t('tools.markdown-to-pdf.file_types'),
);

const inputBytes = computed(() => byteLength(text.value));
const tooLarge = computed(() => inputBytes.value > props.maxInputBytes);
const isEmpty = computed(() => text.value.trim() === '');

const wordsCount = computed(() => countWords(text.value));
const charsCount = computed(() => countChars(text.value));

/** HTML renderizado do Markdown, com debounce para manter a digitacao leve */
const renderedHtml = shallowRef(parseMarkdown(text.value));

const updateHtml = useDebounceFn((content: string) => {
    if (tooLarge.value) {
        renderedHtml.value = '';

        return;
    }

    renderedHtml.value = parseMarkdown(content);
}, 150);

watch(text, (val) => updateHtml(val));

function loadExample(): void {
    fileError.clear();
    text.value = sample;
}

function clear(): void {
    fileError.clear();
    text.value = '';
}

function openFile(): void {
    dropzone.value?.pick();
}

async function onFile(file: File): Promise<void> {
    fileError.clear();

    const result = await readTextFile(file, {
        ...FILE_RULES,
        maxBytes: props.maxInputBytes,
    });

    if (!result.ok) {
        fileError.fail(result.kind, result.size);

        return;
    }

    text.value = result.text;
}

function copyMarkdown(): void {
    void copyFeedback.copy(text.value);
}

async function downloadPdf(): Promise<void> {
    if (!documentSheet.value || isEmpty.value || isExporting.value) {
        return;
    }

    isExporting.value = true;

    try {
        const filename = t('tools.markdown-to-pdf.download_name');
        await exportToPdf(documentSheet.value, filename, config.value);
    } finally {
        isExporting.value = false;
    }
}

function handlePrint(): void {
    if (!documentSheet.value || isEmpty.value) {
        return;
    }

    const title = t('tools.markdown-to-pdf.download_name');
    printDocument(documentSheet.value, title, config.value);
}
</script>

<template>
  <!-- eslint-disable vue/no-v-html -->
  <ToolShell
    split-key="markdown-to-pdf"
    data-test="markdown-to-pdf-shell"
  >
    <template #actions>
      <ToolActionBar>
        <ToolButton
          :label="t('tools.ui.actions.example')"
          data-test="action-example"
          @click="loadExample"
        >
          <template #icon>
            <Sparkles class="size-3.5" />
          </template>
        </ToolButton>

        <ToolButton
          :label="t('tools.ui.actions.open')"
          data-test="action-open"
          @click="openFile"
        >
          <template #icon>
            <FolderOpen class="size-3.5" />
          </template>
        </ToolButton>

        <ToolButton
          :label="t('tools.ui.actions.clear')"
          :disabled="isEmpty"
          data-test="action-clear"
          @click="clear"
        >
          <template #icon>
            <Eraser class="size-3.5" />
          </template>
        </ToolButton>

        <template #end>
          <ToolButton
            :label="t('tools.ui.actions.copy')"
            :disabled="isEmpty"
            data-test="action-copy"
            @click="copyMarkdown"
          >
            <template #icon>
              <Copy class="size-3.5" />
            </template>
          </ToolButton>

          <ToolButton
            :label="t('tools.markdown-to-pdf.actions.print')"
            :disabled="isEmpty"
            data-test="action-print"
            @click="handlePrint"
          >
            <template #icon>
              <Printer class="size-3.5" />
            </template>
          </ToolButton>

          <ToolButton
            :label="
              isExporting
                ? t('tools.markdown-to-pdf.actions.generating')
                : t(
                  'tools.markdown-to-pdf.actions.download_pdf',
                )
            "
            :disabled="isEmpty || isExporting"
            variant="default"
            data-test="action-download-pdf"
            @click="downloadPdf"
          >
            <template #icon>
              <Loader2
                v-if="isExporting"
                class="size-3.5 animate-spin"
              />
              <Download
                v-else
                class="size-3.5"
              />
            </template>
          </ToolButton>
        </template>
      </ToolActionBar>
    </template>

    <!-- Painel Esquerdo: Editor Markdown e Dropzone -->
    <template #input>
      <FileDropzone
        ref="dropzone"
        :accept="acceptAttribute(FILE_RULES)"
        testid="markdown-to-pdf"
        @file="onFile"
      >
        <CodeEditor
          v-model="text"
          testid="markdown-to-pdf-editor"
          class="absolute inset-0"
          :placeholder="t('tools.ui.states.empty')"
          :extensions="[markdown()]"
        />
      </FileDropzone>
    </template>

    <!-- Topo do Painel Direito: Opcoes de formato, fontes, padroes de cores e botao de download -->
    <template #output-header>
      <div
        class="flex flex-wrap items-center gap-1.5"
        data-test="markdown-to-pdf-controls"
      >
        <!-- Formato da folha -->
        <Select v-model="config.format">
          <SelectTrigger
            class="h-7 w-[78px] px-2 text-xs"
            :aria-label="t('tools.markdown-to-pdf.controls.format')"
            data-test="select-format"
          >
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="fmt in PAPER_FORMATS"
              :key="fmt"
              :value="fmt"
              class="text-xs"
            >
              {{ t(`tools.markdown-to-pdf.formats.${fmt}`) }}
            </SelectItem>
          </SelectContent>
        </Select>

        <!-- Orientacao -->
        <Select v-model="config.orientation">
          <SelectTrigger
            class="h-7 w-[96px] px-2 text-xs"
            :aria-label="
              t('tools.markdown-to-pdf.controls.orientation')
            "
            data-test="select-orientation"
          >
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="ori in PAGE_ORIENTATIONS"
              :key="ori"
              :value="ori"
              class="text-xs"
            >
              {{ t(`tools.markdown-to-pdf.orientations.${ori}`) }}
            </SelectItem>
          </SelectContent>
        </Select>

        <!-- Margem -->
        <Select v-model="config.margin">
          <SelectTrigger
            class="h-7 w-[105px] px-2 text-xs"
            :aria-label="t('tools.markdown-to-pdf.controls.margin')"
            data-test="select-margin"
          >
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="m in MARGIN_SIZES"
              :key="m"
              :value="m"
              class="text-xs"
            >
              {{ t(`tools.markdown-to-pdf.margins.${m}`) }}
            </SelectItem>
          </SelectContent>
        </Select>

        <!-- Fonte -->
        <Select v-model="config.font">
          <SelectTrigger
            class="h-7 w-[105px] px-2 text-xs"
            :aria-label="t('tools.markdown-to-pdf.controls.font')"
            data-test="select-font"
          >
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="f in FONT_FAMILIES"
              :key="f"
              :value="f"
              class="text-xs"
            >
              {{ t(`tools.markdown-to-pdf.fonts.${f}`) }}
            </SelectItem>
          </SelectContent>
        </Select>

        <!-- Tamanho da fonte -->
        <Select v-model="config.fontSize">
          <SelectTrigger
            class="h-7 w-[85px] px-2 text-xs"
            :aria-label="
              t('tools.markdown-to-pdf.controls.font_size')
            "
            data-test="select-font-size"
          >
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="sz in FONT_SIZES"
              :key="sz"
              :value="sz"
              class="text-xs"
            >
              {{ t(`tools.markdown-to-pdf.font_sizes.${sz}`) }}
            </SelectItem>
          </SelectContent>
        </Select>

        <!-- Padrao de cores / Tema -->
        <Select v-model="config.theme">
          <SelectTrigger
            class="h-7 w-[100px] px-2 text-xs"
            :aria-label="t('tools.markdown-to-pdf.controls.theme')"
            data-test="select-theme"
          >
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="th in COLOR_THEMES"
              :key="th"
              :value="th"
              class="text-xs"
            >
              {{ t(`tools.markdown-to-pdf.themes.${th}`) }}
            </SelectItem>
          </SelectContent>
        </Select>

        <!-- Botao de download do PDF no topo do painel direito -->
        <Button
          type="button"
          size="sm"
          class="h-7 gap-1 px-2.5 text-xs"
          :disabled="isEmpty || isExporting"
          data-test="top-download-pdf-button"
          @click="downloadPdf"
        >
          <Loader2
            v-if="isExporting"
            class="size-3 animate-spin"
          />
          <Download
            v-else
            class="size-3"
          />
          <span>{{
            isExporting
              ? t('tools.markdown-to-pdf.actions.generating')
              : t('tools.markdown-to-pdf.actions.download_pdf')
          }}</span>
        </Button>
      </div>
    </template>

    <!-- Painel Direito: Preview renderizado em tempo real simulando folha de papel -->
    <template #output>
      <div
        class="relative flex min-h-[380px] flex-1 flex-col overflow-auto bg-neutral-100/70 p-3 sm:p-5 dark:bg-neutral-900/50"
      >
        <div
          v-if="isEmpty"
          class="flex flex-1 items-center justify-center p-8 text-center text-sm text-subtle"
          data-test="markdown-to-pdf-empty"
        >
          {{ t('tools.ui.states.empty') }}
        </div>

        <div
          v-else
          class="flex flex-1 justify-center"
        >
          <!-- eslint-disable-next-line vue/no-v-html -->
          <article
            ref="documentSheet"
            class="markdown-pdf-document rounded border border-neutral-200/80 shadow-md transition-all duration-150 dark:border-neutral-700/80"
            :class="[
              getThemeClasses(config.theme),
              getFontSizeClasses(config.fontSize),
            ]"
            :style="getPageSheetStyle(config)"
            data-test="markdown-pdf-preview"
            v-html="renderedHtml"
          />
        </div>
      </div>
    </template>

    <!-- Status Bar -->
    <template #status>
      <div
        class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-subtle"
        data-test="markdown-to-pdf-status"
      >
        <span>{{
          t('tools.markdown-to-pdf.stats.words', {
            count: wordsCount,
          })
        }}</span>
        <span>{{
          t('tools.markdown-to-pdf.stats.chars', {
            count: charsCount,
          })
        }}</span>
        <span :class="tooLarge ? 'font-medium text-destructive' : ''">
          {{ formatBytes(inputBytes, locale) }}
          {{
            t('tools.ui.limits.of', {
              limit: formatBytes(props.maxInputBytes, locale),
            })
          }}
        </span>
        <span
          v-if="tooLarge"
          class="font-medium text-destructive"
        >
          {{
            t('tools.ui.states.too_large', {
              size: formatBytes(inputBytes, locale),
              limit: formatBytes(props.maxInputBytes, locale),
            })
          }}
        </span>
        <span
          v-if="fileError.message.value"
          class="font-medium text-destructive"
        >
          {{ fileError.message.value }}
        </span>
      </div>
    </template>
  </ToolShell>
</template>

<style>
/* Estilos fundamentais do documento renderizado */
.markdown-pdf-document {
    box-sizing: border-box;
}

.markdown-pdf-document p {
    margin-bottom: 0.85rem;
}

.markdown-pdf-document ul {
    list-style-type: disc;
    padding-left: 1.5rem;
    margin-bottom: 0.85rem;
}

.markdown-pdf-document ol {
    list-style-type: decimal;
    padding-left: 1.5rem;
    margin-bottom: 0.85rem;
}

.markdown-pdf-document li {
    margin-bottom: 0.25rem;
}

.markdown-pdf-document input[type='checkbox'] {
    margin-right: 0.4rem;
    vertical-align: middle;
}

.markdown-pdf-document img {
    max-width: 100%;
    height: auto;
    border-radius: 4px;
    margin: 1rem 0;
}

/* Tema Moderno */
.markdown-pdf-document.theme-modern h1 {
    color: #0f172a;
    font-size: 1.9rem;
    font-weight: 700;
    margin-top: 1.4rem;
    margin-bottom: 0.75rem;
    border-bottom: 1px solid #e2e8f0;
    padding-bottom: 0.4rem;
}
.markdown-pdf-document.theme-modern h2 {
    color: #1e293b;
    font-size: 1.45rem;
    font-weight: 600;
    margin-top: 1.25rem;
    margin-bottom: 0.6rem;
    border-bottom: 1px solid #f1f5f9;
    padding-bottom: 0.3rem;
}
.markdown-pdf-document.theme-modern h3 {
    color: #334155;
    font-size: 1.2rem;
    font-weight: 600;
    margin-top: 1rem;
    margin-bottom: 0.5rem;
}
.markdown-pdf-document.theme-modern h4,
.markdown-pdf-document.theme-modern h5,
.markdown-pdf-document.theme-modern h6 {
    color: #475569;
    font-weight: 600;
    margin-top: 0.8rem;
    margin-bottom: 0.4rem;
}
.markdown-pdf-document.theme-modern a {
    color: #2563eb;
    text-decoration: underline;
    text-underline-offset: 2px;
}
.markdown-pdf-document.theme-modern blockquote {
    border-left: 4px solid #3b82f6;
    padding-left: 1rem;
    color: #475569;
    font-style: italic;
    margin: 1rem 0;
    background: #f8fafc;
    padding-top: 0.5rem;
    padding-bottom: 0.5rem;
    border-radius: 0 4px 4px 0;
}
.markdown-pdf-document.theme-modern pre {
    background: #0f172a;
    color: #f8fafc;
    border-radius: 6px;
    padding: 0.85rem;
    overflow-x: auto;
    font-family: monospace;
    margin: 1rem 0;
}
.markdown-pdf-document.theme-modern code {
    background: #f1f5f9;
    color: #0f172a;
    padding: 0.15rem 0.35rem;
    border-radius: 4px;
    font-family: monospace;
    font-size: 0.9em;
}
.markdown-pdf-document.theme-modern pre code {
    background: transparent;
    color: inherit;
    padding: 0;
}
.markdown-pdf-document.theme-modern table {
    width: 100%;
    border-collapse: collapse;
    margin: 1.2rem 0;
}
.markdown-pdf-document.theme-modern th,
.markdown-pdf-document.theme-modern td {
    border: 1px solid #e2e8f0;
    padding: 0.6rem 0.8rem;
    text-align: left;
}
.markdown-pdf-document.theme-modern th {
    background: #f8fafc;
    color: #0f172a;
    font-weight: 600;
}
.markdown-pdf-document.theme-modern hr {
    border: 0;
    border-top: 1px solid #e2e8f0;
    margin: 1.5rem 0;
}

/* Tema Editorial */
.markdown-pdf-document.theme-editorial h1 {
    color: #1e3a8a;
    font-size: 2rem;
    font-weight: 700;
    margin-top: 1.5rem;
    margin-bottom: 0.8rem;
    border-bottom: 2px solid #e2e8f0;
    padding-bottom: 0.4rem;
}
.markdown-pdf-document.theme-editorial h2 {
    color: #1e3a8a;
    font-size: 1.5rem;
    font-weight: 600;
    margin-top: 1.25rem;
    margin-bottom: 0.6rem;
    border-bottom: 1px solid #cbd5e1;
    padding-bottom: 0.3rem;
}
.markdown-pdf-document.theme-editorial h3 {
    color: #1e40af;
    font-size: 1.25rem;
    font-weight: 600;
    margin-top: 1rem;
    margin-bottom: 0.5rem;
}
.markdown-pdf-document.theme-editorial h4,
.markdown-pdf-document.theme-editorial h5,
.markdown-pdf-document.theme-editorial h6 {
    color: #1e40af;
    font-weight: 600;
    margin-top: 0.8rem;
    margin-bottom: 0.4rem;
}
.markdown-pdf-document.theme-editorial a {
    color: #991b1b;
    text-decoration: underline;
}
.markdown-pdf-document.theme-editorial blockquote {
    border-left: 4px solid #991b1b;
    padding-left: 1rem;
    color: #57534e;
    font-style: italic;
    margin: 1rem 0;
    background: #fafaf9;
    padding-top: 0.5rem;
    padding-bottom: 0.5rem;
}
.markdown-pdf-document.theme-editorial pre {
    background: #f5f5f4;
    color: #292524;
    border: 1px solid #e7e5e4;
    border-radius: 6px;
    padding: 0.8rem;
    overflow-x: auto;
    font-family: monospace;
}
.markdown-pdf-document.theme-editorial code {
    background: #f5f5f4;
    color: #991b1b;
    padding: 0.15rem 0.35rem;
    border-radius: 4px;
    font-family: monospace;
    font-size: 0.9em;
}
.markdown-pdf-document.theme-editorial pre code {
    background: transparent;
    color: inherit;
    padding: 0;
}
.markdown-pdf-document.theme-editorial table {
    width: 100%;
    border-collapse: collapse;
    margin: 1rem 0;
}
.markdown-pdf-document.theme-editorial th,
.markdown-pdf-document.theme-editorial td {
    border: 1px solid #d6d3d1;
    padding: 0.6rem 0.8rem;
    text-align: left;
}
.markdown-pdf-document.theme-editorial th {
    background: #f5f5f4;
    color: #1e3a8a;
    font-weight: 600;
}
.markdown-pdf-document.theme-editorial hr {
    border: 0;
    border-top: 1px solid #d6d3d1;
    margin: 1.5rem 0;
}

/* Tema Minimalista */
.markdown-pdf-document.theme-minimal h1 {
    color: #000;
    font-size: 1.85rem;
    font-weight: 700;
    margin-top: 1.4rem;
    margin-bottom: 0.7rem;
    border-bottom: 1.5px solid #000;
    padding-bottom: 0.3rem;
}
.markdown-pdf-document.theme-minimal h2 {
    color: #000;
    font-size: 1.4rem;
    font-weight: 700;
    margin-top: 1.2rem;
    margin-bottom: 0.5rem;
    border-bottom: 1px solid #000;
    padding-bottom: 0.2rem;
}
.markdown-pdf-document.theme-minimal h3 {
    color: #000;
    font-size: 1.15rem;
    font-weight: 700;
    margin-top: 0.9rem;
    margin-bottom: 0.4rem;
}
.markdown-pdf-document.theme-minimal h4,
.markdown-pdf-document.theme-minimal h5,
.markdown-pdf-document.theme-minimal h6 {
    color: #000;
    font-weight: 700;
    margin-top: 0.7rem;
    margin-bottom: 0.3rem;
}
.markdown-pdf-document.theme-minimal a {
    color: #000;
    text-decoration: underline;
    font-weight: 500;
}
.markdown-pdf-document.theme-minimal blockquote {
    border-left: 3px solid #000;
    padding-left: 0.8rem;
    color: #000;
    font-style: italic;
    margin: 0.8rem 0;
}
.markdown-pdf-document.theme-minimal pre {
    background: #fff;
    color: #000;
    border: 1px solid #000;
    border-radius: 2px;
    padding: 0.7rem;
    overflow-x: auto;
    font-family: monospace;
}
.markdown-pdf-document.theme-minimal code {
    background: #fff;
    color: #000;
    border: 1px solid #000;
    padding: 0.1rem 0.25rem;
    font-family: monospace;
    font-size: 0.9em;
}
.markdown-pdf-document.theme-minimal pre code {
    border: none;
    padding: 0;
}
.markdown-pdf-document.theme-minimal table {
    width: 100%;
    border-collapse: collapse;
    margin: 1rem 0;
}
.markdown-pdf-document.theme-minimal th,
.markdown-pdf-document.theme-minimal td {
    border: 1px solid #000;
    padding: 0.5rem 0.7rem;
    text-align: left;
}
.markdown-pdf-document.theme-minimal th {
    background: #fff;
    color: #000;
    font-weight: 700;
}
.markdown-pdf-document.theme-minimal hr {
    border: 0;
    border-top: 1px solid #000;
    margin: 1.2rem 0;
}

/* Tema Academico */
.markdown-pdf-document.theme-academic h1 {
    color: #171717;
    font-size: 1.75rem;
    font-weight: 700;
    margin-top: 1.5rem;
    margin-bottom: 0.75rem;
    text-align: center;
}
.markdown-pdf-document.theme-academic h2 {
    color: #262626;
    font-size: 1.35rem;
    font-weight: 600;
    margin-top: 1.3rem;
    margin-bottom: 0.6rem;
}
.markdown-pdf-document.theme-academic h3 {
    color: #404040;
    font-size: 1.15rem;
    font-weight: 600;
    margin-top: 1rem;
    margin-bottom: 0.5rem;
}
.markdown-pdf-document.theme-academic p {
    text-align: justify;
    text-indent: 1.5em;
    margin-bottom: 0.8rem;
    line-height: 1.75;
}
.markdown-pdf-document.theme-academic a {
    color: #0f172a;
    text-decoration: underline;
}
.markdown-pdf-document.theme-academic blockquote {
    border-left: 3px solid #737373;
    padding-left: 1.5rem;
    margin-left: 1.5rem;
    color: #404040;
    font-style: italic;
    margin-top: 1rem;
    margin-bottom: 1rem;
}
.markdown-pdf-document.theme-academic pre {
    background: #fafafa;
    color: #171717;
    border: 1px solid #e5e5e5;
    border-radius: 4px;
    padding: 0.75rem;
    overflow-x: auto;
    font-family: monospace;
}
.markdown-pdf-document.theme-academic code {
    background: #f5f5f5;
    color: #262626;
    padding: 0.15rem 0.3rem;
    border-radius: 3px;
    font-family: monospace;
    font-size: 0.9em;
}
.markdown-pdf-document.theme-academic pre code {
    background: transparent;
    padding: 0;
}
.markdown-pdf-document.theme-academic table {
    width: 100%;
    border-collapse: collapse;
    margin: 1.2rem 0;
}
.markdown-pdf-document.theme-academic th,
.markdown-pdf-document.theme-academic td {
    border-top: 1px solid #171717;
    border-bottom: 1px solid #171717;
    padding: 0.5rem 0.75rem;
    text-align: left;
}
.markdown-pdf-document.theme-academic th {
    border-bottom: 2px solid #171717;
    font-weight: 600;
    background: transparent;
}
.markdown-pdf-document.theme-academic hr {
    border: 0;
    border-top: 1px solid #a3a3a3;
    margin: 1.5rem 0;
}
</style>
