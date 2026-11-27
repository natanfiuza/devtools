<script setup lang="ts">
import {
    Copy,
    Download,
    Eraser,
    FolderOpen,
    Sparkles,
    TriangleAlert,
} from '@lucide/vue';
import { computed, ref, useTemplateRef } from 'vue';
import { useI18n } from 'vue-i18n';
import CodeEditor from '@/components/tool/CodeEditor.vue';
import FileDropzone from '@/components/tool/FileDropzone.vue';
import ToolActionBar from '@/components/tool/ToolActionBar.vue';
import ToolButton from '@/components/tool/ToolButton.vue';
import ToolShell from '@/components/tool/ToolShell.vue';
import { useCopyFeedback } from '@/composables/useCopyFeedback';
import { useFileError } from '@/composables/useFileError';
import { downloadText } from '@/lib/download';
import { acceptAttribute, readTextFile } from '@/lib/file';
import { ENV_FILE_RULES } from './lib/files';
import { maskEnv } from './lib/mask';
import { parseEnv } from './lib/parse';
import sample from './sample.txt?raw';

/*
 * `.env` para `.env.example`.
 *
 * O arquivo tem comentarios, grupos e chaves que **nao** sao segredo
 * (`APP_NAME`, `MAIL_HOST`): apagar tudo nao serve, e um exemplo sem chave nao
 * ensina o que configurar. Entao a tool troca so o valor das chaves sensiveis
 * - pelo nome, por segmento, ou por URL com credencial - e devolve o resto
 * exatamente como estava.
 *
 * A leitura e a mascara sao lineares e sincronas; o texto tem milhares de
 * linhas no pior caso, e nao ha worker.
 */

const props = defineProps<{
    /** Teto de entrada em bytes, vindo de `config/tools.php` pelo controller. */
    maxInputBytes: number;
}>();

const { t } = useI18n();

/** O texto do painel de entrada - a fonte da verdade da tela. */
const source = ref('');

const copyFeedback = useCopyFeedback();
const fileError = useFileError(
    () => props.maxInputBytes,
    () => t('tools.env-example.file_types'),
);

const dropzone = useTemplateRef<InstanceType<typeof FileDropzone>>('dropzone');

const isEmpty = computed(() => source.value.trim() === '');
const result = computed(() => maskEnv(parseEnv(source.value)));
const output = computed(() => result.value.output);
const hasOutput = computed(() => output.value !== '');
const masked = computed(() => result.value.masked);
const invalid = computed(() => result.value.invalid);

const status = computed<string>(() => {
    const fileMessage = fileError.message.value;

    if (fileMessage !== null) {
        return fileMessage;
    }

    const feedbackText = copyFeedback.message.value;

    if (feedbackText !== null) {
        return feedbackText;
    }

    if (isEmpty.value) {
        return t('tools.ui.states.empty');
    }

    return t('tools.env-example.status.summary', {
        masked: masked.value.length,
        invalid: invalid.value.length,
    });
});

/*
| As acoes
*/

function loadExample(): void {
    applyText(sample.trim());
}

function clear(): void {
    applyText('');
}

function copyOutput(): void {
    void copyFeedback.copy(output.value);
}

function downloadOutput(): void {
    if (output.value !== '') {
        downloadText('.env.example', output.value, 'text/plain');
    }
}

/** Troca o documento inteiro: e o caminho de colar, abrir, exemplo e limpar. */
function applyText(next: string): void {
    fileError.clear();
    copyFeedback.clear();
    source.value = next;
}

/*
| Arquivo
*/

/** O arquivo escolhido ou solto entra pelo mesmo caminho do texto colado. */
async function onFile(file: File): Promise<void> {
    fileError.clear();

    const read = await readTextFile(file, {
        ...ENV_FILE_RULES,
        maxBytes: props.maxInputBytes,
    });

    if (!read.ok) {
        fileError.fail(read.kind, read.size);

        return;
    }

    applyText(read.text);
}

function openFile(): void {
    dropzone.value?.pick();
}
</script>

<template>
    <ToolShell
        split-key="env-example"
        :pane-labels="{
            input: t('tools.env-example.panes.source'),
            output: t('tools.env-example.panes.example'),
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
                        @click="copyOutput"
                    >
                        <template #icon>
                            <Copy class="size-3.5" />
                        </template>
                    </ToolButton>

                    <ToolButton
                        :label="t('tools.ui.actions.download')"
                        :disabled="!hasOutput"
                        @click="downloadOutput"
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
                :accept="acceptAttribute(ENV_FILE_RULES)"
                testid="env-example"
                @file="onFile"
            >
                <CodeEditor
                    v-model="source"
                    testid="env-editor"
                    class="absolute inset-0"
                    :placeholder="t('tools.ui.states.empty')"
                />
            </FileDropzone>
        </template>

        <template #output>
            <div class="flex min-h-0 flex-1 flex-col">
                <p
                    v-if="isEmpty"
                    role="status"
                    class="flex h-full flex-col items-center justify-center gap-2 p-6 text-center text-sm text-subtle"
                    data-test="env-state"
                >
                    {{ t('tools.env-example.states.empty') }}
                </p>

                <template v-else>
                    <div
                        v-if="masked.length > 0 || invalid.length > 0"
                        class="bg-surface-muted max-h-40 overflow-auto border-b border-border px-3 py-2 text-xs"
                        data-test="env-notices"
                    >
                        <div v-if="masked.length > 0" data-test="env-masked">
                            <p class="flex items-center gap-1.5 font-medium">
                                <TriangleAlert
                                    class="size-3.5 shrink-0 text-subtle"
                                    aria-hidden="true"
                                />
                                {{ t('tools.env-example.notices.masked') }}
                            </p>

                            <ul class="mt-1 space-y-0.5">
                                <li
                                    v-for="item in masked"
                                    :key="item.line"
                                    class="font-mono"
                                    data-test="env-masked-key"
                                >
                                    {{
                                        t(
                                            'tools.env-example.notices.masked_key',
                                            {
                                                key: item.key,
                                                line: item.line,
                                            },
                                        )
                                    }}
                                </li>
                            </ul>
                        </div>

                        <div
                            v-if="invalid.length > 0"
                            class="mt-2"
                            data-test="env-invalid"
                        >
                            <p class="font-medium">
                                {{ t('tools.env-example.notices.invalid') }}
                            </p>

                            <ul class="mt-1 space-y-0.5">
                                <li
                                    v-for="item in invalid"
                                    :key="item.line"
                                    class="font-mono"
                                    data-test="env-invalid-line"
                                >
                                    {{
                                        t(
                                            'tools.env-example.notices.invalid_line',
                                            { line: item.line },
                                        )
                                    }}
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div class="min-h-0 flex-1 overflow-auto">
                        <pre
                            class="px-3 py-3 font-mono text-sm whitespace-pre"
                            data-test="env-output"
                            >{{ output }}</pre>
                    </div>
                </template>
            </div>
        </template>

        <template #status>
            <p class="text-xs text-subtle" data-test="env-status">
                {{ status }}
            </p>
        </template>
    </ToolShell>
</template>
