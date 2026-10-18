<script setup lang="ts">
import {
    Copy,
    Download,
    Eraser,
    FolderOpen,
    Sparkles,
    TriangleAlert,
} from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { computed, ref, shallowRef, useTemplateRef, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import CodeEditor from '@/components/tool/CodeEditor.vue';
import FileDropzone from '@/components/tool/FileDropzone.vue';
import ToolActionBar from '@/components/tool/ToolActionBar.vue';
import ToolButton from '@/components/tool/ToolButton.vue';
import ToolShell from '@/components/tool/ToolShell.vue';
import { Input } from '@/components/ui/input';
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
import {
    analyzeJson,
    byteLength,
    hasBom,
    type ParseResult,
} from '@/lib/json/parse';
import type { JsonValue } from '@/lib/json/types';
import {
    DEFAULT_ROOT_NAME,
    generate,
    TARGET_EXTENSIONS,
    TARGETS,
    type Target,
} from './lib/generate';
import sample from './sample.json?raw';

/*
 * O JSON to Code.
 *
 * JSON a esquerda, codigo a direita, e a inferencia inteira rodando aqui: nada
 * do que se cola sai do navegador, e o unico limite e o tamanho
 * (`maxInputBytes`), que vem do servidor porque limite nao mora no codigo.
 *
 * A analise e **sincrona**, e nao no worker como a do JSON Viewer: la o custo
 * esta em achatar a arvore e indexar a busca; aqui e um `JSON.parse` e uma
 * passada linear pelo valor, que o intervalo entre teclas cobre.
 *
 * O alvo e o nome da raiz nao disparam analise nova: a saida e uma funcao do
 * valor ja analisado, e trocar de linguagem so reescreve a direita.
 */

const props = defineProps<{
    /** Limite de entrada em bytes, vindo de `config/tools.php` pelo controller. */
    maxInputBytes: number;
}>();

const { t, locale } = useI18n();

/** Espera entre a ultima tecla e a analise: digitar nao analisa por tecla. */
const DEBOUNCE_MS = 200;

/** O que a caixa de arquivo aceita; o mesmo vale para o `accept` do seletor. */
const FILE_RULES: Pick<FileRules, 'extensions' | 'types'> = {
    extensions: ['.json', '.txt'],
    types: ['application/json', 'text/plain'],
};

/** O texto do painel de entrada - a fonte da verdade da tela. */
const text = ref('');
/** O resultado da analise. `shallowRef`: o valor nao precisa ser reativo. */
const outcome = shallowRef<ParseResult | null>(null);
const tooLarge = ref(false);
const inputBytes = ref(0);

/** O alvo e o nome da raiz: as duas entradas que nao vem do painel esquerdo. */
const target = ref<Target>('typescript');
const rootName = ref(DEFAULT_ROOT_NAME);

const copyFeedback = useCopyFeedback();
const fileError = useFileError(
    () => props.maxInputBytes,
    () => t('tools.json-to-code.file_types'),
);

const dropzone = useTemplateRef<InstanceType<typeof FileDropzone>>('dropzone');

/*
| A analise
*/

const isEmpty = computed(() => text.value.trim() === '');

/**
 * O valor da ultima entrada valida.
 *
 * E o que sustenta a promessa do SPEC (secao 5): enquanto o texto esta invalido
 * - ou grande demais -, a saida da ultima versao valida **continua na tela**,
 * marcada como defasada. Quem esta corrigindo o erro nao perde a referencia.
 */
const lastValue = shallowRef<JsonValue | null>(null);

/** Nao ha analise valida para o texto atual: a saida na tela e a anterior. */
const stale = computed(
    () =>
        !(outcome.value?.ok ?? false) &&
        !isEmpty.value &&
        lastValue.value !== null,
);

/** O valor que a saida descreve: o de agora, ou o da ultima entrada valida. */
const value = computed<JsonValue | null>(() => {
    const current = outcome.value;

    if (current !== null && current.ok) {
        return current.value;
    }

    return stale.value ? lastValue.value : null;
});

const generation = computed(() =>
    value.value === null
        ? null
        : generate(value.value, {
              target: target.value,
              rootName: rootName.value,
          }),
);

const code = computed(() => generation.value?.code ?? '');
const hasCode = computed(() => generation.value !== null);

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

function analyse(): void {
    const current = text.value;
    const bytes = byteLength(current);

    inputBytes.value = bytes;

    if (bytes > props.maxInputBytes) {
        tooLarge.value = true;
        outcome.value = null;

        return;
    }

    tooLarge.value = false;

    const result = analyzeJson(current);

    // O texto mudou enquanto a conta rodava: quem pinta a tela e o pedido mais
    // novo, senao a saida mostraria o documento de antes.
    if (text.value !== current) {
        return;
    }

    outcome.value = result;

    if (result.ok) {
        lastValue.value = result.value;
    }
}

const analyseSoon = useDebounceFn(analyse, DEBOUNCE_MS);

/**
 * Como o proximo texto entra: um botao ja tem o que mostrar (`now`) ou foi
 * digitacao (`debounced`).
 */
let intake: 'now' | 'debounced' = 'debounced';

watch(text, () => {
    if (intake === 'now') {
        intake = 'debounced';
        analyse();

        return;
    }

    if (isEmpty.value) {
        reset();

        return;
    }

    analyseSoon();
});

function reset(): void {
    outcome.value = null;
    // Documento vazio nao tem "ultima versao valida": a saida sai da tela.
    lastValue.value = null;
    tooLarge.value = false;
    inputBytes.value = 0;
    copyFeedback.clear();
    fileError.clear();
}

/** Troca o documento inteiro: e o caminho de colar, abrir, exemplo e limpar. */
function applyText(next: string): void {
    fileError.clear();
    copyFeedback.clear();

    // Sem mudanca de texto nao ha `watch`: deixar o `intake` armado faria a
    // proxima tecla digitada ser analisada fora do intervalo.
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
    const extension = TARGET_EXTENSIONS[target.value];

    downloadText(
        `${t('tools.json-to-code.download_name')}.${extension}`,
        code.value,
    );
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

const statsMessage = computed(() => {
    const current = generation.value;

    if (current === null) {
        return null;
    }

    return [
        t('tools.json-to-code.stats.types', { count: current.types }),
        // O tamanho do documento encostado no limite: e o que faz o numero do
        // rodape significar alguma coisa.
        `${formatBytes(inputBytes.value, locale.value)} ${t(
            'tools.ui.limits.of',
            {
                limit: formatBytes(props.maxInputBytes, locale.value),
            },
        )}`,
    ].join(' · ');
});

/**
 * O recado do painel de saida, quando nao ha codigo para mostrar.
 *
 * Com a saida defasada na tela (`stale`) ele tambem alimenta o rodape: o erro
 * que interrompeu a analise continua visivel, ao lado do codigo antigo.
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

    // Entrada valida que nao vira codigo: a raiz nao e um objeto com chaves.
    if (value.value !== null && generation.value === null) {
        return {
            text: t('tools.json-to-code.states.root_object'),
            error: false,
        };
    }

    return null;
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

    const stats = statsMessage.value;

    return stats === null ? null : { text: stats, error: false };
});
</script>

<template>
    <ToolShell split-key="json-to-code">
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
                testid="json-to-code"
                @file="onFile"
            >
                <CodeEditor
                    v-model="text"
                    testid="json-to-code-editor"
                    class="absolute inset-0"
                    :placeholder="t('tools.ui.states.empty')"
                    :error="editorError"
                />
            </FileDropzone>
        </template>

        <!--
            O alvo e o nome da raiz ficam no cabecalho da **saida**: sao as duas
            escolhas que mudam o que esta do lado de la, e ficam ao lado dele.
        -->
        <template #output-header>
            <Select v-model="target">
                <SelectTrigger
                    class="h-7 w-36 text-xs"
                    :aria-label="t('tools.json-to-code.target')"
                    data-test="json-to-code-target"
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="item in TARGETS"
                        :key="item"
                        :value="item"
                    >
                        {{ t(`tools.json-to-code.targets.${item}`) }}
                    </SelectItem>
                </SelectContent>
            </Select>

            <Input
                v-model="rootName"
                class="h-7 w-32 text-xs"
                :aria-label="t('tools.json-to-code.root_name')"
                :placeholder="DEFAULT_ROOT_NAME"
                data-test="json-to-code-root-name"
            />
        </template>

        <template #output>
            <!--
                O codigo na tela e da ultima versao valida, e nao do texto que
                esta no editor: o aviso diz isso, e o codigo sai em opacidade
                reduzida (SPEC secao 5).
            -->
            <p
                v-if="stale"
                role="status"
                class="flex items-center justify-center gap-1.5 border-b border-destructive/30 bg-destructive/5 px-3 py-1.5 text-xs text-destructive"
                data-test="json-to-code-stale"
            >
                <TriangleAlert class="size-3.5" aria-hidden="true" />
                {{ t('tools.ui.states.stale_output') }}
            </p>

            <div class="relative min-h-0 flex-1 overflow-auto">
                <pre
                    v-if="hasCode"
                    class="p-3 font-mono text-sm transition-opacity"
                    :class="stale ? 'opacity-50' : ''"
                    data-test="json-to-code-output"
                    >{{ code }}</pre>

                <p
                    v-else-if="state !== null"
                    role="status"
                    class="flex h-full flex-col items-center justify-center gap-2 p-6 text-center text-sm"
                    :class="state.error ? 'text-destructive' : 'text-subtle'"
                    data-test="json-to-code-state"
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
                data-test="json-to-code-status"
            >
                {{ status.text }}
            </p>
        </template>
    </ToolShell>
</template>
