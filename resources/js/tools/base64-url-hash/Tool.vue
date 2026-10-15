<script setup lang="ts">
import { Copy, Eraser, FolderOpen, Sparkles, TriangleAlert } from '@lucide/vue';
import { computed, ref, shallowRef, useTemplateRef, watch } from 'vue';
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
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useCopyFeedback } from '@/composables/useCopyFeedback';
import { useFileError } from '@/composables/useFileError';
import { formatBytes } from '@/lib/bytes';
import { acceptAttribute, readTextFile, type FileRules } from '@/lib/file';
import { byteLength } from '@/lib/json/parse';
import {
    DIRECTIONS,
    digestRows,
    MODES,
    rowsFor,
    type Direction,
    type Mode,
    type RowLabel,
    type ValueRow,
} from './lib/convert';
import { hashAll, type DigestLine } from './lib/hash';
import sample from './sample.txt?raw';

/*
 * Base64, URL e Hash.
 *
 * Uma entrada, tres leituras: o mesmo texto vira Base64 (padrao e URL-safe),
 * vira URL codificada (componente e formulario) ou vira quatro hashes. As tres
 * caberiam lado a lado e nao cabem - cada uma rende de uma a quatro linhas com
 * valor longo -, entao elas sao **abas** do painel de saida, e nao secoes.
 *
 * A **direcao** (codificar / decodificar) fica no cabecalho da **entrada**,
 * porque e ela que muda o que o texto de cima significa: o mesmo "YQ==" e a
 * pergunta "o que isto vira?" ou a resposta "isto e a codificacao de 'a'".
 * Ela some na aba Hash: hash nao tem volta.
 *
 * A conta e sincrona e por aba - uma passada linear sobre o texto, sem worker.
 * Medido: 100 KB saem em menos de 60 ms por aba, e o teto de entrada (5 MB) e
 * um caso que a pessoa cola uma vez, nao um que se digita. A aba do Hash e
 * assincrona porque os tres SHAs sao do `crypto.subtle`, e nao porque sejam
 * caros.
 */

const props = defineProps<{
    /** Limite de entrada em bytes, vindo de `config/tools.php` pelo controller. */
    maxInputBytes: number;
}>();

const { t, locale } = useI18n();

/** O que a caixa de arquivo aceita; o mesmo vale para o `accept` do seletor. */
const FILE_RULES: Pick<FileRules, 'extensions' | 'types'> = {
    extensions: ['.txt'],
    types: ['text/plain'],
};

/** O texto do painel de entrada - a fonte da verdade da tela. */
const text = ref('');

/** A aba aberta e a direcao escolhida; as duas comecam no padrao. */
const mode = ref<Mode>('base64');
const direction = ref<Direction>('encode');

/** Os quatro digests da ultima conta da aba Hash. */
const digests = shallowRef<DigestLine[]>([]);

const copyFeedback = useCopyFeedback();
const fileError = useFileError(
    () => props.maxInputBytes,
    () => t('tools.base64-url-hash.file_types'),
);

const dropzone = useTemplateRef<InstanceType<typeof FileDropzone>>('dropzone');

/*
| A conta
*/

const isEmpty = computed(() => text.value.trim() === '');
const inputBytes = computed(() => byteLength(text.value));
const tooLarge = computed(() => inputBytes.value > props.maxInputBytes);

/**
 * A aba Hash so tem o que fazer com texto que cabe na tela.
 *
 * Enquanto ela nao estiver valendo, os digests na tela sao de outro texto - e
 * um sha antigo ao lado de um texto novo e pior do que nenhum.
 */
const hashActive = computed(() => mode.value === 'hash' && !isEmpty.value && !tooLarge.value);

async function runHashes(): Promise<void> {
    const value = text.value;
    const result = await hashAll(value);

    // Superado: o texto ja mudou enquanto os digests eram calculados.
    if (text.value !== value) {
        return;
    }

    digests.value = result;
}

watch([text, mode], () => {
    if (hashActive.value) {
        void runHashes();

        return;
    }

    // Fora da aba Hash nao ha digest para mostrar - nem antigo para deixar.
    digests.value = [];
});

/**
 * As linhas da aba aberta.
 *
 * O vazio e o texto acima do teto vem primeiro: nos dois casos nao ha o que
 * converter, e a leitura de `rowsFor` seria uma conta sobre texto que a tela
 * ja recusou. Na aba Hash a leitura e dos digests, e nao do texto: eles sao o
 * resultado assincrono, e nao uma funcao dele.
 */
const rawRows = computed<ValueRow[]>(() => {
    if (isEmpty.value || tooLarge.value) {
        return [];
    }

    if (mode.value === 'hash') {
        return digestRows(digests.value);
    }

    return rowsFor(text.value, mode.value, direction.value);
});

/** A linha como a tela a desenha: sem a uniao de resultado no meio do caminho. */
interface RowView {
    label: RowLabel;
    text: string;
    refused: boolean;
}

const rows = computed<RowView[]>(() =>
    rawRows.value.map((row) => ({
        label: row.label,
        text: row.result.ok ? row.result.value : '',
        refused: !row.result.ok,
    })),
);

/** Nenhuma linha da aba tem valor: a recusa e da entrada inteira. */
const allRefused = computed(() => rows.value.length > 0 && rows.value.every((row) => row.refused));

/**
 * Por que a linha ficou sem valor.
 *
 * Na decodificacao as duas variantes de URL recusam juntas - um `+` a mais nao
 * salva uma sequencia de escape quebrada -, entao a recusa e uma so para a aba,
 * e nao uma por linha. Na aba Hash o motivo nao esta no texto: esta no navegador
 * que nao expos a Web Crypto, e o aviso diz isso e diz o que sobra.
 */
const refusalMessage = computed(() =>
    mode.value === 'hash'
        ? t('tools.base64-url-hash.states.no_crypto')
        : t(`tools.base64-url-hash.states.invalid_${mode.value}`),
);

/** Uma linha so ficou sem valor: e o caso do SHA sem Web Crypto na pagina. */
const someRefused = computed(
    () => !allRefused.value && rows.value.some((row) => row.refused),
);

function labelOf(row: RowView): string {
    return t(`tools.base64-url-hash.rows.${row.label}`);
}

/*
| As acoes
*/

/** O que o botao Copiar da barra leva: a aba aberta, uma linha por valor. */
const copyText = computed(() =>
    rows.value
        .filter((row) => !row.refused && row.text !== '')
        .map((row) => `${labelOf(row)}: ${row.text}`)
        .join('\n'),
);

const hasOutput = computed(() => copyText.value !== '');

function loadExample(): void {
    applyText(sample);
}

function clear(): void {
    applyText('');
}

function copyAll(): void {
    void copyFeedback.copy(copyText.value);
}

function copyRow(row: RowView): void {
    void copyFeedback.copy(row.text, t('tools.base64-url-hash.row_copied', { label: labelOf(row) }));
}

/** Troca o documento inteiro: e o caminho de colar, abrir, exemplo e limpar. */
function applyText(next: string): void {
    fileError.clear();
    copyFeedback.clear();
    text.value = next;
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

const statsMessage = computed(() =>
    `${formatBytes(inputBytes.value, locale.value)} ${t('tools.ui.limits.of', {
        limit: formatBytes(props.maxInputBytes, locale.value),
    })}`,
);

/** O recado que ocupa o lugar das linhas, quando nao ha valor nenhum a mostrar. */
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

    return allRefused.value ? { text: refusalMessage.value, error: true } : null;
});

/**
 * O recado do rodape: o erro do arquivo manda, depois o que acabou de
 * acontecer, e por ultimo o tamanho do texto.
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

    return isEmpty.value ? state.value : { text: statsMessage.value, error: false };
});
</script>

<template>
    <ToolShell split-key="base64-url-hash">
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
                        :disabled="!hasOutput"
                        @click="copyAll"
                    >
                        <template #icon>
                            <Copy class="size-3.5" />
                        </template>
                    </ToolButton>
                </template>
            </ToolActionBar>
        </template>

        <!--
            A direcao fica no cabecalho da **entrada**: e ela que diz o que o
            texto de cima significa. Na aba Hash ela some - hash nao tem volta.
        -->
        <template #input-header>
            <Select v-if="mode !== 'hash'" v-model="direction">
                <SelectTrigger
                    class="h-7 w-36 text-xs"
                    :aria-label="t('tools.base64-url-hash.direction')"
                    data-test="base64-url-hash-direction"
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem v-for="item in DIRECTIONS" :key="item" :value="item">
                        {{ t(`tools.base64-url-hash.directions.${item}`) }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </template>

        <template #input>
            <FileDropzone
                ref="dropzone"
                :accept="acceptAttribute(FILE_RULES)"
                testid="base64-url-hash"
                @file="onFile"
            >
                <CodeEditor
                    v-model="text"
                    testid="base64-url-hash-editor"
                    class="absolute inset-0"
                    :placeholder="t('tools.ui.states.empty')"
                />
            </FileDropzone>
        </template>

        <template #output>
            <Tabs v-model="mode" class="flex min-h-0 flex-1 flex-col">
                <TabsList class="m-3 w-fit">
                    <TabsTrigger
                        v-for="item in MODES"
                        :key="item"
                        :value="item"
                        :data-test="`base64-url-hash-mode-${item}`"
                    >
                        {{ t(`tools.base64-url-hash.modes.${item}`) }}
                    </TabsTrigger>
                </TabsList>

                <!--
                    Uma aba so, com o conteudo trocando por dentro: as tres
                    desenham o mesmo par rotulo/valor, e escrever tres blocos
                    iguais seria escrever o mesmo componente tres vezes.
                -->
                <div class="min-h-0 flex-1 overflow-auto">
                    <p
                        v-if="state !== null"
                        role="status"
                        class="flex h-full flex-col items-center justify-center gap-2 p-6 text-center text-sm"
                        :class="state.error ? 'text-destructive' : 'text-subtle'"
                        data-test="base64-url-hash-state"
                    >
                        <TriangleAlert
                            v-if="state.error"
                            class="size-5"
                            aria-hidden="true"
                        />
                        {{ state.text }}
                    </p>

                    <template v-else>
                        <!--
                            Fora de contexto seguro os tres SHAs nao saem. O
                            aviso aparece **uma vez**, e o MD5 continua na lista
                            logo abaixo: ele e a conta da casa.
                        -->
                        <p
                            v-if="someRefused"
                            role="status"
                            class="flex items-center gap-1.5 border-b border-border bg-surface-muted px-3 py-1.5 text-xs text-subtle"
                            data-test="base64-url-hash-no-crypto"
                        >
                            <TriangleAlert class="size-3.5 shrink-0" aria-hidden="true" />
                            {{ refusalMessage }}
                        </p>

                        <ul>
                            <li
                                v-for="row in rows"
                                :key="row.label"
                                class="border-b border-border px-3 py-2 last:border-b-0"
                                :data-label="row.label"
                                data-test="base64-url-hash-row"
                            >
                                <div class="flex items-center gap-2">
                                    <h3
                                        class="text-[11px] font-medium tracking-wide text-subtle uppercase"
                                        data-test="base64-url-hash-row-label"
                                    >
                                        {{ labelOf(row) }}
                                    </h3>

                                    <button
                                        v-if="!row.refused && row.text !== ''"
                                        type="button"
                                        class="ml-auto rounded p-1 text-subtle transition-colors hover:bg-surface-muted hover:text-foreground"
                                        :aria-label="`${t('tools.ui.actions.copy')} — ${labelOf(row)}`"
                                        data-test="base64-url-hash-row-copy"
                                        @click="copyRow(row)"
                                    >
                                        <Copy class="size-3.5" aria-hidden="true" />
                                    </button>
                                </div>

                                <p
                                    v-if="row.refused"
                                    class="mt-1 font-mono text-sm text-subtle"
                                    data-test="base64-url-hash-refused"
                                >
                                    —
                                </p>

                                <p
                                    v-else
                                    class="mt-1 font-mono text-sm break-all whitespace-pre-wrap"
                                    data-test="base64-url-hash-value"
                                >{{ row.text }}</p>
                            </li>
                        </ul>
                    </template>
                </div>
            </Tabs>
        </template>

        <template #status>
            <p
                v-if="status !== null"
                class="text-xs"
                :class="status.error ? 'text-destructive' : 'text-subtle'"
                data-test="base64-url-hash-status"
            >
                {{ status.text }}
            </p>
        </template>
    </ToolShell>
</template>
