<script setup lang="ts">
import { Copy, Eraser, Sparkles, TriangleAlert } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import CodeEditor from '@/components/tool/CodeEditor.vue';
import ToolActionBar from '@/components/tool/ToolActionBar.vue';
import ToolButton from '@/components/tool/ToolButton.vue';
import ToolShell from '@/components/tool/ToolShell.vue';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useCopyFeedback } from '@/composables/useCopyFeedback';
import { emitAll } from './lib/emit';
import { parseCurl } from './lib/parse';
import { CURL_TARGETS, type CurlTarget, type CurlWarning } from './types';
import sample from './sample.txt?raw';

/*
 * cURL para codigo.
 *
 * A entrada e o comando inteiro (varias linhas, continuacao por barra), entao
 * ela e um editor. A saida sao tres textos do mesmo pedido, entao ela e um par
 * de abas sobre um `<pre>`: codigo gerado nao se edita, se copia.
 *
 * O parser reduz o comando ao modelo da requisicao e **lista** o que ficou de
 * fora - uma opcao ignorada em silencio geraria codigo que finge fazer o que o
 * curl fazia. A credencial do `-u` tem aviso proprio: ela vai para o codigo.
 *
 * A conta e sincrona e linear: o comando tem uma linha (ou poucas), e a
 * tokenizacao percorre o texto uma vez. Nao ha worker.
 */

const { t } = useI18n();

/** O comando colado. */
const source = ref('');

/** A aba aberta: Laravel, fetch ou axios. */
const target = ref<CurlTarget>('laravel');

const copyFeedback = useCopyFeedback();

const parsed = computed(() => parseCurl(source.value));
const request = computed(() => (parsed.value.ok ? parsed.value.request : null));
const error = computed(() => (parsed.value.ok ? null : parsed.value.error));
const unsupported = computed(() =>
    parsed.value.ok ? parsed.value.unsupported : [],
);
const warnings = computed(() => (parsed.value.ok ? parsed.value.warnings : []));

const isEmpty = computed(() => source.value.trim() === '');
const outputs = computed(() =>
    request.value === null ? null : emitAll(request.value),
);
const output = computed(() => outputs.value?.[target.value] ?? '');
const hasOutput = computed(() => output.value !== '');

/*
| Os recados
*/

/** Uma linha por aviso; a credencial nomeia o usuario, nunca a senha. */
function warningText(warning: CurlWarning): string {
    switch (warning.code) {
        case 'credential_in_output':
            return t('tools.curl-to-code.warnings.credential_in_output', {
                user: warning.token,
            });
        case 'extra_url':
            return t('tools.curl-to-code.warnings.extra_url', {
                url: warning.token,
            });
        case 'header_without_colon':
            return t('tools.curl-to-code.warnings.header_without_colon', {
                token: warning.token,
            });
        case 'data_from_file':
            return t('tools.curl-to-code.warnings.data_from_file', {
                token: warning.token,
            });
    }
}

/** O que o banner da saida lista: o que ficou de fora e os avisos. */
const notices = computed<string[]>(() => {
    const lines: string[] = [];

    if (unsupported.value.length > 0) {
        lines.push(
            t('tools.curl-to-code.unsupported', {
                options: unsupported.value.join(', '),
            }),
        );
    }

    for (const warning of warnings.value) {
        lines.push(warningText(warning));
    }

    return lines;
});

const errorText = computed<string>(() => {
    const current = error.value;

    if (current === null) {
        return '';
    }

    if (current.code === 'shell') {
        return t('tools.curl-to-code.errors.shell', {
            detail: t(`tools.curl-to-code.shell_details.${current.detail}`),
        });
    }

    return t(`tools.curl-to-code.errors.${current.code}`, {
        option: current.option,
        token: current.option,
    });
});

/** O estado do painel de saida: vazio, ou o motivo da recusa. */
const stateText = computed(() =>
    isEmpty.value ? t('tools.curl-to-code.states.empty') : errorText.value,
);

const status = computed<string>(() => {
    const feedbackText = copyFeedback.message.value;

    if (feedbackText !== null) {
        return feedbackText;
    }

    if (isEmpty.value) {
        return t('tools.ui.states.empty');
    }

    if (request.value === null) {
        return errorText.value;
    }

    return t('tools.curl-to-code.status.ready', {
        method: request.value.method,
        url: request.value.url,
    });
});

/*
| As acoes
*/

function loadExample(): void {
    copyFeedback.clear();
    source.value = sample.trim();
}

function clear(): void {
    copyFeedback.clear();
    source.value = '';
}

function copyOutput(): void {
    void copyFeedback.copy(output.value);
}
</script>

<template>
    <ToolShell
        split-key="curl-to-code"
        :pane-labels="{
            input: t('tools.curl-to-code.panes.command'),
            output: t('tools.curl-to-code.panes.code'),
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
                </template>
            </ToolActionBar>
        </template>

        <template #input>
            <CodeEditor
                v-model="source"
                testid="curl-editor"
                class="min-h-0 flex-1"
                :placeholder="t('tools.ui.states.empty')"
            />
        </template>

        <template #output>
            <div class="flex min-h-0 flex-1 flex-col">
                <p
                    v-if="request === null"
                    role="status"
                    class="flex h-full flex-col items-center justify-center gap-2 p-6 text-center text-sm text-subtle"
                    :data-state="isEmpty ? 'empty' : 'invalid'"
                    data-test="curl-state"
                >
                    {{ stateText }}
                </p>

                <template v-else>
                    <div
                        v-if="notices.length > 0"
                        class="bg-surface-muted border-b border-border px-3 py-2 text-xs"
                        data-test="curl-notices"
                    >
                        <p
                            v-for="(notice, index) in notices"
                            :key="index"
                            class="flex items-start gap-1.5 py-0.5"
                            :data-test="`curl-notice-${index}`"
                        >
                            <TriangleAlert
                                class="mt-0.5 size-3.5 shrink-0 text-subtle"
                                aria-hidden="true"
                            />
                            {{ notice }}
                        </p>
                    </div>

                    <Tabs v-model="target" class="flex min-h-0 flex-1 flex-col">
                        <TabsList class="m-3 w-fit">
                            <TabsTrigger
                                v-for="item in CURL_TARGETS"
                                :key="item"
                                :value="item"
                                :data-test="`curl-tab-${item}`"
                            >
                                {{ t(`tools.curl-to-code.tabs.${item}`) }}
                            </TabsTrigger>
                        </TabsList>

                        <div class="min-h-0 flex-1 overflow-auto">
                            <pre
                                class="px-3 pb-3 font-mono text-sm whitespace-pre"
                                data-test="curl-output"
                                >{{ output }}</pre>
                        </div>
                    </Tabs>
                </template>
            </div>
        </template>

        <template #status>
            <p class="text-xs text-subtle" data-test="curl-status">
                {{ status }}
            </p>
        </template>
    </ToolShell>
</template>
