<script setup lang="ts">
import {
    ArrowLeftRight,
    Copy,
    Download,
    Eraser,
    FolderOpen,
    Sparkles,
    TriangleAlert,
} from '@lucide/vue';
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
import { acceptAttribute, readTextFile } from '@/lib/file';
import { byteLength } from '@/lib/json/parse';
import { positionAt } from '@/lib/position';
import { createWorkerClient } from '@/lib/worker/client';
import { convert } from './lib/convert';
import { createConvertWorker } from './lib/convert.workerHost';
import { JSON_EXAMPLE } from './lib/examples';
import {
    DEFAULT_FROM,
    DEFAULT_TO,
    FILE_EXTENSIONS,
    FILE_TYPES,
    FORMAT_EXTENSIONS,
    FORMAT_MEDIA_TYPES,
} from './lib/formats';
import type { Loss } from './lib/lossy';
import { FORMATS, type ConvertRequest, type ConvertResult } from './types';

/*
 * O conversor de formatos.
 *
 * A entrada a esquerda, a saida a direita, e os dois formatos escolhidos na
 * tela - a tool nao adivinha o formato do texto colado. A conversao passa por
 * uma arvore intermediaria (`lib/tree`), entao cada formato tem um parser e um
 * serializador so, e a matriz e completa: JSON, YAML, TOML e XML em qualquer
 * direcao.
 *
 * O que o destino nao representa sai como aviso, e nao como erro: o TOML nao
 * tem `null`, o JSON nao tem comentario. Quem converteu ve o aviso e confere o
 * que perdeu no painel da direita.
 *
 * Acima de 128 KiB a conta vai para o worker; abaixo, roda aqui mesmo.
 */

const props = defineProps<{
    /** Limite de entrada em bytes, vindo de `config/tools.php` pelo controller. */
    maxInputBytes: number;
}>();

const { t, locale } = useI18n();

const client = createWorkerClient<ConvertRequest, ConvertResult>(
    createConvertWorker,
    (request) => convert(request),
);

/** Acima disto a conversao vai para o worker. */
const WORKER_THRESHOLD = 128 * 1024;

/** Espera entre a ultima tecla e a conversao: digitar nao converte por tecla. */
const DEBOUNCE_MS = 200;

/** O que a caixa de arquivo aceita; o mesmo vale para o `accept` do seletor. */
const FILE_RULES = { extensions: FILE_EXTENSIONS, types: FILE_TYPES };

/** O texto do painel de entrada - a fonte da verdade da tela. */
const text = ref('');
/** Os dois formatos escolhidos, com os defaults de quem abre a tela. */
const from = ref(DEFAULT_FROM);
const to = ref(DEFAULT_TO);

/** O resultado da conversao. `shallowRef`: o resultado nao precisa ser reativo. */
const outcome = shallowRef<ConvertResult | null>(null);
const busy = ref(false);
const tooLarge = ref(false);
const inputBytes = ref(0);

const copyFeedback = useCopyFeedback();
const fileError = useFileError(
    () => props.maxInputBytes,
    () => t('tools.data-format-converter.file_types'),
);

const dropzone = useTemplateRef<InstanceType<typeof FileDropzone>>('dropzone');

/*
| A conversao
*/

const isEmpty = computed(() => text.value.trim() === '');

/**
 * A ultima conversao que deu certo.
 *
 * E o que sustenta a promessa do SPEC (secao 5): enquanto o texto esta
 * invalido - ou grande demais -, a saida da ultima versao valida **continua
 * na tela**, marcada como defasada.
 */
const lastOutput = shallowRef<{ text: string; losses: Loss[] } | null>(null);

/** Nao ha conversao valida para o texto atual: a saida na tela e a anterior. */
const stale = computed(
    () =>
        !(outcome.value?.ok ?? false) &&
        !isEmpty.value &&
        lastOutput.value !== null,
);

/** O documento convertido que a saida mostra: o de agora, ou o da ultima valida. */
const output = computed(() => {
    const current = outcome.value;

    if (current !== null && current.ok) {
        return current.text;
    }

    return stale.value ? (lastOutput.value?.text ?? '') : '';
});

const hasOutput = computed(() => output.value !== '');

/** As perdas da conversao atual; na saida defasada elas saem da tela junto. */
const losses = computed<Loss[]>(() => {
    const current = outcome.value;

    return current !== null && current.ok && !stale.value ? current.losses : [];
});

const errorMessage = computed(() => {
    const current = outcome.value;

    if (current === null || current.ok) {
        return null;
    }

    if (current.code === 'parse') {
        return current.line === null || current.column === null
            ? t('tools.ui.states.invalid_no_position')
            : t('tools.ui.states.invalid', {
                  line: current.line,
                  column: current.column,
              });
    }

    // O detalhe e a chave (ou o caminho) que nao coube no destino - dado do
    // documento, e nao texto de tela; a frase em volta e traducao.
    return t(`tools.data-format-converter.errors.${current.code}`, {
        key: current.detail ?? '',
        path: current.detail ?? '',
    });
});

/**
 * O erro que o editor marca.
 *
 * So o erro do parser tem posicao no texto de origem; os conflitos de formato
 * de destino ficam no rodape. A linha e a coluna viram posicao com
 * `positionAt`, a mesma conta do SQL Formatter.
 */
const editorError = computed<EditorError | null>(() => {
    const current = outcome.value;

    if (
        current === null ||
        current.ok ||
        current.code !== 'parse' ||
        current.line === null ||
        current.column === null
    ) {
        return null;
    }

    return {
        position: positionAt(text.value, current.line, current.column),
        message: errorMessage.value ?? '',
    };
});

async function runConvert(): Promise<void> {
    const value = text.value;
    const request: ConvertRequest = {
        text: value,
        from: from.value,
        to: to.value,
    };
    const bytes = byteLength(value);

    inputBytes.value = bytes;

    if (bytes > props.maxInputBytes) {
        tooLarge.value = true;
        outcome.value = null;
        busy.value = false;

        return;
    }

    tooLarge.value = false;

    let result: ConvertResult | null;

    if (bytes > WORKER_THRESHOLD) {
        busy.value = true;
        result = await client.run(request);
    } else {
        result = convert(request);
    }

    // Superado (ou o texto/os formatos mudaram enquanto a conta rodava): quem
    // pinta a tela e o pedido mais novo.
    if (
        result === null ||
        text.value !== value ||
        from.value !== request.from ||
        to.value !== request.to
    ) {
        return;
    }

    busy.value = false;
    outcome.value = result;

    if (result.ok) {
        lastOutput.value = { text: result.text, losses: result.losses };
    }
}

const convertSoon = useDebounceFn(() => {
    void runConvert();
}, DEBOUNCE_MS);

/**
 * Como o proximo texto entra: um botao ja quer o resultado (`now`) ou foi
 * digitacao (`debounced`).
 */
let intake: 'now' | 'debounced' = 'debounced';

function onTextChange(): void {
    if (isEmpty.value) {
        intake = 'debounced';
        reset();

        return;
    }

    if (intake === 'now') {
        intake = 'debounced';
        void runConvert();

        return;
    }

    convertSoon();
}

watch(text, onTextChange);

/*
| Os formatos
|
| Nao vem do painel de entrada, entao nao passam pelo `intake`: escolher o
| formato e uma acao pronta, como um botao - converte ja.
*/
watch([from, to], () => {
    if (!isEmpty.value) {
        void runConvert();
    }
});

function reset(): void {
    outcome.value = null;
    // Texto vazio nao tem "ultima versao valida": a saida sai da tela.
    lastOutput.value = null;
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
    // proxima tecla digitada ser convertida fora do intervalo.
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
    // O exemplo e JSON: o seletor de entrada volta para ele, senao o texto
    // chegaria num formato que nao e o dele.
    from.value = DEFAULT_FROM;
    applyText(JSON_EXAMPLE);
}

function clear(): void {
    applyText('');
}

function swapFormats(): void {
    const previous = from.value;
    from.value = to.value;
    to.value = previous;
}

function copyOutput(): void {
    void copyFeedback.copy(output.value);
}

function download(): void {
    downloadText(
        `${t('tools.data-format-converter.download_name')}.${FORMAT_EXTENSIONS[to.value]}`,
        output.value,
        FORMAT_MEDIA_TYPES[to.value],
    );
}

/** O aviso de perda, ja traduzido; a contagem so aparece quando e mais de um. */
function lossMessage(loss: Loss): string {
    return t(`tools.data-format-converter.losses.${loss.code}`, {
        count: loss.count,
    });
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

    // A extensao do arquivo nao troca o seletor: a escolha do formato de
    // entrada e de quem usa (ADR-180), e um `.txt` nao diz formato nenhum.
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

/** O recado do painel de saida, quando nao ha documento convertido. */
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

    return hasOutput.value
        ? { text: statsMessage.value, error: false }
        : state.value;
});

onBeforeUnmount(() => {
    client.dispose();
});
</script>

<template>
    <ToolShell split-key="data-format-converter">
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

                <ToolButton
                    :label="t('tools.data-format-converter.swap')"
                    :disabled="isEmpty"
                    @click="swapFormats"
                >
                    <template #icon>
                        <ArrowLeftRight class="size-3.5" />
                    </template>
                </ToolButton>

                <template #end>
                    <ToolButton
                        :label="t('tools.ui.actions.copy')"
                        :disabled="!hasOutput"
                        @click="copyOutput"
                    >
                        <template #icon>
                            <Copy class="size-3.5" />
                        </template>
                    </ToolButton>

                    <ToolButton
                        :label="t('tools.ui.actions.download')"
                        :disabled="!hasOutput"
                        @click="download"
                    >
                        <template #icon>
                            <Download class="size-3.5" />
                        </template>
                    </ToolButton>
                </template>
            </ToolActionBar>
        </template>

        <template #input-header>
            <!--
                O formato de **entrada** fica no cabecalho da entrada: e o que
                ele descreve. O de saida faz o mesmo do outro lado.
            -->
            <Select v-model="from">
                <SelectTrigger
                    class="h-7 w-28 text-xs"
                    :aria-label="t('tools.data-format-converter.from')"
                    data-test="data-format-converter-from"
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="format in FORMATS"
                        :key="format"
                        :value="format"
                    >
                        {{ t(`tools.data-format-converter.formats.${format}`) }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </template>

        <template #input>
            <FileDropzone
                ref="dropzone"
                :accept="acceptAttribute(FILE_RULES)"
                testid="data-format-converter"
                @file="onFile"
            >
                <CodeEditor
                    v-model="text"
                    testid="data-format-converter-editor"
                    class="absolute inset-0"
                    :placeholder="t('tools.ui.states.empty')"
                    :error="editorError"
                />
            </FileDropzone>
        </template>

        <template #output-header>
            <Select v-model="to">
                <SelectTrigger
                    class="h-7 w-28 text-xs"
                    :aria-label="t('tools.data-format-converter.to')"
                    data-test="data-format-converter-to"
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="format in FORMATS"
                        :key="format"
                        :value="format"
                    >
                        {{ t(`tools.data-format-converter.formats.${format}`) }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </template>

        <template #output>
            <!--
                O aviso de perda e visivel e fica **acima** do resultado: quem
                converteu precisa saber o que conferir, e o texto convertido
                continua ali para copiar.
            -->
            <div
                v-if="losses.length > 0"
                role="status"
                class="flex flex-col gap-1 border-b border-destructive/30 bg-destructive/5 px-3 py-1.5 text-xs text-destructive"
                data-test="data-format-converter-losses"
            >
                <p class="flex items-center gap-1.5 font-medium">
                    <TriangleAlert class="size-3.5" aria-hidden="true" />
                    {{ t('tools.data-format-converter.losses.title') }}
                </p>
                <ul class="list-disc ps-5">
                    <li
                        v-for="loss in losses"
                        :key="loss.code"
                        data-test="data-format-converter-loss"
                    >
                        {{ lossMessage(loss) }}
                    </li>
                </ul>
            </div>

            <p
                v-if="stale"
                role="status"
                class="flex items-center justify-center gap-1.5 border-b border-destructive/30 bg-destructive/5 px-3 py-1.5 text-xs text-destructive"
                data-test="data-format-converter-stale"
            >
                <TriangleAlert class="size-3.5" aria-hidden="true" />
                {{ t('tools.ui.states.stale_output') }}
            </p>

            <div class="relative min-h-0 flex-1 overflow-auto">
                <pre
                    v-if="hasOutput"
                    class="p-3 font-mono text-sm transition-opacity"
                    :class="stale ? 'opacity-50' : ''"
                    data-test="data-format-converter-output"
                    >{{ output }}</pre>

                <p
                    v-else-if="state !== null"
                    role="status"
                    class="flex h-full flex-col items-center justify-center gap-2 p-6 text-center text-sm"
                    :class="state.error ? 'text-destructive' : 'text-subtle'"
                    data-test="data-format-converter-state"
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
                data-test="data-format-converter-status"
            >
                {{ status.text }}
            </p>
        </template>
    </ToolShell>
</template>
