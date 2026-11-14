<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import FileDropzone from '@/components/tool/FileDropzone.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatBytes } from '@/lib/bytes';
import {
    acceptAttribute,
    readTextFile,
    type FileErrorKind,
    type FileRules,
} from '@/lib/file';
import {
    importTokens,
    type ImportedPalette,
    type TokenImportDiagnostic,
    type TokenImportResult,
} from '@/tools/palette-generator/lib/tokenImport';
import type { TokenImportLimits } from '@/types/colors';

/**
 * Importacao validada de tokens (F5, Etapa 6).
 *
 * Leitura e validacao acontecem so aqui: escolher o arquivo nao altera a
 * paleta. O parser devolve o diagnostico completo e a previa; "Aplicar" e a
 * unica coisa que emite a paleta, e so quando nao ha erro. Nada do arquivo
 * sai do navegador.
 *
 * O limite de exibicao e do dialogo; o parser devolve tudo, e os diagnosticos
 * alem do teto aparecem como contagem.
 */

const props = defineProps<{
    open: boolean;
    /** Tetos da importacao, entregues pelo servidor (`config/colors.php`). */
    tokens: TokenImportLimits;
    /** Teto do rotulo de origem (nome do arquivo) em `palette`. */
    sourceLabelMaxLength: number;
}>();

const emit = defineEmits<{
    close: [];
    apply: [palette: ImportedPalette];
}>();

const { t, locale } = useI18n();

const FILE_RULES: Omit<FileRules, 'maxBytes'> = {
    extensions: ['.json'],
    types: ['application/json'],
};

const accept = acceptAttribute(FILE_RULES);

const dropzone = ref<{ pick: () => void } | null>(null);
const reading = ref(false);
const fileName = ref('');
const fileError = ref<FileErrorKind | null>(null);
const result = ref<TokenImportResult | null>(null);

/** Duas leituras seguidas: so a ultima escolha vale. */
let request = 0;

function reset(): void {
    request += 1;
    reading.value = false;
    fileName.value = '';
    fileError.value = null;
    result.value = null;
}

watch(
    () => props.open,
    (open) => {
        if (open) {
            reset();
        }
    },
);

async function onFile(file: File): Promise<void> {
    const id = ++request;

    reading.value = true;
    fileError.value = null;
    result.value = null;
    fileName.value = file.name.slice(0, props.sourceLabelMaxLength);

    const read = await readTextFile(file, {
        maxBytes: props.tokens.maxBytes,
        ...FILE_RULES,
    });

    if (id !== request) {
        return;
    }

    reading.value = false;

    if (!read.ok) {
        fileError.value = read.kind;

        return;
    }

    result.value = importTokens(read.text, {
        limits: props.tokens,
        sourceLabel: fileName.value,
    });
}

const palette = computed<ImportedPalette | null>(() =>
    result.value?.ok ? result.value.palette : null,
);

/** Erros primeiro; dentro de cada gravidade, a ordem do parser. */
const orderedDiagnostics = computed<TokenImportDiagnostic[]>(() => {
    const diagnostics = result.value?.diagnostics ?? [];

    return [
        ...diagnostics.filter((entry) => entry.severity === 'error'),
        ...diagnostics.filter((entry) => entry.severity === 'warning'),
    ];
});

const shownDiagnostics = computed(() =>
    orderedDiagnostics.value.slice(0, props.tokens.maxDiagnostics),
);

const hiddenDiagnostics = computed(
    () =>
        orderedDiagnostics.value.length - shownDiagnostics.value.length,
);

const previewColors = computed<string[]>(() => {
    const current = palette.value;

    if (current === null) {
        return [];
    }

    return current.kind === 'scale'
        ? (current.scale?.tones.map((tone) => tone.hex) ?? [])
        : (current.extracted?.colors.map((color) => color.hex) ?? []);
});

function diagnosticText(diagnostic: TokenImportDiagnostic): string {
    const message = t(
        `tools.shared.token_import.codes.${diagnostic.code}`,
        { ...diagnostic.params },
    );

    return diagnostic.path === undefined
        ? message
        : `${message} ${t('tools.shared.token_import.diagnostics.path', {
              path: diagnostic.path,
          })}`;
}

function fileErrorMessage(kind: FileErrorKind): string {
    return t(`tools.shared.token_import.file_errors.${kind}`, {
        limit: formatBytes(props.tokens.maxBytes, locale.value),
    });
}

function apply(): void {
    const current = palette.value;

    if (current === null) {
        return;
    }

    emit('apply', current);
    emit('close');
}

function setOpen(value: boolean): void {
    if (!value) {
        emit('close');
    }
}
</script>

<template>
  <Dialog
    :open="props.open"
    @update:open="setOpen"
  >
    <DialogContent
      class="sm:max-w-xl"
      data-test="token-import-dialog"
    >
      <DialogHeader>
        <DialogTitle>{{ t('tools.shared.token_import.title') }}</DialogTitle>
        <DialogDescription>
          {{ t('tools.shared.token_import.description') }}
        </DialogDescription>
      </DialogHeader>

      <div class="flex flex-col gap-4">
        <FileDropzone
          ref="dropzone"
          :accept="accept"
          testid="token-import"
          @file="onFile"
        >
          <div
            class="flex flex-col items-center gap-2 rounded-lg border border-dashed border-border px-4 py-6 text-center"
          >
            <Button
              type="button"
              variant="secondary"
              size="sm"
              :disabled="reading"
              data-test="token-import-choose"
              @click="dropzone?.pick()"
            >
              {{ t('tools.shared.token_import.choose') }}
            </Button>
            <p class="text-xs text-muted-foreground">
              {{
                t('tools.shared.token_import.hint', {
                  size: formatBytes(props.tokens.maxBytes, locale),
                })
              }}
            </p>
          </div>
        </FileDropzone>

        <p
          v-if="reading"
          class="text-sm text-muted-foreground"
          role="status"
          data-test="token-import-reading"
        >
          {{ t('tools.shared.token_import.reading') }}
        </p>

        <p
          v-if="fileError !== null"
          class="rounded-lg border border-destructive/40 px-3 py-2 text-xs text-destructive"
          role="alert"
          data-test="token-import-file-error"
        >
          {{ fileErrorMessage(fileError) }}
        </p>

        <p
          v-if="fileName !== ''"
          class="text-xs text-muted-foreground"
          data-test="token-import-filename"
        >
          {{
            t('tools.shared.token_import.preview.filename', {
              name: fileName,
            })
          }}
        </p>

        <template v-if="result !== null">
          <section
            v-if="!result.ok"
            class="flex flex-col gap-2"
            aria-live="polite"
            data-test="token-import-error"
          >
            <h3 class="text-sm font-semibold">
              {{ t('tools.shared.token_import.diagnostics.title') }}
            </h3>
            <ul class="flex flex-col gap-1">
              <li
                v-for="(diagnostic, index) in shownDiagnostics"
                :key="`${diagnostic.code}-${index}`"
                class="rounded-lg border border-destructive/40 px-3 py-2 text-xs text-destructive"
                data-test="token-import-diagnostic"
                :data-severity="diagnostic.severity"
              >
                {{ diagnosticText(diagnostic) }}
              </li>
            </ul>
            <p
              v-if="hiddenDiagnostics > 0"
              class="text-xs text-muted-foreground"
              data-test="token-import-hidden"
            >
              {{
                t('tools.shared.token_import.diagnostics.hidden', {
                  count: hiddenDiagnostics,
                })
              }}
            </p>
          </section>

          <section
            v-else
            class="flex flex-col gap-3 rounded-lg border border-border p-3"
            data-test="token-import-preview"
          >
            <h3 class="text-sm font-semibold">
              {{ t('tools.shared.token_import.preview.title') }}
            </h3>

            <dl class="grid gap-2 text-xs sm:grid-cols-2">
              <div class="flex gap-2">
                <dt class="text-muted-foreground">
                  {{ t('tools.shared.token_import.preview.format') }}
                </dt>
                <dd data-test="token-import-format">
                  {{
                    t(
                      `tools.shared.token_import.formats.${palette!.format}`,
                    )
                  }}
                </dd>
              </div>
              <div class="flex gap-2">
                <dt class="text-muted-foreground">
                  {{ t('tools.shared.token_import.preview.kind') }}
                </dt>
                <dd data-test="token-import-kind">
                  {{ t(`tools.shared.token_import.kinds.${palette!.kind}`) }}
                </dd>
              </div>
              <div class="flex gap-2">
                <dt class="text-muted-foreground">
                  {{ t('tools.shared.token_import.preview.base') }}
                </dt>
                <dd>
                  <code data-test="token-import-base">{{ palette!.base }}</code>
                </dd>
              </div>
              <div class="flex gap-2">
                <dt class="text-muted-foreground">
                  {{ t('tools.shared.token_import.preview.colors_label') }}
                </dt>
                <dd data-test="token-import-count">
                  {{ previewColors.length }}
                </dd>
              </div>
            </dl>

            <p
              class="text-xs"
              :class="
                palette!.roles === null
                  ? 'text-muted-foreground'
                  : 'text-foreground'
              "
              data-test="token-import-roles"
            >
              {{
                palette!.roles === null
                  ? t('tools.shared.token_import.preview.no_roles')
                  : t('tools.shared.token_import.preview.roles')
              }}
            </p>

            <p
              v-if="palette!.name !== null"
              class="text-xs text-muted-foreground"
              data-test="token-import-name"
            >
              {{
                t('tools.shared.token_import.preview.name', {
                  name: palette!.name,
                })
              }}
            </p>

            <ul class="flex flex-wrap gap-1">
              <li
                v-for="(hex, index) in previewColors"
                :key="`${hex}-${index}`"
                class="size-6 rounded border border-border"
                :style="{ backgroundColor: hex }"
                :title="hex"
                aria-hidden="true"
              />
            </ul>

            <template v-if="shownDiagnostics.length > 0">
              <h4 class="text-xs font-semibold">
                {{ t('tools.shared.token_import.diagnostics.warnings') }}
              </h4>
              <ul class="flex flex-col gap-1">
                <li
                  v-for="(diagnostic, index) in shownDiagnostics"
                  :key="`${diagnostic.code}-${index}`"
                  class="rounded-lg border border-border px-3 py-2 text-xs text-muted-foreground"
                  data-test="token-import-diagnostic"
                  :data-severity="diagnostic.severity"
                >
                  {{ diagnosticText(diagnostic) }}
                </li>
              </ul>
              <p
                v-if="hiddenDiagnostics > 0"
                class="text-xs text-muted-foreground"
                data-test="token-import-hidden"
              >
                {{
                  t('tools.shared.token_import.diagnostics.hidden', {
                    count: hiddenDiagnostics,
                  })
                }}
              </p>
            </template>

            <p class="text-xs text-muted-foreground">
              {{ t('tools.shared.token_import.preview.apply_hint') }}
            </p>
          </section>
        </template>
      </div>

      <DialogFooter class="gap-2">
        <Button
          type="button"
          variant="ghost"
          size="sm"
          data-test="token-import-cancel"
          @click="emit('close')"
        >
          {{ t('tools.shared.token_import.preview.cancel') }}
        </Button>
        <Button
          v-if="fileName !== ''"
          type="button"
          variant="ghost"
          size="sm"
          data-test="token-import-replace"
          @click="reset()"
        >
          {{ t('tools.shared.token_import.preview.replace') }}
        </Button>
        <Button
          type="button"
          size="sm"
          :disabled="palette === null"
          data-test="token-import-apply"
          @click="apply"
        >
          {{ t('tools.shared.token_import.preview.apply') }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
