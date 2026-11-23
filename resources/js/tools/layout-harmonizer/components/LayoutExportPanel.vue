<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Download, Lock } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { downloadBlob, downloadText } from '@/lib/download';
import systems from '@/routes/dashboard/colors/systems';
import type { Locale } from '@/types/i18n';
import type {
    LayoutSystemExportLimits,
    LayoutSystemExportMessages,
} from '@/types/layoutSystem';
import { buildLayoutCss } from '../export/css';
import { buildLayoutMarkdown } from '../export/markdown';
import {
    DEFAULT_LAYOUT_CSS_PREFIX,
    DEFAULT_LAYOUT_CSS_SCOPE,
    DEFAULT_LAYOUT_EXPORT_LIMITS,
    validateLayoutExportOptions,
} from '../export/options';
import {
    isLayoutExportLanguage,
    LAYOUT_EXPORT_LANGUAGES,
    type LayoutExportInput,
    type LayoutExportLanguage,
    type LayoutExportOrigin,
} from '../export/types';
import { buildLayoutZip } from '../export/zip';
import type { LayoutSystemStateV1 } from '../lib/state';

/**
 * Card de exportacao local do Layout Harmonizer (F5, ADR-175).
 *
 * Gera Markdown, CSS e ZIP a partir do snapshot ja exibido: nenhuma cor e
 * regenerada, nada e salvo e nenhuma requisicao sai do navegador. O visitante
 * anonimo ve o card bloqueado com o CTA de login; o documento sai no idioma
 * escolhido, lendo o subtree que o servidor manda para os cinco locales.
 *
 * Sem `exportMessages` (mounts antigos em teste) o card nao renderiza, e todas
 * as props tem default inerte.
 */

const props = withDefaults(
    defineProps<{
        /** Envelope exibido; `null` desabilita os downloads. */
        state?: LayoutSystemStateV1 | null;
        /** Nome salvo ou sugerido; entra no titulo e no nome do ZIP. */
        name?: string;
        authenticated?: boolean;
        pendingRegeneration?: boolean;
        /** Envelope do painel: `compatible` ganha aviso de versoes. */
        mode?: 'live' | 'compatible' | 'readonly';
        /** Decidido pelo chamador (editor: arquivado ou versao readonly). */
        readOnly?: boolean;
        /** Origem publica do systema; privada nunca entra no documento. */
        origin?: LayoutExportOrigin | null;
        /** Paleta de origem preservada no CTA de login. */
        originPaletteId?: string | null;
        exportMessages?: LayoutSystemExportMessages;
        exportLimits?: LayoutSystemExportLimits;
    }>(),
    {
        state: null,
        name: '',
        authenticated: false,
        pendingRegeneration: false,
        mode: 'live',
        readOnly: false,
        origin: null,
        originPaletteId: null,
        exportMessages: () => ({}),
        exportLimits: () => DEFAULT_LAYOUT_EXPORT_LIMITS,
    },
);

const { t } = useI18n();
const page = usePage();

const pageLocales = computed<Locale[]>(() =>
    Array.isArray(page.props.locales) ? page.props.locales : [],
);

/** Idiomas que o servidor entregou; sem nenhum, o card fica inerte. */
const languages = computed<LayoutExportLanguage[]>(() =>
    LAYOUT_EXPORT_LANGUAGES.filter(
        (code) => props.exportMessages[code] !== undefined,
    ),
);

const hasMessages = computed(() => languages.value.length > 0);

function initialLanguage(): LayoutExportLanguage {
    const current = page.props.locale;

    if (
        isLayoutExportLanguage(current) &&
        props.exportMessages[current] !== undefined
    ) {
        return current;
    }

    return languages.value[0] ?? 'en';
}

const language = ref<LayoutExportLanguage>(initialLanguage());

const selectedLanguage = computed<LayoutExportLanguage>({
    get: () => language.value,
    set: (value) => {
        if (isLayoutExportLanguage(value)) {
            language.value = value;
        }
    },
});

function languageLabel(code: LayoutExportLanguage): string {
    return (
        pageLocales.value.find((locale) => locale.code === code)?.name ?? code
    );
}

const prefix = ref(DEFAULT_LAYOUT_CSS_PREFIX);
const scopeKind = ref<'root' | 'class'>('root');
const className = ref('');

const selectedScopeKind = computed<'root' | 'class'>({
    get: () => scopeKind.value,
    set: (value) => {
        if (value === 'root' || value === 'class') {
            scopeKind.value = value;
        }
    },
});

const scope = computed(() =>
    scopeKind.value === 'root'
        ? DEFAULT_LAYOUT_CSS_SCOPE
        : className.value.trim(),
);

const validation = computed(() =>
    validateLayoutExportOptions({
        prefix: prefix.value,
        scope: scope.value,
        limits: props.exportLimits,
    }),
);

const canExport = computed(
    () =>
        props.authenticated &&
        props.state !== null &&
        !props.pendingRegeneration &&
        validation.value.valid,
);

// Erros de validacao precisam de id/aria-describedby para o leitor de tela
// saber por que os botoes de download estao desabilitados (DESIGN.md).
const PREFIX_HINT_ID = 'layout-export-prefix-hint';
const PREFIX_ERROR_ID = 'layout-export-prefix-error';
const SCOPE_ERROR_ID = 'layout-export-scope-class-error';

const prefixDescribedBy = computed(() =>
    validation.value.prefixError === null
        ? PREFIX_HINT_ID
        : `${PREFIX_HINT_ID} ${PREFIX_ERROR_ID}`,
);

function buildInput(): LayoutExportInput | null {
    if (props.state === null) {
        return null;
    }

    const messages = props.exportMessages[language.value];

    if (messages === undefined) {
        return null;
    }

    return {
        state: props.state,
        name: props.name,
        language: language.value,
        messages,
        origin:
            props.origin?.public === true
                ? { name: props.origin.name, public: true }
                : null,
    };
}

function downloadMarkdown(): void {
    const input = buildInput();

    if (input === null || !canExport.value) {
        return;
    }

    const file = buildLayoutMarkdown(input);

    downloadText(file.filename, file.contents, file.mime);
}

function cssContents(input: LayoutExportInput): string {
    return buildLayoutCss(input, {
        prefix: prefix.value.trim(),
        scope: scope.value,
    }).contents;
}

function downloadCss(): void {
    const input = buildInput();

    if (input === null || !canExport.value) {
        return;
    }

    const file = buildLayoutCss(input, {
        prefix: prefix.value.trim(),
        scope: scope.value,
    });

    downloadText(file.filename, file.contents, file.mime);
}

function downloadZip(): void {
    const input = buildInput();

    if (input === null || !canExport.value) {
        return;
    }

    const markdown = buildLayoutMarkdown(input).contents;
    const zip = buildLayoutZip(markdown, cssContents(input), props.name);

    downloadBlob(zip.filename, zip.blob);
}

/** Login pelo intent: volta ao tool com o card liberado, sem abrir salvar. */
function login(): void {
    router.get(
        systems.create.url({
            query: {
                export: '1',
                ...(props.originPaletteId === null
                    ? {}
                    : { palette: props.originPaletteId }),
            },
        }),
    );
}
</script>

<template>
  <section
    v-if="hasMessages"
    class="flex flex-col gap-4 rounded-xl border border-border bg-card p-4"
    data-test="layout-export-panel"
  >
    <div>
      <h2 class="text-sm font-semibold">
        {{ t('tools.layout-harmonizer.export.title') }}
      </h2>
      <p class="mt-1 text-xs text-muted-foreground">
        {{ t('tools.layout-harmonizer.export.description') }}
      </p>
    </div>

    <div
      v-if="!authenticated"
      class="flex flex-col items-start gap-3 rounded-lg border border-dashed border-border bg-muted/40 p-3"
      data-test="layout-export-locked"
    >
      <p class="flex items-center gap-2 text-sm font-medium">
        <Lock class="size-4" />
        {{ t('tools.layout-harmonizer.export.title') }}
      </p>
      <p class="text-xs text-muted-foreground">
        {{ t('tools.layout-harmonizer.export.login_hint') }}
      </p>
      <Button
        type="button"
        size="sm"
        data-test="layout-export-login"
        @click="login"
      >
        {{ t('tools.layout-harmonizer.export.login_cta') }}
      </Button>
    </div>

    <template v-else>
      <p
        v-if="pendingRegeneration"
        class="rounded-lg bg-muted/50 p-3 text-xs text-muted-foreground"
        data-test="layout-export-pending"
      >
        {{ t('tools.layout-harmonizer.export.pending_regeneration') }}
      </p>
      <p
        v-else-if="mode === 'compatible'"
        class="rounded-lg bg-muted/50 p-3 text-xs text-muted-foreground"
        data-test="layout-export-compatible"
      >
        {{ t('tools.layout-harmonizer.export.compatible_notice') }}
      </p>
      <p
        v-if="readOnly"
        class="rounded-lg bg-muted/50 p-3 text-xs text-muted-foreground"
        data-test="layout-export-readonly"
      >
        {{ t('tools.layout-harmonizer.export.readonly_notice') }}
      </p>

      <div class="grid gap-4 sm:grid-cols-2">
        <div class="space-y-2">
          <Label for="layout-export-language">
            {{ t('tools.layout-harmonizer.export.language_label') }}
          </Label>
          <Select v-model="selectedLanguage">
            <SelectTrigger
              id="layout-export-language"
              class="w-full"
              data-test="layout-export-language"
            >
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem
                v-for="code in languages"
                :key="code"
                :value="code"
              >
                {{ languageLabel(code) }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>

        <div class="space-y-2">
          <Label for="layout-export-prefix">
            {{ t('tools.layout-harmonizer.export.prefix_label') }}
          </Label>
          <Input
            id="layout-export-prefix"
            v-model="prefix"
            class="w-full"
            :aria-invalid="validation.prefixError !== null"
            :aria-describedby="prefixDescribedBy"
            data-test="layout-export-prefix"
          />
          <p
            :id="PREFIX_HINT_ID"
            class="text-xs text-muted-foreground"
          >
            {{
              t('tools.layout-harmonizer.export.prefix_hint', {
                prefix: prefix.trim(),
              })
            }}
          </p>
          <p
            v-if="validation.prefixError !== null"
            :id="PREFIX_ERROR_ID"
            class="text-xs text-destructive"
            role="alert"
            data-test="layout-export-prefix-error"
          >
            {{
              t(
                `tools.layout-harmonizer.export.errors.${validation.prefixError}`,
                { max: exportLimits.prefixMaxLength },
              )
            }}
          </p>
        </div>

        <div class="space-y-2">
          <Label for="layout-export-scope">
            {{ t('tools.layout-harmonizer.export.scope_label') }}
          </Label>
          <Select v-model="selectedScopeKind">
            <SelectTrigger
              id="layout-export-scope"
              class="w-full"
              data-test="layout-export-scope"
            >
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="root">
                {{
                  t(
                    'tools.layout-harmonizer.export.scope_root',
                  )
                }}
              </SelectItem>
              <SelectItem value="class">
                {{
                  t(
                    'tools.layout-harmonizer.export.scope_class',
                  )
                }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>

        <div
          v-if="scopeKind === 'class'"
          class="space-y-2"
        >
          <Label for="layout-export-scope-class">
            {{
              t(
                'tools.layout-harmonizer.export.scope_class_label',
              )
            }}
          </Label>
          <Input
            id="layout-export-scope-class"
            v-model="className"
            class="w-full"
            :aria-invalid="validation.scopeError !== null"
            :aria-describedby="
              validation.scopeError !== null
                ? SCOPE_ERROR_ID
                : undefined
            "
            data-test="layout-export-scope-class"
          />
          <p
            v-if="validation.scopeError !== null"
            :id="SCOPE_ERROR_ID"
            class="text-xs text-destructive"
            role="alert"
            data-test="layout-export-scope-error"
          >
            {{
              t(
                `tools.layout-harmonizer.export.errors.${validation.scopeError}`,
                { max: exportLimits.scopeMaxLength },
              )
            }}
          </p>
        </div>
      </div>

      <div class="flex flex-wrap gap-2">
        <Button
          type="button"
          size="sm"
          variant="outline"
          :disabled="!canExport"
          data-test="layout-export-markdown"
          @click="downloadMarkdown"
        >
          <Download class="size-4" />
          {{ t('tools.layout-harmonizer.export.download_markdown') }}
        </Button>
        <Button
          type="button"
          size="sm"
          variant="outline"
          :disabled="!canExport"
          data-test="layout-export-css"
          @click="downloadCss"
        >
          <Download class="size-4" />
          {{ t('tools.layout-harmonizer.export.download_css') }}
        </Button>
        <Button
          type="button"
          size="sm"
          variant="outline"
          :disabled="!canExport"
          data-test="layout-export-zip"
          @click="downloadZip"
        >
          <Download class="size-4" />
          {{ t('tools.layout-harmonizer.export.download_zip') }}
        </Button>
      </div>
    </template>
  </section>
</template>
