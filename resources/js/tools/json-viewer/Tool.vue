<script setup lang="ts">
import {
    ArrowDownAZ,
    ChevronsDownUp,
    ChevronsUpDown,
    Copy,
    Download,
    Eraser,
    FolderOpen,
    Minimize2,
    Search,
    Sparkles,
    TriangleAlert,
    WandSparkles,
    X,
} from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { computed, onBeforeUnmount, ref, shallowRef, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import ToolActionBar from '@/components/tool/ToolActionBar.vue';
import ToolButton from '@/components/tool/ToolButton.vue';
import ToolShell from '@/components/tool/ToolShell.vue';
import { Input } from '@/components/ui/input';
import JsonEditor from './editor/JsonEditor.vue';
import type { EditorError } from './editor/createEditor';
import JsonTree from './JsonTree.vue';
import {
    INITIAL_COLLAPSE_DEPTH,
    type AnalyzeOptions,
    type AnalyzeOutcome,
} from './lib/analyze';
import { formatBytes } from './lib/bytes';
import { downloadText } from './lib/download';
import { byteLength, hasBom } from './lib/parse';
import { createAnalyzeClient } from './lib/parseClient';
import { nodePath } from './lib/path';
import { collapseFrom, searchNodes, type JsonNode } from './lib/tree';
import sample from './sample.json?raw';

/*
 * O JSON Viewer.
 *
 * Editor a esquerda, arvore a direita, e **uma** analise alimentando os dois:
 * validar, achatar e - quando alguem pede - reescrever o texto acontece numa
 * funcao so (`analyzeDocument`), que roda no worker e cai para a thread
 * principal quando nao ha worker. A tela nao sabe a diferenca.
 *
 * O documento inteiro vive nesta pagina: nada do que se cola aqui sai do
 * navegador, e o unico limite e o tamanho (`maxInputBytes`), que vem do
 * servidor porque limite nao mora no codigo.
 */

const props = defineProps<{
    /** Limite de entrada em bytes, vindo de `config/tools.php` pelo controller. */
    maxInputBytes: number;
}>();

const { t, locale } = useI18n();

const client = createAnalyzeClient();

/** Espera entre a ultima tecla e a analise: digitar nao analisa por tecla. */
const DEBOUNCE_MS = 200;

/** Quanto tempo o recado de copia fica na tela. */
const FEEDBACK_MS = 2500;

/** O texto do painel de entrada - a fonte da verdade da tela. */
const text = ref('');
/** O resultado da analise. `shallowRef`: a arvore nao precisa ser reativa. */
const outcome = shallowRef<AnalyzeOutcome | null>(null);
/** Containers fechados, por indice na lista achatada. */
const collapsed = shallowRef<Set<number>>(new Set());
const query = ref('');
const busy = ref(false);
const dragging = ref(false);
const tooLarge = ref(false);
const inputBytes = ref(0);

const feedback = ref<'copied' | 'path' | 'manual' | null>(null);
const feedbackPath = ref('');
const fileError = ref<{ kind: FileErrorKind; size: number } | null>(null);

const fileInput = ref<HTMLInputElement | null>(null);
const searchBox = ref<HTMLElement | null>(null);

/** A tecla de comando muda com o teclado, nao com o idioma. */
const commandKey =
    typeof navigator !== 'undefined' && /Mac|iPhone|iPad/.test(navigator.userAgent)
        ? '⌘'
        : 'Ctrl';

/*
| A analise
*/

const success = computed(() => {
    const current = outcome.value;

    return current !== null && current.ok ? current : null;
});

const nodes = computed<JsonNode[]>(() => success.value?.nodes ?? []);
const hasTree = computed(() => nodes.value.length > 0);
const isEmpty = computed(() => text.value.trim() === '');
const canRewrite = computed(() => success.value !== null);

/** O erro do documento: linha e coluna quando o motor informou. */
const errorMessage = computed(() => {
    const current = outcome.value;

    if (current === null || current.ok) {
        return null;
    }

    return current.line === null || current.column === null
        ? t('tools.ui.states.invalid_no_position')
        : t('tools.ui.states.invalid', { line: current.line, column: current.column });
});

/**
 * O erro que o editor marca na linha.
 *
 * A posicao do motor conta o texto **sem** o BOM (que ele nem ve), entao a
 * conversao para a coordenada do editor soma um quando o arquivo veio do
 * Windows - senao a marca aparece um caractere antes do erro.
 */
const editorError = computed<EditorError | null>(() => {
    const current = outcome.value;

    if (current === null || current.ok || current.position === null) {
        return null;
    }

    return {
        position: current.position + (hasBom(text.value) ? 1 : 0),
        message: errorMessage.value ?? current.message,
    };
});

/**
 * Como o proximo texto entra: um botao ja analisou o que ele escreveu
 * (`skip`), um botao quer o resultado ja (`now`) ou foi digitacao (`debounced`).
 *
 * Sem isso, "formatar" analisaria o documento duas vezes - uma para reescrever,
 * outra quando o texto novo chegasse de volta pelo `v-model`.
 */
type Intake = 'skip' | 'now' | 'debounced';

let intake: Intake = 'debounced';

async function runAnalysis(extra: Partial<AnalyzeOptions> = {}): Promise<AnalyzeOutcome | null> {
    const value = text.value;
    const bytes = byteLength(value);

    inputBytes.value = bytes;

    if (bytes > props.maxInputBytes) {
        tooLarge.value = true;
        outcome.value = null;
        busy.value = false;

        return null;
    }

    tooLarge.value = false;
    busy.value = true;

    const result = await client.analyze(value, extra);

    // Superado (ou o texto mudou enquanto a conta rodava): quem pinta a tela e
    // o pedido mais novo, senao a arvore mostraria o documento de antes.
    if (result === null || text.value !== value) {
        return null;
    }

    busy.value = false;
    outcome.value = result;

    if (result.ok) {
        collapsed.value = new Set(result.collapsed);
    }

    return result;
}

const analyzeSoon = useDebounceFn(() => {
    void runAnalysis();
}, DEBOUNCE_MS);

watch(text, () => {
    if (intake === 'skip') {
        intake = 'debounced';

        return;
    }

    if (intake === 'now') {
        intake = 'debounced';
        void runAnalysis();

        return;
    }

    if (isEmpty.value) {
        resetDocument();

        return;
    }

    analyzeSoon();
});

function resetDocument(): void {
    outcome.value = null;
    collapsed.value = new Set();
    busy.value = false;
    tooLarge.value = false;
    inputBytes.value = 0;
    query.value = '';
    feedback.value = null;
    fileError.value = null;
}

/** Troca o documento inteiro: e o caminho de colar, abrir, exemplo e limpar. */
function applyText(value: string): void {
    fileError.value = null;
    feedback.value = null;
    query.value = '';

    // Sem mudanca de texto nao ha `watch`: deixar o `intake` armado faria a
    // proxima tecla digitada ser analisada fora do intervalo.
    if (text.value === value) {
        return;
    }

    intake = 'now';
    text.value = value;
}

/*
| A arvore
*/

const searchResult = computed(() => searchNodes(nodes.value, query.value));
const matches = computed(() => new Set(searchResult.value.matches));
const hasQuery = computed(() => query.value.trim() !== '');

const searchMessage = computed(() => {
    if (!hasQuery.value) {
        return null;
    }

    const { matches: found, truncated } = searchResult.value;

    if (found.length === 0) {
        return t('tools.json-viewer.search.none');
    }

    return truncated
        ? t('tools.json-viewer.search.truncated', { count: found.length })
        : t('tools.json-viewer.search.results', { count: found.length });
});

/*
| Busca que casa dentro de um container fechado nao mostraria nada: os pais dos
| resultados abrem quando a busca muda. Quem pesquisou abre; fechar de novo e um
| clique no proprio no.
*/
watch(query, () => {
    const { ancestors } = searchResult.value;

    if (ancestors.size === 0) {
        return;
    }

    const next = new Set(collapsed.value);
    let changed = false;

    for (const index of ancestors) {
        if (next.delete(index)) {
            changed = true;
        }
    }

    if (changed) {
        collapsed.value = next;
    }
});

function toggle(index: number): void {
    const next = new Set(collapsed.value);

    if (!next.delete(index)) {
        next.add(index);
    }

    collapsed.value = next;
}

function expandAll(): void {
    collapsed.value = new Set();
}

/** Recolher tudo deixa a raiz aberta: fechada, nao sobraria nada na tela. */
function collapseAll(): void {
    collapsed.value = collapseFrom(nodes.value, INITIAL_COLLAPSE_DEPTH);
}

/*
| Os recados da tela
*/

const fileErrorMessage = computed(() => {
    const current = fileError.value;

    if (current === null) {
        return null;
    }

    if (current.kind === 'file_too_large') {
        return t('tools.ui.states.file_too_large', {
            size: formatBytes(current.size, locale.value),
            limit: formatBytes(props.maxInputBytes, locale.value),
        });
    }

    return t(
        current.kind === 'file_type'
            ? 'tools.ui.states.file_type'
            : 'tools.ui.states.file_unreadable',
    );
});

const feedbackMessage = computed(() => {
    switch (feedback.value) {
        case 'copied':
            return t('tools.ui.actions.copied');
        case 'path':
            return t('tools.json-viewer.tree.copied', { path: feedbackPath.value });
        case 'manual':
            return t('tools.ui.actions.copy_manual');
        default:
            return null;
    }
});

const statsMessage = computed(() => {
    const current = success.value;

    if (current === null) {
        return null;
    }

    return [
        t('tools.json-viewer.stats.keys', { count: current.stats.keys }),
        t('tools.json-viewer.stats.items', { count: current.stats.items }),
        // O tamanho do documento encostado no limite: e o que faz o numero do
        // rodape significar alguma coisa.
        `${formatBytes(current.stats.bytes, locale.value)} ${t('tools.ui.limits.of', {
            limit: formatBytes(props.maxInputBytes, locale.value),
        })}`,
    ].join(' · ');
});

/**
 * O recado do rodape: o erro do arquivo manda, depois o que acabou de
 * acontecer, e por ultimo o tamanho do documento.
 */
const status = computed<{ text: string; error: boolean } | null>(() => {
    const fileMessage = fileErrorMessage.value;

    if (fileMessage !== null) {
        return { text: fileMessage, error: true };
    }

    const feedbackText = feedbackMessage.value;

    if (feedbackText !== null) {
        return { text: feedbackText, error: false };
    }

    const stats = statsMessage.value;

    return stats === null ? null : { text: stats, error: false };
});

/** O recado do painel de saida, quando nao ha arvore para desenhar. */
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

    return busy.value ? { text: t('tools.ui.states.parsing'), error: false } : null;
});

let feedbackTimer = 0;

function showFeedback(kind: 'copied' | 'path' | 'manual', path = ''): void {
    feedback.value = kind;
    feedbackPath.value = path;

    window.clearTimeout(feedbackTimer);
    feedbackTimer = window.setTimeout(() => {
        feedback.value = null;
    }, FEEDBACK_MS);
}

/*
| As acoes
*/

async function rewrite(options: Partial<AnalyzeOptions>): Promise<void> {
    const result = await runAnalysis(options);

    if (result === null || !result.ok || result.output === null) {
        return;
    }

    // Documento ja nesta forma: o texto nao muda, entao nao ha analise a pular
    // - e o `intake` nao pode ficar armado para a proxima tecla.
    if (result.output === text.value) {
        return;
    }

    intake = 'skip';
    text.value = result.output;
}

function format(): void {
    void rewrite({ output: 'pretty' });
}

function minify(): void {
    void rewrite({ output: 'minify' });
}

/** Ordenar reescreve o documento: a arvore e o texto saem do mesmo valor. */
function sortKeys(): void {
    void rewrite({ output: 'pretty', sortKeys: true });
}

function loadExample(): void {
    applyText(sample);
}

function clear(): void {
    applyText('');
}

async function copyValue(value: string, path = ''): Promise<void> {
    try {
        // Fora de contexto seguro o navegador nem expoe a area de transferencia.
        if (navigator.clipboard === undefined) {
            throw new Error('sem area de transferencia');
        }

        await navigator.clipboard.writeText(value);
        showFeedback(path === '' ? 'copied' : 'path', path);
    } catch {
        // Sem permissao (ou sem API): a saida e copiar na mao, e o recado diz.
        showFeedback('manual');
    }
}

function copyDocument(): void {
    void copyValue(text.value);
}

function copyPath(index: number): void {
    const path = nodePath(nodes.value, index);

    void copyValue(path, path);
}

function download(): void {
    downloadText(`${t('tools.json-viewer.download_name')}.json`, text.value);
}

function focusSearch(): void {
    searchBox.value?.querySelector('input')?.focus();
}

/*
| Arquivo por clique e por arrasto
*/

/** O que a caixa de arquivo aceita alem da extensao. */
const ACCEPTED_TYPES = ['application/json', 'text/plain'];
const FILE_EXTENSION = /\.(?:json|txt)$/i;

type FileErrorKind = 'file_too_large' | 'file_type' | 'file_unreadable';

function readAsText(file: File): Promise<string> {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();

        reader.onload = () => resolve(String(reader.result ?? ''));
        // Texto interno: nao vai para a tela, so decide o recado traduzido.
        reader.onerror = () => reject(new Error('leitura falhou'));
        reader.readAsText(file);
    });
}

async function loadFile(file: File): Promise<void> {
    fileError.value = null;

    if (file.size > props.maxInputBytes) {
        fileError.value = { kind: 'file_too_large', size: file.size };

        return;
    }

    if (!FILE_EXTENSION.test(file.name) && !ACCEPTED_TYPES.includes(file.type)) {
        fileError.value = { kind: 'file_type', size: file.size };

        return;
    }

    try {
        applyText(await readAsText(file));
    } catch {
        fileError.value = { kind: 'file_unreadable', size: file.size };
    }
}

function pickFile(): void {
    fileInput.value?.click();
}

function onFileChosen(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    // Zerar o campo e o que permite escolher o mesmo arquivo duas vezes
    // seguidas: com ele preenchido, o segundo `change` nao acontece.
    input.value = '';

    if (file !== undefined) {
        void loadFile(file);
    }
}

function onDrop(event: DragEvent): void {
    dragging.value = false;

    const file = event.dataTransfer?.files?.[0];

    if (file !== undefined) {
        void loadFile(file);
    }
}

onBeforeUnmount(() => {
    window.clearTimeout(feedbackTimer);
    client.dispose();
});
</script>

<template>
    <ToolShell split-key="json-viewer">
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
                    @click="pickFile"
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
                        :disabled="isEmpty"
                        @click="copyDocument"
                    >
                        <template #icon>
                            <Copy class="size-3.5" />
                        </template>
                    </ToolButton>

                    <ToolButton
                        :label="t('tools.ui.actions.download')"
                        :disabled="isEmpty"
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
                :label="t('tools.json-viewer.format')"
                :disabled="!canRewrite"
                @click="format"
            >
                <template #icon>
                    <WandSparkles class="size-3.5" />
                </template>
            </ToolButton>

            <ToolButton
                :label="t('tools.json-viewer.minify')"
                :disabled="!canRewrite"
                @click="minify"
            >
                <template #icon>
                    <Minimize2 class="size-3.5" />
                </template>
            </ToolButton>

            <ToolButton
                :label="t('tools.json-viewer.sort_keys')"
                :disabled="!canRewrite"
                @click="sortKeys"
            >
                <template #icon>
                    <ArrowDownAZ class="size-3.5" />
                </template>
            </ToolButton>
        </template>

        <template #input>
            <div
                class="relative flex min-h-0 flex-1 flex-col"
                data-test="json-viewer-dropzone"
                @dragover.prevent="dragging = true"
                @dragleave="dragging = false"
                @drop.prevent="onDrop"
            >
                <JsonEditor
                    v-model="text"
                    class="absolute inset-0"
                    :placeholder="t('tools.ui.states.empty')"
                    :error="editorError"
                    @find="focusSearch"
                />

                <div
                    v-if="dragging"
                    class="pointer-events-none absolute inset-0 z-10 flex flex-col items-center justify-center gap-1 border-2 border-dashed border-ring bg-surface/90 p-4 text-center"
                    data-test="json-viewer-dropzone-overlay"
                >
                    <p class="text-sm font-medium">{{ t('tools.ui.drop') }}</p>
                    <p class="text-xs text-subtle">
                        {{ t('tools.ui.states.drop_hint') }}
                    </p>
                </div>

                <input
                    ref="fileInput"
                    type="file"
                    accept=".json,.txt,application/json,text/plain"
                    class="sr-only"
                    data-test="json-viewer-file"
                    @change="onFileChosen"
                />
            </div>
        </template>

        <template #output-header>
            <div ref="searchBox" class="relative">
                <Search
                    class="pointer-events-none absolute top-1/2 left-2 size-3.5 -translate-y-1/2 text-subtle"
                    aria-hidden="true"
                />
                <Input
                    v-model="query"
                    type="search"
                    class="h-7 w-40 pl-7 text-xs"
                    :placeholder="t('tools.json-viewer.search.placeholder')"
                    :aria-label="t('tools.json-viewer.search.label')"
                    data-test="json-viewer-search"
                />
            </div>

            <span
                v-if="searchMessage !== null"
                class="text-xs text-subtle"
                data-test="json-viewer-search-count"
            >
                {{ searchMessage }}
            </span>

            <ToolButton
                v-if="hasQuery"
                :label="t('tools.json-viewer.search.clear')"
                variant="ghost"
                @click="query = ''"
            >
                <template #icon>
                    <X class="size-3.5" />
                </template>
            </ToolButton>

            <ToolButton
                :label="t('tools.json-viewer.expand_all')"
                :disabled="!hasTree"
                variant="ghost"
                @click="expandAll"
            >
                <template #icon>
                    <ChevronsUpDown class="size-3.5" />
                </template>
            </ToolButton>

            <ToolButton
                :label="t('tools.json-viewer.collapse_all')"
                :disabled="!hasTree"
                variant="ghost"
                @click="collapseAll"
            >
                <template #icon>
                    <ChevronsDownUp class="size-3.5" />
                </template>
            </ToolButton>
        </template>

        <template #output>
            <div class="relative min-h-0 flex-1">
                <JsonTree
                    v-if="hasTree"
                    class="absolute inset-0"
                    :nodes="nodes"
                    :collapsed="collapsed"
                    :matches="matches"
                    @toggle="toggle"
                    @copy="copyPath"
                />

                <p
                    v-else-if="state !== null"
                    role="status"
                    class="flex h-full flex-col items-center justify-center gap-2 p-6 text-center text-sm"
                    :class="state.error ? 'text-destructive' : 'text-subtle'"
                    data-test="json-viewer-state"
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
                data-test="json-viewer-status"
            >
                {{ status.text }}
            </p>
        </template>

        <template #shortcuts>
            <span class="flex items-center gap-1.5">
                <kbd class="rounded border bg-surface-2 px-1 font-mono">
                    {{ commandKey }} F
                </kbd>
                {{ t('tools.json-viewer.shortcuts.search') }}
            </span>
            <span class="flex items-center gap-1.5">
                <kbd class="rounded border bg-surface-2 px-1 font-mono">
                    {{ commandKey }} Z
                </kbd>
                {{ t('tools.json-viewer.shortcuts.undo') }}
            </span>
            <span class="flex items-center gap-1.5">
                <kbd class="rounded border bg-surface-2 px-1 font-mono">
                    {{ commandKey }} V
                </kbd>
                {{ t('tools.json-viewer.shortcuts.paste') }}
            </span>
        </template>
    </ToolShell>
</template>
