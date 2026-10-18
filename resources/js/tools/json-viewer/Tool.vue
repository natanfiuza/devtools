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
import { Input } from '@/components/ui/input';
import { useCopyFeedback } from '@/composables/useCopyFeedback';
import { useFileError } from '@/composables/useFileError';
import { formatBytes } from '@/lib/bytes';
import { downloadText } from '@/lib/download';
import type { EditorError } from '@/lib/editor/createEditor';
import { acceptAttribute, readTextFile, type FileRules } from '@/lib/file';
import { byteLength, hasBom } from '@/lib/json/parse';
import JsonTree from './JsonTree.vue';
import {
    INITIAL_COLLAPSE_DEPTH,
    type AnalyzeOptions,
    type AnalyzeOutcome,
    type AnalyzeSuccess,
} from './lib/analyze';
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
    /** Acima de quantas linhas a arvore passa a renderizar so a janela. */
    virtualizeFrom: number;
}>();

const { t, locale } = useI18n();

const client = createAnalyzeClient();

/** Espera entre a ultima tecla e a analise: digitar nao analisa por tecla. */
const DEBOUNCE_MS = 200;

/** O que a caixa de arquivo aceita; o mesmo vale para o `accept` do seletor. */
const FILE_RULES: Pick<FileRules, 'extensions' | 'types'> = {
    extensions: ['.json', '.txt'],
    types: ['application/json', 'text/plain'],
};

/** O texto do painel de entrada - a fonte da verdade da tela. */
const text = ref('');
/** O resultado da analise. `shallowRef`: a arvore nao precisa ser reativa. */
const outcome = shallowRef<AnalyzeOutcome | null>(null);
/** Containers fechados, por indice na lista achatada. */
const collapsed = shallowRef<Set<number>>(new Set());
const query = ref('');
const busy = ref(false);
const tooLarge = ref(false);
const inputBytes = ref(0);

/** O recado de copia, e a recusa de arquivo - os dois caem na barra de baixo. */
const copyFeedback = useCopyFeedback();
const fileError = useFileError(
    () => props.maxInputBytes,
    () => t('tools.json-viewer.file_types'),
);

const dropzone = useTemplateRef<InstanceType<typeof FileDropzone>>('dropzone');
const searchBox = ref<HTMLElement | null>(null);

/** A tecla de comando muda com o teclado, nao com o idioma. */
const commandKey =
    typeof navigator !== 'undefined' &&
    /Mac|iPhone|iPad/.test(navigator.userAgent)
        ? '⌘'
        : 'Ctrl';

/*
| A analise
*/

const success = computed(() => {
    const current = outcome.value;

    return current !== null && current.ok ? current : null;
});

/**
 * A ultima analise que deu certo.
 *
 * E o que sustenta a promessa do SPEC (secao 5): enquanto o texto esta
 * invalido - ou grande demais para ser analisado -, a arvore da ultima versao
 * valida **continua na tela**, marcada como defasada. Quem esta corrigindo o
 * erro nao perde a referencia do que o documento era.
 */
const lastValid = shallowRef<AnalyzeSuccess | null>(null);

const isEmpty = computed(() => text.value.trim() === '');

/** Nao ha analise valida para o texto atual: a arvore na tela e a anterior. */
const stale = computed(
    () => success.value === null && !isEmpty.value && lastValid.value !== null,
);

const nodes = computed<JsonNode[]>(() => {
    if (success.value !== null) {
        return success.value.nodes;
    }

    const last = lastValid.value;

    return stale.value && last !== null ? last.nodes : [];
});

const hasTree = computed(() => nodes.value.length > 0);
const canRewrite = computed(() => success.value !== null);

/** O erro do documento: linha e coluna quando o motor informou. */
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

async function runAnalysis(
    extra: Partial<AnalyzeOptions> = {},
): Promise<AnalyzeOutcome | null> {
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
        lastValid.value = result;
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
    // Documento vazio nao tem "ultima versao valida": a arvore sai da tela.
    lastValid.value = null;
    collapsed.value = new Set();
    busy.value = false;
    tooLarge.value = false;
    inputBytes.value = 0;
    query.value = '';
    copyFeedback.clear();
    fileError.clear();
}

/** Troca o documento inteiro: e o caminho de colar, abrir, exemplo e limpar. */
function applyText(value: string): void {
    fileError.clear();
    copyFeedback.clear();
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
        `${formatBytes(current.stats.bytes, locale.value)} ${t(
            'tools.ui.limits.of',
            {
                limit: formatBytes(props.maxInputBytes, locale.value),
            },
        )}`,
    ].join(' · ');
});

/**
 * O recado do painel de saida, quando nao ha arvore para desenhar.
 *
 * Com a arvore defasada na tela (`stale`) ele tambem alimenta o rodape: o erro
 * que interrompeu a analise continua visivel, ao lado da arvore antiga.
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
 * acontecer, depois o erro que deixou a arvore defasada, e por ultimo o
 * tamanho do documento.
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

    const stats = statsMessage.value;

    return stats === null ? null : { text: stats, error: false };
});

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

function copyDocument(): void {
    void copyFeedback.copy(text.value);
}

/** O caminho copiado se anuncia: o recado mostra qual foi, e nao so "copiado". */
function copyPath(index: number): void {
    const path = nodePath(nodes.value, index);

    void copyFeedback.copy(path, t('tools.json-viewer.tree.copied', { path }));
}

function download(): void {
    downloadText(`${t('tools.json-viewer.download_name')}.json`, text.value);
}

function focusSearch(): void {
    searchBox.value?.querySelector('input')?.focus();
}

/**
 * Esc limpa a busca da arvore.
 *
 * Escuta na janela, e nao no campo: quem esta com o foco no editor (que acabou
 * de receber o `Mod-f`) tambem espera que o Esc desfaca a busca - e e isso que a
 * faixa de atalhos anuncia. Sem busca ativa o Esc segue com o resto da pagina.
 */
function onKeydown(event: KeyboardEvent): void {
    if (event.key !== 'Escape' || !hasQuery.value) {
        return;
    }

    query.value = '';
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

onMounted(() => {
    window.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown);
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
                :shortcut="`${commandKey} Enter`"
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
            <FileDropzone
                ref="dropzone"
                :accept="acceptAttribute(FILE_RULES)"
                testid="json-viewer"
                @file="onFile"
            >
                <CodeEditor
                    v-model="text"
                    testid="json-editor"
                    class="absolute inset-0"
                    :placeholder="t('tools.ui.states.empty')"
                    :error="editorError"
                    @find="focusSearch"
                    @format="format"
                />
            </FileDropzone>
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
                    class="h-7 w-40 pr-9 pl-7 text-xs"
                    :placeholder="t('tools.json-viewer.search.placeholder')"
                    :aria-label="t('tools.json-viewer.search.label')"
                    data-test="json-viewer-search"
                />
                <!-- O `Esc` que a faixa de atalhos anuncia, colado no campo. -->
                <kbd
                    class="pointer-events-none absolute top-1/2 right-1.5 hidden -translate-y-1/2 rounded border bg-surface-2 px-1 font-mono text-[10px] text-subtle sm:inline"
                    data-test="json-viewer-search-shortcut"
                >
                    Esc
                </kbd>
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
            <!--
                A arvore na tela e da ultima versao valida, e nao do texto que
                esta no editor: o aviso diz isso, e a arvore sai em opacidade
                reduzida (SPEC secao 5).
            -->
            <p
                v-if="stale"
                role="status"
                class="flex items-center justify-center gap-1.5 border-b border-destructive/30 bg-destructive/5 px-3 py-1.5 text-xs text-destructive"
                data-test="json-viewer-stale"
            >
                <TriangleAlert class="size-3.5" aria-hidden="true" />
                {{ t('tools.ui.states.stale_output') }}
            </p>

            <div class="relative min-h-0 flex-1">
                <JsonTree
                    v-if="hasTree"
                    class="absolute inset-0 transition-opacity"
                    :class="stale ? 'opacity-50' : ''"
                    :nodes="nodes"
                    :collapsed="collapsed"
                    :matches="matches"
                    :virtualize-from="virtualizeFrom"
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
                    {{ commandKey }} Enter
                </kbd>
                {{ t('tools.json-viewer.shortcuts.format') }}
            </span>
            <span class="flex items-center gap-1.5">
                <kbd class="rounded border bg-surface-2 px-1 font-mono">
                    {{ commandKey }} F
                </kbd>
                {{ t('tools.json-viewer.shortcuts.search') }}
            </span>
            <span class="flex items-center gap-1.5">
                <kbd class="rounded border bg-surface-2 px-1 font-mono">
                    Esc
                </kbd>
                {{ t('tools.json-viewer.shortcuts.clear') }}
            </span>
        </template>
    </ToolShell>
</template>
