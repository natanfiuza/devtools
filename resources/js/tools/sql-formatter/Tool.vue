<script setup lang="ts">
import {
    Copy,
    Download,
    Eraser,
    FolderOpen,
    Sparkles,
    TriangleAlert,
} from '@lucide/vue';
import { sql } from '@codemirror/lang-sql';
import { useDebounceFn } from '@vueuse/core';
import {
    computed,
    onBeforeUnmount,
    ref,
    shallowRef,
    useTemplateRef,
    watch,
} from 'vue';
import { useI18n } from 'vue-i18n';
import CodeEditor from '@/components/tool/CodeEditor.vue';
import FileDropzone from '@/components/tool/FileDropzone.vue';
import ToolActionBar from '@/components/tool/ToolActionBar.vue';
import ToolButton from '@/components/tool/ToolButton.vue';
import ToolShell from '@/components/tool/ToolShell.vue';
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
import { downloadText } from '@/lib/download';
import type { EditorError } from '@/lib/editor/createEditor';
import { acceptAttribute, readTextFile, type FileRules } from '@/lib/file';
import { byteLength } from '@/lib/json/parse';
import { positionAt } from '@/lib/position';
import { createWorkerClient } from '@/lib/worker/client';
import {
    DEFAULT_DIALECT,
    DIALECTS,
    highlighterOf,
    type Dialect,
} from './lib/dialects';
import {
    DEFAULT_KEYWORD_CASE,
    formatSql,
    KEYWORD_CASES,
    type FormatRequest,
    type FormatResult,
    type KeywordCase,
} from './lib/format';
import { createFormatWorker } from './lib/format.workerHost';
import sample from './sample.sql?raw';

/*
 * O SQL Formatter.
 *
 * A consulta a esquerda, o SQL indentado a direita, e o dialeto e a caixa das
 * palavras-chave no cabecalho da **saida**: sao as duas escolhas que mudam o
 * que esta do lado de la, e ficam ao lado dele.
 *
 * A formatacao roda num **worker**, e nao aqui como a inferencia do JSON to
 * Code: nao e uma passada linear, e um parser. Formatado aqui, 1 MB de SQL
 * levaria nove segundos de aba travada - medido -, e o teto de entrada e de
 * 5 MB. Sem worker (SSR, jsdom, CSP), a mesma funcao roda na thread principal e
 * o resultado e identico.
 *
 * O dialeto e a caixa entram na conta, e nao so na pintura: trocar qualquer um
 * dos dois reformata o texto que ja esta na tela.
 */

const props = defineProps<{
    /** Limite de entrada em bytes, vindo de `config/tools.php` pelo controller. */
    maxInputBytes: number;
}>();

const { t, locale } = useI18n();

/** Espera entre a ultima tecla e a formatacao: digitar nao formata por tecla. */
const DEBOUNCE_MS = 200;

/** O que a caixa de arquivo aceita; o mesmo vale para o `accept` do seletor. */
const FILE_RULES: Pick<FileRules, 'extensions' | 'types'> = {
    extensions: ['.sql', '.txt'],
    types: ['application/sql', 'text/plain'],
};

/** O texto do painel de entrada - a fonte da verdade da tela. */
const text = ref('');
/** O resultado da formatacao. `shallowRef`: o resultado nao precisa ser reativo. */
const outcome = shallowRef<FormatResult | null>(null);
const busy = ref(false);
const tooLarge = ref(false);
const inputBytes = ref(0);

/** As duas escolhas da tela, e as duas comecam no padrao. */
const dialect = ref<Dialect>(DEFAULT_DIALECT);
const keywordCase = ref<KeywordCase>(DEFAULT_KEYWORD_CASE);

const copyFeedback = useCopyFeedback();
const fileError = useFileError(
    () => props.maxInputBytes,
    () => t('tools.sql-formatter.file_types'),
);

const dropzone = useTemplateRef<InstanceType<typeof FileDropzone>>('dropzone');

/*
| A formatacao
*/

/**
 * O cliente: worker quando ha, thread principal quando nao ha.
 *
 * A linguagem do realce sai do dialeto **da montagem** - o editor monta a
 * linguagem uma vez e nenhuma troca a reconstroi (o porque esta em
 * `lib/dialects`).
 */
const client = createWorkerClient<FormatRequest, FormatResult>(
    createFormatWorker,
    (request) => formatSql(request.text, request.options),
);

const isEmpty = computed(() => text.value.trim() === '');

/**
 * O SQL da ultima entrada valida.
 *
 * E o que sustenta a promessa do SPEC (secao 5): enquanto o texto esta invalido
 * - ou grande demais -, a saida da ultima versao valida **continua na tela**,
 * marcada como defasada.
 */
const lastText = shallowRef<string | null>(null);

/** Nao ha formatacao valida para o texto atual: a saida na tela e a anterior. */
const stale = computed(
    () =>
        !(outcome.value?.ok ?? false) &&
        !isEmpty.value &&
        lastText.value !== null,
);

/** O SQL que a saida mostra: o de agora, ou o da ultima entrada valida. */
const code = computed(() => {
    const current = outcome.value;

    if (current !== null && current.ok) {
        return current.text;
    }

    return stale.value ? (lastText.value ?? '') : '';
});

const hasCode = computed(() => code.value !== '');

const errorMessage = computed(() => {
    const current = outcome.value;

    if (current === null || current.ok) {
        return null;
    }

    return current.line === null || current.column === null
        ? t('tools.ui.states.invalid_no_position')
        : t('tools.ui.states.invalid', {
              line: current.line,
              column: current.column,
          });
});

/**
 * O erro que o editor marca.
 *
 * A biblioteca informa **linha e coluna**, e nao posicao: a conversao para a
 * coordenada do editor e `positionAt`, a volta de `lineColumnAt`. Sem coluna
 * nao da para apontar lugar nenhum, e a marca fica so no rodape.
 */
const editorError = computed<EditorError | null>(() => {
    const current = outcome.value;

    if (
        current === null ||
        current.ok ||
        current.line === null ||
        current.column === null
    ) {
        return null;
    }

    return {
        position: positionAt(text.value, current.line, current.column),
        message: errorMessage.value ?? current.message,
    };
});

async function runFormat(): Promise<void> {
    const value = text.value;
    const bytes = byteLength(value);

    inputBytes.value = bytes;

    if (bytes > props.maxInputBytes) {
        tooLarge.value = true;
        outcome.value = null;
        busy.value = false;

        return;
    }

    tooLarge.value = false;
    busy.value = true;

    const result = await client.run({
        text: value,
        options: { dialect: dialect.value, keywordCase: keywordCase.value },
    });

    // Superado (ou o texto mudou enquanto a conta rodava): quem pinta a tela e
    // o pedido mais novo, senao a saida mostraria o SQL de antes.
    if (result === null || text.value !== value) {
        return;
    }

    busy.value = false;
    outcome.value = result;

    if (result.ok) {
        lastText.value = result.text;
    }
}

const formatSoon = useDebounceFn(() => {
    void runFormat();
}, DEBOUNCE_MS);

/**
 * Como o proximo texto entra: um botao ja tem o que mostrar (`now`) ou foi
 * digitacao (`debounced`).
 */
let intake: 'now' | 'debounced' = 'debounced';

function onTextChange(): void {
    // O vazio vem primeiro, e nao dentro do caminho da digitacao: o botao Limpar
    // tambem esvazia o painel, e um painel vazio nao tem "ultima versao valida"
    // para a saida mostrar.
    if (isEmpty.value) {
        intake = 'debounced';
        reset();

        return;
    }

    if (intake === 'now') {
        intake = 'debounced';
        void runFormat();

        return;
    }

    formatSoon();
}

watch(text, onTextChange);

/*
| As duas escolhas
|
| Elas nao vem do painel de entrada, entao nao passam pelo `intake`: trocar o
| dialeto ou a caixa e uma acao pronta, como um botao - formata ja.
*/
watch(dialect, () => {
    void runFormat();
});

watch(keywordCase, () => {
    void runFormat();
});

function reset(): void {
    outcome.value = null;
    // Texto vazio nao tem "ultima versao valida": a saida sai da tela.
    lastText.value = null;
    tooLarge.value = false;
    inputBytes.value = 0;
    busy.value = false;
    copyFeedback.clear();
    fileError.clear();
}

/** Troca o documento inteiro: e o caminho de colar, abrir, exemplo e limpar. */
function applyText(next: string): void {
    fileError.clear();
    copyFeedback.clear();

    // Sem mudanca de texto nao ha `watch`: deixar o `intake` armado faria a
    // proxima tecla digitada ser formatada fora do intervalo.
    if (text.value === next) {
        return;
    }

    intake = 'now';
    text.value = next;
}

/*
| As acoes
*/

function loadExample(): void {
    applyText(sample);
}

function clear(): void {
    applyText('');
}

function copyCode(): void {
    void copyFeedback.copy(code.value);
}

function download(): void {
    downloadText(`${t('tools.sql-formatter.download_name')}.sql`, code.value);
}

/*
| Arquivo
*/

/** O arquivo escolhido ou solto entra pelo mesmo caminho do texto colado. */
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

    applyText(result.text);
}

function openFile(): void {
    dropzone.value?.pick();
}

/*
| Os recados da tela
*/

const statsMessage = computed(
    () =>
        `${formatBytes(inputBytes.value, locale.value)} ${t(
            'tools.ui.limits.of',
            {
                limit: formatBytes(props.maxInputBytes, locale.value),
            },
        )}`,
);

/**
 * O recado do painel de saida, quando nao ha SQL para mostrar.
 *
 * Com a saida defasada na tela (`stale`) ele tambem alimenta o rodape: o erro
 * que interrompeu a formatacao continua visivel, ao lado do SQL antigo.
 */
const state = computed<{ text: string; error: boolean } | null>(() => {
    if (tooLarge.value) {
        return {
            text: t('tools.ui.states.too_large', {
                size: formatBytes(inputBytes.value, locale.value),
                limit: formatBytes(props.maxInputBytes, locale.value),
            }),
            error: true,
        };
    }

    const error = errorMessage.value;

    if (error !== null) {
        return { text: error, error: true };
    }

    return busy.value
        ? { text: t('tools.ui.states.parsing'), error: false }
        : null;
});

/**
 * O recado do rodape: o erro do arquivo manda, depois o que acabou de
 * acontecer, depois o erro que deixou a saida defasada, e por ultimo o tamanho.
 */
const status = computed<{ text: string; error: boolean } | null>(() => {
    const fileMessage = fileError.message.value;

    if (fileMessage !== null) {
        return { text: fileMessage, error: true };
    }

    const feedbackText = copyFeedback.message.value;

    if (feedbackText !== null) {
        return { text: feedbackText, error: false };
    }

    if (stale.value) {
        return state.value;
    }

    return hasCode.value
        ? { text: statsMessage.value, error: false }
        : state.value;
});

onBeforeUnmount(() => {
    client.dispose();
});
</script>

<template>
  <ToolShell split-key="sql-formatter">
    <template #actions>
      <ToolActionBar>
        <ToolButton
          :label="t('tools.ui.actions.example')"
          @click="loadExample"
        >
          <template #icon>
            <Sparkles class="size-3.5" />
          </template>
        </ToolButton>

        <ToolButton
          :label="t('tools.ui.actions.open')"
          @click="openFile"
        >
          <template #icon>
            <FolderOpen class="size-3.5" />
          </template>
        </ToolButton>

        <ToolButton
          :label="t('tools.ui.actions.clear')"
          :disabled="isEmpty"
          @click="clear"
        >
          <template #icon>
            <Eraser class="size-3.5" />
          </template>
        </ToolButton>

        <template #end>
          <ToolButton
            :label="t('tools.ui.actions.copy')"
            :disabled="!hasCode"
            @click="copyCode"
          >
            <template #icon>
              <Copy class="size-3.5" />
            </template>
          </ToolButton>

          <ToolButton
            :label="t('tools.ui.actions.download')"
            :disabled="!hasCode"
            @click="download"
          >
            <template #icon>
              <Download class="size-3.5" />
            </template>
          </ToolButton>
        </template>
      </ToolActionBar>
    </template>

    <template #input>
      <FileDropzone
        ref="dropzone"
        :accept="acceptAttribute(FILE_RULES)"
        testid="sql-formatter"
        @file="onFile"
      >
        <CodeEditor
          v-model="text"
          testid="sql-formatter-editor"
          class="absolute inset-0"
          :placeholder="t('tools.ui.states.empty')"
          :error="editorError"
          :extensions="[
            sql({ dialect: highlighterOf(DEFAULT_DIALECT) }),
          ]"
        />
      </FileDropzone>
    </template>

    <!--
            O dialeto e a caixa das palavras-chave ficam no cabecalho da
            **saida**: sao as duas escolhas que mudam o que esta do lado de la.
        -->
    <template #output-header>
      <Select v-model="dialect">
        <SelectTrigger
          class="h-7 w-32 text-xs"
          :aria-label="t('tools.sql-formatter.dialect')"
          data-test="sql-formatter-dialect"
        >
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          <SelectItem
            v-for="item in DIALECTS"
            :key="item"
            :value="item"
          >
            {{ t(`tools.sql-formatter.dialects.${item}`) }}
          </SelectItem>
        </SelectContent>
      </Select>

      <Select v-model="keywordCase">
        <SelectTrigger
          class="h-7 w-36 text-xs"
          :aria-label="t('tools.sql-formatter.keyword_case')"
          data-test="sql-formatter-case"
        >
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          <SelectItem
            v-for="item in KEYWORD_CASES"
            :key="item"
            :value="item"
          >
            {{ t(`tools.sql-formatter.cases.${item}`) }}
          </SelectItem>
        </SelectContent>
      </Select>
    </template>

    <template #output>
      <!--
                O SQL na tela e da ultima entrada valida, e nao do texto que esta
                no editor: o aviso diz isso, e o SQL sai em opacidade reduzida
                (SPEC secao 5).
            -->
      <p
        v-if="stale"
        role="status"
        class="flex items-center justify-center gap-1.5 border-b border-destructive/30 bg-destructive/5 px-3 py-1.5 text-xs text-destructive"
        data-test="sql-formatter-stale"
      >
        <TriangleAlert
          class="size-3.5"
          aria-hidden="true"
        />
        {{ t('tools.ui.states.stale_output') }}
      </p>

      <div class="relative min-h-0 flex-1 overflow-auto">
        <pre
          v-if="hasCode"
          class="p-3 font-mono text-sm transition-opacity"
          :class="stale ? 'opacity-50' : ''"
          data-test="sql-formatter-output"
        >{{ code }}</pre>

        <p
          v-else-if="state !== null"
          role="status"
          class="flex h-full flex-col items-center justify-center gap-2 p-6 text-center text-sm"
          :class="state.error ? 'text-destructive' : 'text-subtle'"
          data-test="sql-formatter-state"
        >
          <TriangleAlert
            v-if="state.error"
            class="size-5"
            aria-hidden="true"
          />
          {{ state.text }}
        </p>
      </div>
    </template>

    <template #status>
      <p
        v-if="status !== null"
        class="text-xs"
        :class="status.error ? 'text-destructive' : 'text-subtle'"
        data-test="sql-formatter-status"
      >
        {{ status.text }}
      </p>
    </template>
  </ToolShell>
</template>
