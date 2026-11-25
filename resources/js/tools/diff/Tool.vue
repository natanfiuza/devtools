<script setup lang="ts">
import { EditorView } from '@codemirror/view';
import {
    ChevronDown,
    ChevronUp,
    Copy,
    Download,
    Eraser,
    FolderOpen,
    Sparkles,
} from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import {
    computed,
    onBeforeUnmount,
    onMounted,
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
import { useCopyFeedback } from '@/composables/useCopyFeedback';
import { useFileError } from '@/composables/useFileError';
import { byteLength, formatBytes } from '@/lib/bytes';
import { downloadText } from '@/lib/download';
import type { EditorError } from '@/lib/editor/createEditor';
import type { LineHighlight } from '@/lib/editor/highlights';
import { acceptAttribute, readTextFile } from '@/lib/file';
import { positionAt } from '@/lib/position';
import { createWorkerClient } from '@/lib/worker/client';
import { diffDocuments } from './lib/diff';
import { createDiffWorker } from './lib/diff.workerHost';
import {
    JSON_EXAMPLE_LEFT,
    JSON_EXAMPLE_RIGHT,
    TEXT_EXAMPLE_LEFT,
    TEXT_EXAMPLE_RIGHT,
} from './lib/examples';
import { buildReport, type ReportLabels } from './lib/report';
import type {
    DiffHunk,
    DiffMode,
    DiffRequest,
    DiffResult,
    JsonDiffFailure,
    JsonDiffResult,
} from './types';

/*
 * O diff de dois textos, lado a lado.
 *
 * Os dois paineis sao entrada, e nao entrada e saida - por isso a casca recebe
 * os rotulos "Original"/"Modificado". O modo texto compara arquivo (linha a
 * linha, minimo de edicoes); o modo JSON compara dado (caminhos, ordem de
 * chave nao conta). Sao duas perguntas diferentes, e a tela nao esconde a
 * escolha: o modo e um botao pressionado, nao uma adivinhacao.
 *
 * As linhas que mudaram sao pintadas **dentro** dos editores (o motor do diff
 * so diz o que mudou; a pintura e `setLineHighlights`). A navegacao leva os
 * dois paineis ate a regiao da vez, e a rolagem de um acompanha a do outro.
 *
 * Acima de 128 KiB somados os dois lados, a conta vai para o worker; abaixo,
 * roda aqui mesmo. O modo JSON com documento invalido e um **resultado**, nao
 * um erro solto: um lado invalido so diz "nao ha como comparar", e indica
 * onde.
 */

const props = defineProps<{
    /** Limite de entrada em bytes por lado, vindo de `config/tools.php`. */
    maxInputBytes: number;
}>();

const { t, locale } = useI18n();

const client = createWorkerClient<DiffRequest, DiffResult>(
    createDiffWorker,
    (request) => diffDocuments(request),
);

/** Acima disto (os dois lados somados) o diff vai para o worker. */
const WORKER_THRESHOLD = 128 * 1024;

/** Espera entre a ultima tecla e o diff: digitar nao compara por tecla. */
const DEBOUNCE_MS = 200;

/**
 * O que a caixa de arquivo aceita.
 *
 * O diff compara **texto**: a lista e de formatos textuais, e nao dos quatro
 * do conversor. Arquivo com extensao fora da lista e com MIME de texto passa
 * pela segunda checagem de `readTextFile`.
 */
const FILE_RULES = {
    extensions: [
        '.txt',
        '.text',
        '.json',
        '.yaml',
        '.yml',
        '.toml',
        '.xml',
        '.md',
        '.csv',
        '.log',
        '.ini',
        '.conf',
        '.cfg',
        '.sql',
        '.html',
        '.css',
        '.js',
        '.ts',
        '.php',
        '.py',
        '.rb',
        '.sh',
    ],
    types: [
        'text/plain',
        'text/markdown',
        'text/csv',
        'text/html',
        'text/css',
        'text/javascript',
        'application/json',
        'application/xml',
        'text/xml',
    ],
};

/** O texto dos dois paineis - a fonte da verdade da tela. */
const left = ref('');
const right = ref('');
const mode = ref<DiffMode>('text');

/**
 * O ultimo resultado, com o pedido que o gerou.
 *
 * Guardar o pedido e o que deixa a tela saber se o resultado ainda vale: entre
 * a tecla e a resposta do worker o texto ja mudou, e pintar o diff antigo seria
 * mentir. `shallowRef`: o resultado nao precisa ser reativo.
 */
const outcome = shallowRef<{ request: DiffRequest; result: DiffResult } | null>(
    null,
);

const dirty = ref(false);
const busy = ref(false);
const tooLarge = ref(false);

/** O hunk da navegacao; `-1` quer dizer que nenhum foi escolhido ainda. */
const currentHunk = ref(-1);

const copyFeedback = useCopyFeedback();
const fileError = useFileError(
    () => props.maxInputBytes,
    () => t('tools.diff.file_types'),
);

const leftDropzone =
    useTemplateRef<InstanceType<typeof FileDropzone>>('leftDropzone');
const rightDropzone =
    useTemplateRef<InstanceType<typeof FileDropzone>>('rightDropzone');
const leftEditor = useTemplateRef<{ view: EditorView | null }>('leftEditor');
const rightEditor = useTemplateRef<{ view: EditorView | null }>('rightEditor');

/*
| O diff
*/

const isEmpty = computed(
    () => left.value.trim() === '' && right.value.trim() === '',
);

/** O resultado que ainda corresponde ao texto e ao modo na tela. */
const currentResult = computed<DiffResult | null>(() => {
    const entry = outcome.value;

    if (entry === null || dirty.value) {
        return null;
    }

    if (
        entry.request.left !== left.value ||
        entry.request.right !== right.value ||
        entry.request.mode !== mode.value
    ) {
        return null;
    }

    return entry.result;
});

const textDiff = computed(() => {
    const result = currentResult.value;

    return result !== null && result.mode === 'text' ? result.text : null;
});

const hunks = computed<DiffHunk[]>(() => textDiff.value?.hunks ?? []);

const jsonResult = computed<JsonDiffResult | null>(() => {
    const result = currentResult.value;

    return result !== null && result.mode === 'json' ? result.json : null;
});

const jsonChanges = computed(() =>
    jsonResult.value !== null && jsonResult.value.ok
        ? jsonResult.value.changes
        : [],
);

async function runDiff(): Promise<void> {
    const request: DiffRequest = {
        left: left.value,
        right: right.value,
        mode: mode.value,
    };
    const leftBytes = byteLength(request.left);
    const rightBytes = byteLength(request.right);

    if (leftBytes > props.maxInputBytes || rightBytes > props.maxInputBytes) {
        tooLarge.value = true;
        outcome.value = null;
        dirty.value = false;
        busy.value = false;

        return;
    }

    tooLarge.value = false;

    let result: DiffResult | null;

    if (leftBytes + rightBytes > WORKER_THRESHOLD) {
        busy.value = true;
        result = await client.run(request);
    } else {
        result = diffDocuments(request);
    }

    // Superado (ou o texto/o modo mudaram enquanto a conta rodava): quem pinta
    // a tela e o pedido mais novo.
    if (
        result === null ||
        left.value !== request.left ||
        right.value !== request.right ||
        mode.value !== request.mode
    ) {
        return;
    }

    busy.value = false;
    outcome.value = { request, result };
    dirty.value = false;
    currentHunk.value = -1;
}

const diffSoon = useDebounceFn(() => {
    void runDiff();
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

    dirty.value = true;

    if (intake === 'now') {
        intake = 'debounced';
        void runDiff();

        return;
    }

    diffSoon();
}

watch([left, right], onTextChange);

/*
| O modo
|
| Nao vem dos paineis, entao nao passa pelo `intake`: trocar o modo e uma acao
| pronta, como um botao - compara ja.
*/
watch(mode, () => {
    if (isEmpty.value) {
        reset();

        return;
    }

    dirty.value = true;
    void runDiff();
});

function reset(): void {
    outcome.value = null;
    dirty.value = false;
    busy.value = false;
    tooLarge.value = false;
    currentHunk.value = -1;
    copyFeedback.clear();
    fileError.clear();
}

/** Troca o documento inteiro de um lado: e o caminho de colar, abrir e exemplo. */
function applyText(side: 'left' | 'right', next: string): void {
    fileError.clear();
    copyFeedback.clear();

    const target = side === 'left' ? left : right;

    // Sem mudanca de texto nao ha `watch`: deixar o `intake` armado faria a
    // proxima tecla digitada ser comparada fora do intervalo.
    if (target.value === next) {
        return;
    }

    intake = 'now';
    target.value = next;
}

/*
| As acoes
*/

function setMode(next: DiffMode): void {
    mode.value = next;
}

function loadExample(): void {
    const isJson = mode.value === 'json';

    applyText('left', isJson ? JSON_EXAMPLE_LEFT : TEXT_EXAMPLE_LEFT);
    applyText('right', isJson ? JSON_EXAMPLE_RIGHT : TEXT_EXAMPLE_RIGHT);
}

function clear(): void {
    applyText('left', '');
    applyText('right', '');
}

const hasReport = computed(() => currentResult.value !== null);

function copyReport(): void {
    const result = currentResult.value;

    if (result !== null) {
        void copyFeedback.copy(buildReport(result, reportLabels.value));
    }
}

function download(): void {
    const result = currentResult.value;

    if (result !== null) {
        downloadText(
            `${t('tools.diff.download_name')}.txt`,
            buildReport(result, reportLabels.value),
            'text/plain;charset=utf-8',
        );
    }
}

/*
| A pintura
*/

const leftHighlights = computed<LineHighlight[]>(() => {
    const diff = textDiff.value;

    if (diff === null) {
        return [];
    }

    return diff.hunks.flatMap((hunk) =>
        hunk.lines
            .filter((line) => line.type === 'removed')
            .map((line) => ({
                line: line.line,
                tone: line.paired ? ('changed' as const) : ('removed' as const),
            })),
    );
});

const rightHighlights = computed<LineHighlight[]>(() => {
    const diff = textDiff.value;

    if (diff === null) {
        return [];
    }

    return diff.hunks.flatMap((hunk) =>
        hunk.lines
            .filter((line) => line.type === 'added')
            .map((line) => ({
                line: line.line,
                tone: line.paired ? ('changed' as const) : ('added' as const),
            })),
    );
});

/*
| A navegacao
*/

function scrollToLine(view: EditorView | null, line: number): void {
    if (view === null) {
        return;
    }

    const target = Math.min(Math.max(line, 0), view.state.doc.lines - 1);
    const pos = view.state.doc.line(target + 1).from;

    view.dispatch({
        selection: { anchor: pos },
        effects: EditorView.scrollIntoView(pos, { y: 'center' }),
    });
}

function step(delta: number): void {
    const total = hunks.value.length;

    if (total === 0) {
        return;
    }

    const next =
        currentHunk.value < 0
            ? delta > 0
                ? 0
                : total - 1
            : (currentHunk.value + delta + total) % total;

    currentHunk.value = next;

    const hunk = hunks.value[next];

    if (hunk !== undefined) {
        scrollToLine(leftEditor.value?.view ?? null, hunk.leftStart);
        scrollToLine(rightEditor.value?.view ?? null, hunk.rightStart);
    }
}

/*
| A rolagem acompanhada
|
| Proporcional, e nao por linha: os dois documentos tem alturas diferentes, e
| alinhar por porcentagem mantem a leitura na mesma regiao. As duas direcoes
| existem; o empate numerico impede o vaivem (trocar por um valor igual nao
| dispara evento de rolagem).
*/

const cleanups: Array<() => void> = [];

function follow(source: EditorView | null, target: EditorView | null): void {
    if (source === null || target === null || source === target) {
        return;
    }

    // Constantes locais: e o que mantem o estreitamento de tipo dentro do
    // ouvinte, que roda muito depois do `if`.
    const from = source;
    const to = target;

    function onScroll(): void {
        const sourceMax =
            from.scrollDOM.scrollHeight - from.scrollDOM.clientHeight;
        const targetMax = to.scrollDOM.scrollHeight - to.scrollDOM.clientHeight;
        const ratio = sourceMax > 0 ? from.scrollDOM.scrollTop / sourceMax : 0;

        to.scrollDOM.scrollTop = ratio * targetMax;
    }

    from.scrollDOM.addEventListener('scroll', onScroll, { passive: true });
    cleanups.push(() => {
        from.scrollDOM.removeEventListener('scroll', onScroll);
    });
}

onMounted(() => {
    const leftView = leftEditor.value?.view ?? null;
    const rightView = rightEditor.value?.view ?? null;

    follow(leftView, rightView);
    follow(rightView, leftView);
});

/*
| Arquivo
*/

async function onFile(side: 'left' | 'right', file: File): Promise<void> {
    fileError.clear();

    const result = await readTextFile(file, {
        ...FILE_RULES,
        maxBytes: props.maxInputBytes,
    });

    if (!result.ok) {
        fileError.fail(result.kind, result.size);

        return;
    }

    applyText(side, result.text);
}

function openFile(side: 'left' | 'right'): void {
    const zone = side === 'left' ? leftDropzone : rightDropzone;

    zone.value?.pick();
}

/*
| Os recados da tela
*/

/** O rotulo de cada lado, ja traduzido. */
function sideLabel(side: 'left' | 'right'): string {
    return side === 'left' ? t('tools.diff.left') : t('tools.diff.right');
}

/**
 * O erro que o editor marca.
 *
 * So o modo JSON tem posicao no painel: um lado invalido leva a marca no
 * texto dele. Sem linha/coluna do motor, nao ha onde marcar - o recado fica no
 * rodape.
 */
function sideError(side: 'left' | 'right'): EditorError | null {
    const json = jsonResult.value;

    if (json === null || json.ok || json.side !== side) {
        return null;
    }

    if (json.line === null || json.column === null) {
        return null;
    }

    const source = side === 'left' ? left.value : right.value;

    return {
        position: positionAt(source, json.line, json.column),
        message: t('tools.ui.states.invalid', {
            line: json.line,
            column: json.column,
        }),
    };
}

/** O recado do JSON invalido, com o lado e a posicao quando o motor deu. */
function failureText(json: JsonDiffFailure): string {
    const message =
        json.line === null || json.column === null
            ? t('tools.ui.states.invalid_no_position')
            : t('tools.ui.states.invalid', {
                  line: json.line,
                  column: json.column,
              });

    return t('tools.diff.invalid_side', {
        side: sideLabel(json.side),
        message,
    });
}

const jsonFailureMessage = computed(() => {
    const json = jsonResult.value;

    return json !== null && !json.ok ? failureText(json) : null;
});

/**
 * O recado do rodape: o erro do arquivo manda, depois o que acabou de
 * acontecer e, por ultimo, o resumo do diff.
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

    if (tooLarge.value) {
        return {
            text: t('tools.ui.states.too_large', {
                size: formatBytes(
                    Math.max(byteLength(left.value), byteLength(right.value)),
                    locale.value,
                ),
                limit: formatBytes(props.maxInputBytes, locale.value),
            }),
            error: true,
        };
    }

    const failure = jsonFailureMessage.value;

    if (failure !== null) {
        return { text: failure, error: true };
    }

    const result = currentResult.value;

    if (result === null) {
        return busy.value
            ? { text: t('tools.ui.states.parsing'), error: false }
            : null;
    }

    if (result.mode === 'text') {
        if (result.text.hunks.length === 0) {
            return { text: t('tools.diff.no_changes'), error: false };
        }

        const summary = t('tools.diff.summary.text', {
            added: result.text.added,
            removed: result.text.removed,
        });

        return {
            text: result.text.truncated
                ? `${summary} · ${t('tools.diff.truncated')}`
                : summary,
            error: false,
        };
    }

    const json = result.json;

    if (!json.ok) {
        return { text: failureText(json), error: true };
    }

    if (json.changes.length === 0) {
        return { text: t('tools.diff.no_changes'), error: false };
    }

    return {
        text: t('tools.diff.summary.json', {
            added: json.added,
            removed: json.removed,
            changed: json.changed,
        }),
        error: false,
    };
});

const reportLabels = computed<ReportLabels>(() => ({
    title: t('tools.diff.report.title'),
    text: t('tools.diff.report.text'),
    json: t('tools.diff.report.json'),
    original: t('tools.diff.report.original'),
    modified: t('tools.diff.report.modified'),
    added: t('tools.diff.report.added'),
    removed: t('tools.diff.report.removed'),
    changed: t('tools.diff.report.changed'),
    noChanges: t('tools.diff.report.no_changes'),
    truncated: t('tools.diff.report.truncated'),
    invalid: t('tools.diff.report.invalid'),
    line: t('tools.diff.report.line'),
    column: t('tools.diff.report.column'),
}));

onBeforeUnmount(() => {
    client.dispose();

    for (const cleanup of cleanups.splice(0)) {
        cleanup();
    }
});
</script>

<template>
    <ToolShell
        split-key="diff"
        :pane-labels="{
            input: t('tools.diff.left'),
            output: t('tools.diff.right'),
        }"
    >
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
                    :label="t('tools.ui.actions.clear')"
                    :disabled="isEmpty"
                    @click="clear"
                >
                    <template #icon>
                        <Eraser class="size-3.5" />
                    </template>
                </ToolButton>

                <ToolButton
                    :label="t('tools.diff.modes.text')"
                    :pressed="mode === 'text'"
                    :variant="mode === 'text' ? 'secondary' : 'outline'"
                    data-test="diff-mode-text"
                    @click="setMode('text')"
                />

                <ToolButton
                    :label="t('tools.diff.modes.json')"
                    :pressed="mode === 'json'"
                    :variant="mode === 'json' ? 'secondary' : 'outline'"
                    data-test="diff-mode-json"
                    @click="setMode('json')"
                />

                <template #end>
                    <ToolButton
                        :label="t('tools.diff.previous')"
                        :aria-label="t('tools.diff.previous')"
                        :disabled="hunks.length === 0"
                        data-test="diff-previous"
                        @click="step(-1)"
                    >
                        <template #icon>
                            <ChevronUp class="size-3.5" />
                        </template>
                    </ToolButton>

                    <ToolButton
                        :label="t('tools.diff.next')"
                        :aria-label="t('tools.diff.next')"
                        :disabled="hunks.length === 0"
                        data-test="diff-next"
                        @click="step(1)"
                    >
                        <template #icon>
                            <ChevronDown class="size-3.5" />
                        </template>
                    </ToolButton>

                    <ToolButton
                        :label="t('tools.ui.actions.copy')"
                        :disabled="!hasReport"
                        data-test="diff-copy"
                        @click="copyReport"
                    >
                        <template #icon>
                            <Copy class="size-3.5" />
                        </template>
                    </ToolButton>

                    <ToolButton
                        :label="t('tools.ui.actions.download')"
                        :disabled="!hasReport"
                        data-test="diff-download"
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
            <ToolButton
                :label="t('tools.ui.actions.open')"
                :aria-label="t('tools.diff.open_left')"
                :disabled="tooLarge"
                data-test="diff-open-left"
                @click="openFile('left')"
            >
                <template #icon>
                    <FolderOpen class="size-3.5" />
                </template>
            </ToolButton>
        </template>

        <template #input>
            <FileDropzone
                ref="leftDropzone"
                :accept="acceptAttribute(FILE_RULES)"
                testid="diff-left"
                @file="onFile('left', $event)"
            >
                <CodeEditor
                    ref="leftEditor"
                    v-model="left"
                    testid="diff-left-editor"
                    class="absolute inset-0"
                    :placeholder="t('tools.ui.states.empty')"
                    :error="sideError('left')"
                    :line-highlights="leftHighlights"
                />
            </FileDropzone>
        </template>

        <template #output-header>
            <ToolButton
                :label="t('tools.ui.actions.open')"
                :aria-label="t('tools.diff.open_right')"
                :disabled="tooLarge"
                data-test="diff-open-right"
                @click="openFile('right')"
            >
                <template #icon>
                    <FolderOpen class="size-3.5" />
                </template>
            </ToolButton>
        </template>

        <template #output>
            <FileDropzone
                ref="rightDropzone"
                :accept="acceptAttribute(FILE_RULES)"
                testid="diff-right"
                @file="onFile('right', $event)"
            >
                <CodeEditor
                    ref="rightEditor"
                    v-model="right"
                    testid="diff-right-editor"
                    class="absolute inset-0"
                    :placeholder="t('tools.ui.states.empty')"
                    :error="sideError('right')"
                    :line-highlights="rightHighlights"
                />
            </FileDropzone>
        </template>

        <template #status>
            <p
                v-if="status !== null"
                class="text-xs"
                :class="status.error ? 'text-destructive' : 'text-subtle'"
                data-test="diff-status"
            >
                {{ status.text }}
            </p>
        </template>
    </ToolShell>

    <!--
        No modo JSON o resultado e uma lista de caminhos: ela sai **abaixo** da
        casca porque nao pertence a nenhum dos dois paineis - e o dado que os
        dois formam juntos.
    -->
    <section
        v-if="jsonChanges.length > 0"
        class="mt-3 flex flex-col gap-1"
        data-test="diff-changes"
    >
        <h2 class="text-[11px] font-medium tracking-wide text-subtle uppercase">
            {{ t('tools.diff.changes_title') }}
        </h2>
        <ul class="flex flex-col gap-0.5 font-mono text-xs">
            <li
                v-for="change in jsonChanges"
                :key="`${change.kind}:${change.path}`"
                class="flex flex-wrap items-baseline gap-x-2 rounded px-1"
                :data-kind="change.kind"
                data-test="diff-change"
            >
                <span
                    class="w-3 shrink-0 text-center font-bold"
                    :class="{
                        'text-accent-2': change.kind === 'added',
                        'text-destructive': change.kind === 'removed',
                        'text-brand': change.kind === 'changed',
                    }"
                    aria-hidden="true"
                    >{{
                        change.kind === 'added'
                            ? '+'
                            : change.kind === 'removed'
                              ? '−'
                              : '~'
                    }}</span
                >
                <code class="break-all">{{ change.path }}</code>
                <span
                    v-if="change.kind === 'changed'"
                    class="break-all text-subtle"
                >
                    {{ change.left }} → {{ change.right }}
                </span>
                <span v-else class="break-all text-subtle">
                    {{ change.left ?? change.right }}
                </span>
            </li>
        </ul>
    </section>
</template>
