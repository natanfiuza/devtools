<script setup lang="ts">
import { Copy, Eraser, FolderOpen, Sparkles, TriangleAlert } from '@lucide/vue';
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
import { useCopyFeedback } from '@/composables/useCopyFeedback';
import { useFileError } from '@/composables/useFileError';
import { formatBytes } from '@/lib/bytes';
import { acceptAttribute, readTextFile, type FileRules } from '@/lib/file';
import { byteLength } from '@/lib/json/parse';
import {
    flagsOf,
    highlight,
    REGEX_FLAGS,
    type RegexFlag,
    type RegexMatch,
    type RegexRequest,
    type RegexResult,
} from './lib/run';
import { createRunner } from './lib/runner';
import sample from './sample.txt?raw';

/*
 * O Regex Tester.
 *
 * **A excecao da casca**: o padrao mora na barra de acoes, em linha inteira, com
 * as flags ao lado. Nas outras tools a barra so tem acao - e o porque de aqui
 * ser diferente esta na natureza das duas coisas: o texto de teste e o
 * **documento** (mora no painel, com editor, arquivo e exemplo), e a expressao e
 * um **argumento da busca** - uma linha so, que se digita e se ajusta olhando
 * para o resultado. Enterra-la no painel de entrada seria por o argumento no
 * lugar do sujeito, e ainda a afastaria da lista de casamentos que ela produz.
 * O `g` esta sempre ligado (a tela promete "todos os casamentos") e o `y` fica
 * de fora (ele muda o sentido da busca) - a decisao inteira esta em `lib/run`.
 *
 * A busca roda num **worker**, e nao aqui: um padrao como `(a+)+$` leva anos
 * sobre trinta `a` e uma letra que nao casa, e na thread principal isso e a aba
 * travada. Quem mata a conta presa e o relogio (`lib/runner`), com o tempo de
 * `config/tools.php`.
 *
 * Nao ha "ultima saida valida" aqui, ao contrario do JSON Viewer, do JSON to
 * Code e do SQL Formatter: a saida desta tool nao e um documento derivado do
 * texto - e uma **leitura** dele. Guardar a leitura de um texto que ja mudou
 * seria mostrar o que nao esta mais la.
 */

const props = defineProps<{
    /** Limite de entrada em bytes, vindo de `config/tools.php` pelo controller. */
    maxInputBytes: number;
    /** Tempo maximo de uma busca, em milissegundos. */
    regexTimeoutMs: number;
    /** Teto de casamentos por busca. */
    regexMaxMatches: number;
}>();

const { t, locale } = useI18n();

/** Espera entre a ultima tecla e a busca: digitar nao busca por tecla. */
const DEBOUNCE_MS = 200;

/** O que a caixa de arquivo aceita; o mesmo vale para o `accept` do seletor. */
const FILE_RULES: Pick<FileRules, 'extensions' | 'types'> = {
    extensions: ['.txt'],
    types: ['text/plain'],
};

/*
 * O exemplo: um par, e nao um texto solto.
 *
 * Um padrao sem texto de teste nao mostra nada, e um texto sem padrao mostra
 * menos ainda - entao o botao escreve os dois, mais a troca que faz a
 * substituicao aparecer. A expressao le uma linha de log e nomeia o que
 * encontra; o texto que ela le esta em `sample.txt`.
 */
const EXAMPLE_PATTERN =
    '^(?<quando>\\S+) (?<nivel>[A-Z]+)\\s+pedido (?<id>\\d+) (?<mensagem>.+)$';
const EXAMPLE_REPLACEMENT = '$<nivel> #$<id>: $<mensagem>';
const EXAMPLE_TOGGLES: Record<RegexFlag, boolean> = {
    i: false,
    m: true,
    s: false,
    u: false,
};

/** O que a linha da lista mostra no lugar de um valor que nao existe. */
const AUSENTE = '—';

/** O texto do painel de entrada - a fonte da verdade da tela. */
const text = ref('');
/** A expressao regular, e o que a troca escreve no lugar de cada casamento. */
const pattern = ref('');
const replacement = ref('');
/** As flags ligadas; `g` nao esta aqui porque nunca sai. */
const toggles = ref<Record<RegexFlag, boolean>>({
    i: false,
    m: false,
    s: false,
    u: false,
});

/** O resultado da busca. `shallowRef`: o resultado nao precisa ser reativo. */
const outcome = shallowRef<RegexResult | null>(null);
const busy = ref(false);
const tooLarge = ref(false);
/** A conta estourou o tempo: nao ha resultado, e a tela diz isso. */
const timedOut = ref(false);
const inputBytes = ref(0);

const copyFeedback = useCopyFeedback();
const fileError = useFileError(
    () => props.maxInputBytes,
    () => t('tools.regex-tester.file_types'),
);

const dropzone = useTemplateRef<InstanceType<typeof FileDropzone>>('dropzone');
const patternInput = useTemplateRef<HTMLInputElement>('patternInput');

/*
| A busca
*/

const runner = createRunner({ timeoutMs: props.regexTimeoutMs });

/** O tempo limite em segundos: a configuracao fala em ms, e a frase, em segundos. */
const timeoutSeconds = computed(() => props.regexTimeoutMs / 1_000);

const isEmpty = computed(() => text.value.trim() === '');

/** As flags como o motor as quer - e como a barra as mostra, no fim do campo. */
const activeFlags = computed(() => flagsOf(toggles.value));

/** A troca que a busca recebe: campo vazio e "sem substituicao", e nao a troca vazia. */
function replacementOrNull(): string | null {
    return replacement.value === '' ? null : replacement.value;
}

/** O pedido ainda vale? A tela toda pode ter mudado enquanto a conta corria. */
function isStale(request: RegexRequest): boolean {
    return (
        request.text !== text.value ||
        request.pattern !== pattern.value ||
        request.flags !== flagsOf(toggles.value) ||
        request.replacement !== replacementOrNull()
    );
}

async function runSearch(): Promise<void> {
    const value = text.value;
    const bytes = byteLength(value);

    inputBytes.value = bytes;

    if (bytes > props.maxInputBytes) {
        tooLarge.value = true;
        outcome.value = null;
        timedOut.value = false;
        busy.value = false;

        return;
    }

    tooLarge.value = false;

    // Sem texto ou sem padrao nao ha busca: o padrao vazio casaria em toda
    // posicao - uma lista de mil vazios, e nao uma resposta.
    if (isEmpty.value || pattern.value === '') {
        outcome.value = null;
        timedOut.value = false;
        busy.value = false;

        return;
    }

    const request: RegexRequest = {
        pattern: pattern.value,
        flags: activeFlags.value,
        text: value,
        replacement: replacementOrNull(),
        maxMatches: props.regexMaxMatches,
    };

    busy.value = true;
    timedOut.value = false;

    const found = await runner.run(request);

    // Superado por um pedido mais novo, ou com a tela ja mudada: quem pinta e o
    // pedido que veio depois. O segundo teste e o que segura o **estouro de
    // tempo**, que nao chega como "superado" - o worker morreu, e o cliente nao
    // tem como saber que o pedido envelheceu.
    if (found.status === 'superseded' || isStale(request)) {
        return;
    }

    busy.value = false;

    if (found.status === 'timeout') {
        // Nenhum resultado, e nao o resultado atrasado: a conta morreu no meio,
        // e o que ela tinha para dizer valia para um texto que talvez nem tenha
        // sido lido inteiro.
        outcome.value = null;
        timedOut.value = true;

        return;
    }

    outcome.value = found.result;
}

const searchSoon = useDebounceFn(() => {
    void runSearch();
}, DEBOUNCE_MS);

/**
 * Como o proximo estado entra: um botao ja tem o que mostrar (`now`) ou foi
 * digitacao (`debounced`).
 */
let intake: 'now' | 'debounced' = 'debounced';

function onChange(): void {
    // O vazio vem primeiro: o botao Limpar tambem esvazia o painel, e um painel
    // vazio nao tem leitura para a saida mostrar.
    if (isEmpty.value) {
        intake = 'debounced';
        reset();

        return;
    }

    if (intake === 'now') {
        intake = 'debounced';
        void runSearch();

        return;
    }

    searchSoon();
}

/*
 * Um observador so para os quatro estados. O exemplo troca texto, padrao,
 * flags e troca de uma vez, e a busca tem que sair **uma** vez, com o conjunto
 * novo - quatro observadores pediriam quatro buscas, e as tres primeiras
 * seriam sobre um estado que nunca existiu na tela.
 */
watch([text, pattern, replacement, toggles], onChange);

function toggle(flag: RegexFlag): void {
    // Objeto novo: o observador compara por identidade, e mutar o de antes nao
    // o acordaria.
    toggles.value = { ...toggles.value, [flag]: !toggles.value[flag] };
}

function reset(): void {
    outcome.value = null;
    timedOut.value = false;
    tooLarge.value = false;
    inputBytes.value = 0;
    busy.value = false;
    copyFeedback.clear();
    fileError.clear();
}

/*
| As acoes
*/

interface ScreenChange {
    text?: string;
    pattern?: string;
    replacement?: string;
    toggles?: Record<RegexFlag, boolean>;
}

/**
 * Troca o que a tela mostra de uma vez: e o caminho do exemplo, do arquivo, de
 * colar e de limpar.
 */
function apply(next: ScreenChange): void {
    fileError.clear();
    copyFeedback.clear();

    const changed =
        (next.text !== undefined && next.text !== text.value) ||
        (next.pattern !== undefined && next.pattern !== pattern.value) ||
        (next.replacement !== undefined &&
            next.replacement !== replacement.value) ||
        (next.toggles !== undefined && next.toggles !== toggles.value);

    // Sem mudanca nao ha `watch` para observar: deixar o `intake` armado faria a
    // proxima tecla digitada ser buscada fora do intervalo.
    if (!changed) {
        return;
    }

    intake = 'now';

    if (next.text !== undefined) {
        text.value = next.text;
    }

    if (next.pattern !== undefined) {
        pattern.value = next.pattern;
    }

    if (next.replacement !== undefined) {
        replacement.value = next.replacement;
    }

    if (next.toggles !== undefined) {
        toggles.value = next.toggles;
    }
}

function loadExample(): void {
    apply({
        text: sample,
        pattern: EXAMPLE_PATTERN,
        replacement: EXAMPLE_REPLACEMENT,
        toggles: EXAMPLE_TOGGLES,
    });
}

/**
 * Limpar mexe **so no documento** - o texto de teste.
 *
 * O padrao, as flags e a troca sao controles da busca, como o dialeto do SQL
 * Formatter: quem escreveu uma expressao e limpou o texto para colar outro
 * continua com a expressao na mao.
 */
function clear(): void {
    apply({ text: '' });
}

/** A saida de verdade da tool: o texto de teste passado pela troca. */
const replaced = computed(() =>
    outcome.value?.ok === true ? outcome.value.replaced : null,
);

function copy(): void {
    void copyFeedback.copy(replaced.value ?? '');
}

/** Ctrl+F no editor cai no padrao: e ele que se ajusta olhando o resultado. */
function focusPattern(): void {
    patternInput.value?.focus();
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

    apply({ text: result.text });
}

function openFile(): void {
    dropzone.value?.pick();
}

/*
| A saida
*/

const matches = computed(() =>
    outcome.value?.ok === true ? outcome.value.matches : [],
);
const hasResult = computed(() => outcome.value?.ok === true);
const truncated = computed(
    () => outcome.value?.ok === true && outcome.value.truncated,
);

/** O texto cortado nos casamentos, para a previa realcada. */
const parts = computed(() =>
    hasResult.value ? highlight(text.value, matches.value) : [],
);

/** O que a linha da lista mostra no lugar do casamento: o vazio e um valor, e o `''` o diz. */
function excerptOf(match: RegexMatch): string {
    return match.text === '' ? "''" : match.text;
}

/**
 * O recado do painel de saida, quando nao ha leitura para mostrar.
 *
 * A ordem e a do que trava mais: o teto de entrada, o padrao invalido, o tempo
 * estourado, o que falta escrever, e por fim a espera. O "a processar" so
 * aparece quando **nao ha** leitura na tela - com o resultado de antes a vista,
 * a busca seguinte espera calada em vez de piscar por cima dele.
 */
const state = computed<{
    text: string;
    error: boolean;
    detail: string | null;
} | null>(() => {
    if (tooLarge.value) {
        return {
            text: t('tools.ui.states.too_large', {
                size: formatBytes(inputBytes.value, locale.value),
                limit: formatBytes(props.maxInputBytes, locale.value),
            }),
            error: true,
            detail: null,
        };
    }

    const current = outcome.value;

    if (current !== null && !current.ok) {
        // A frase e nossa e traduzida; o detalhe e a mensagem do motor, que e o
        // unico texto que diz *o que* esta errado na expressao.
        return {
            text: t('tools.regex-tester.states.pattern'),
            error: true,
            detail: current.detail,
        };
    }

    if (timedOut.value) {
        return {
            text: t('tools.regex-tester.states.timeout', {
                seconds: timeoutSeconds.value,
            }),
            error: true,
            detail: null,
        };
    }

    if (isEmpty.value) {
        return { text: t('tools.ui.states.empty'), error: false, detail: null };
    }

    if (pattern.value === '') {
        return {
            text: t('tools.regex-tester.states.pattern_empty'),
            error: false,
            detail: null,
        };
    }

    if (busy.value && !hasResult.value) {
        return {
            text: t('tools.ui.states.parsing'),
            error: false,
            detail: null,
        };
    }

    return null;
});

/**
 * O recado do rodape: o erro do arquivo manda, depois o que acabou de acontecer,
 * depois o estado da tela e, por fim, a contagem.
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

    const current = state.value;

    if (current !== null) {
        return { text: current.text, error: current.error };
    }

    return hasResult.value
        ? {
              text: t('tools.regex-tester.matches_count', {
                  count: matches.value.length,
              }),
              error: false,
          }
        : null;
});

onBeforeUnmount(() => {
    runner.dispose();
});
</script>

<template>
  <ToolShell split-key="regex-tester">
    <template #actions>
      <ToolActionBar>
        <!--
                    O padrao como um literal de expressao: as barras em volta e as
                    flags no fim. As flags sao **sintaxe** - nao passam pelo i18n -,
                    e os alternadores logo ao lado dizem quais estao ligadas.
                -->
        <div
          class="flex min-w-40 flex-1 items-center gap-1 font-mono text-sm"
        >
          <span
            class="text-subtle"
            aria-hidden="true"
          >/</span>
          <input
            ref="patternInput"
            v-model="pattern"
            type="text"
            spellcheck="false"
            autocomplete="off"
            autocapitalize="off"
            class="h-8 min-w-0 flex-1 rounded-md border border-border bg-transparent px-2 font-mono text-sm"
            :aria-label="t('tools.regex-tester.pattern')"
            :placeholder="t('tools.regex-tester.pattern')"
            data-test="regex-tester-pattern"
          >
          <span
            class="text-subtle"
            aria-hidden="true"
          >/{{ activeFlags }}</span>
        </div>

        <div class="flex items-center gap-1">
          <ToolButton
            v-for="flag in REGEX_FLAGS"
            :key="flag"
            :label="flag"
            :aria-label="t(`tools.regex-tester.flags.${flag}`)"
            :pressed="toggles[flag]"
            :variant="toggles[flag] ? 'default' : 'outline'"
            @click="toggle(flag)"
          />
        </div>

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
            :disabled="replaced === null"
            @click="copy"
          >
            <template #icon>
              <Copy class="size-3.5" />
            </template>
          </ToolButton>
        </template>
      </ToolActionBar>
    </template>

    <template #input>
      <FileDropzone
        ref="dropzone"
        :accept="acceptAttribute(FILE_RULES)"
        testid="regex-tester"
        @file="onFile"
      >
        <CodeEditor
          v-model="text"
          testid="regex-tester-editor"
          class="absolute inset-0"
          :placeholder="t('tools.ui.states.empty')"
          @find="focusPattern"
        />
      </FileDropzone>
    </template>

    <!--
            A troca fica no cabecalho da **saida**: ela nao muda o que se procura,
            muda o que sai da busca - e a previa do resultado esta logo abaixo.
        -->
    <template #output-header>
      <input
        v-model="replacement"
        type="text"
        spellcheck="false"
        autocomplete="off"
        class="h-7 w-44 rounded-md border border-border bg-transparent px-2 font-mono text-xs"
        :aria-label="t('tools.regex-tester.replacement')"
        :placeholder="t('tools.regex-tester.replacement_hint')"
        data-test="regex-tester-replacement"
      >
    </template>

    <template #output>
      <div
        class="relative min-h-0 flex-1 overflow-auto"
        data-test="regex-tester-output"
      >
        <!-- O estado manda: sem leitura valida nao ha o que realcar. -->
        <div
          v-if="state !== null"
          role="status"
          class="flex h-full flex-col items-center justify-center gap-2 p-6 text-center text-sm"
          :class="state.error ? 'text-destructive' : 'text-subtle'"
          data-test="regex-tester-state"
        >
          <TriangleAlert
            v-if="state.error"
            class="size-5"
            aria-hidden="true"
          />
          <span>{{ state.text }}</span>
          <code
            v-if="state.detail !== null"
            class="max-w-full text-xs break-words text-subtle"
            data-test="regex-tester-detail"
          >{{ state.detail }}</code>
        </div>

        <template v-else>
          <!--
                        O texto com os casamentos pintados. Os pedacos sao um por
                        casamento (mais os pedacos entre eles), entao o teto de
                        casamentos ja limita quantos elementos nascem aqui.
                    -->
          <pre
            v-if="parts.length > 0"
            class="border-b px-3 py-2 font-mono text-sm break-words whitespace-pre-wrap"
            data-test="regex-tester-preview"
          ><span
                        v-for="(part, index) in parts"
                        :key="index"
                        :class="part.match ? 'rounded-sm bg-accent-2/30' : ''"
                    >{{ part.text }}</span></pre>

          <p
            v-if="hasResult && matches.length === 0"
            class="p-3 text-sm text-subtle"
            data-test="regex-tester-none"
          >
            {{ t('tools.regex-tester.matches_none') }}
          </p>

          <p
            v-if="truncated"
            class="border-b px-3 py-1.5 text-xs text-subtle"
            data-test="regex-tester-truncated"
          >
            {{
              t('tools.regex-tester.truncated', {
                count: matches.length,
              })
            }}
          </p>

          <ol
            v-if="matches.length > 0"
            data-test="regex-tester-matches"
          >
            <li
              v-for="(match, index) in matches"
              :key="`${index}-${match.index}`"
              class="border-b border-border/60 px-3 py-2 last:border-b-0"
              data-test="regex-tester-match"
            >
              <div
                class="flex flex-wrap items-baseline gap-x-2 gap-y-1"
              >
                <span class="font-mono text-xs text-subtle">#{{ index + 1 }}</span>
                <span
                  class="rounded-sm bg-surface-2 px-1 font-mono text-sm"
                  data-test="regex-tester-match-text"
                >{{ excerptOf(match) }}</span>
                <span
                  class="text-xs text-subtle"
                  data-test="regex-tester-match-position"
                >
                  {{
                    t('tools.regex-tester.position', {
                      start: match.index,
                    })
                  }}
                </span>
              </div>

              <div
                v-if="
                  match.groups.length > 0 ||
                    match.named.length > 0
                "
                class="mt-1 flex flex-wrap gap-x-3 gap-y-1 font-mono text-xs text-subtle"
              >
                <span
                  v-for="(group, at) in match.groups"
                  :key="`g${at}`"
                  data-test="regex-tester-group"
                >${{ at + 1 }}: {{ group ?? AUSENTE }}</span>
                <span
                  v-for="group in match.named"
                  :key="group.name"
                  data-test="regex-tester-named-group"
                >$&lt;{{ group.name }}&gt;:
                  {{ group.text ?? AUSENTE }}</span>
              </div>
            </li>
          </ol>

          <section
            v-if="replaced !== null"
            class="px-3 py-2"
          >
            <h3
              class="text-[11px] font-medium tracking-wide text-subtle uppercase"
            >
              {{ t('tools.regex-tester.replaced_title') }}
            </h3>
            <pre
              class="mt-1 font-mono text-sm break-words whitespace-pre-wrap"
              data-test="regex-tester-replaced"
            >{{ replaced }}</pre>
          </section>
        </template>
      </div>
    </template>

    <template #status>
      <p
        v-if="status !== null"
        class="text-xs"
        :class="status.error ? 'text-destructive' : 'text-subtle'"
        data-test="regex-tester-status"
      >
        {{ status.text }}
      </p>
    </template>
  </ToolShell>
</template>
