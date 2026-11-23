<script setup lang="ts">
import {
    Clock,
    Copy,
    Eraser,
    Hash,
    RefreshCw,
    Sparkles,
    TriangleAlert,
} from '@lucide/vue';
import { computed, onUnmounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import CodeEditor from '@/components/tool/CodeEditor.vue';
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
import { formatBytes } from '@/lib/bytes';
import { byteLength } from '@/lib/json/parse';
import {
    DEFAULT_ID_KIND,
    DEFAULT_ID_QUANTITY,
    generateIds,
    ID_KINDS,
    ID_QUANTITIES,
    type IdKind,
} from './lib/ids';
import {
    readInstant,
    readingsFor,
    type Instant,
    type Reading,
} from './lib/time';
import sample from './sample.txt?raw';

/*
 * Timestamp e UUID.
 *
 * Duas contas que nao se falam, na mesma tela: **ler** um instante (o texto de
 * cima vira seis linhas) e **gerar** ids (uma lista, do lado direito). Elas nao
 * dependem uma da outra - quem esta lendo um log nao esta pedindo UUID -, e por
 * isso os ids ficam numa secao propria, com o botao de gerar no cabecalho do
 * painel de saida, e nao na barra de acoes.
 *
 * A entrada e so texto: um instante chega colado de um log, e um arquivo com um
 * instante dentro nao e um caso que exista.
 *
 * "Agora" escreve o Unix em **segundos**, que e a forma que se cola de um log; e
 * a unica acao que depende do relogio junto com o "tempo relativo" da saida.
 */

const props = defineProps<{
    /** Limite de entrada em bytes, vindo de `config/tools.php` pelo controller. */
    maxInputBytes: number;
}>();

const { t, locale } = useI18n();

/** O texto do painel de entrada - a fonte da verdade da tela. */
const text = ref('');

/** Os ids gerados, o tipo escolhido e a quantidade. */
const ids = ref<string[]>([]);
const idKind = ref<IdKind>(DEFAULT_ID_KIND);
const idQuantity = ref<number>(DEFAULT_ID_QUANTITY);

const copyFeedback = useCopyFeedback();

/**
 * O agora da tela.
 *
 * So o "tempo relativo" depende dele, e ele anda: "ha 2 minutos" envelhece
 * enquanto a pagina esta aberta. Meio minuto e o passo - mais fino que isso a
 * linha mudaria por nada, e mais grosso ela mentiria por quase um minuto.
 */
const now = ref(Date.now());
const tick = setInterval(() => {
    now.value = Date.now();
}, 30_000);

onUnmounted(() => {
    clearInterval(tick);
});

/** O idioma na forma que o `Intl` espera (`pt_BR` -> `pt-BR`). */
const dateLocale = computed(() => locale.value.replace('_', '-'));

/*
| A leitura
*/

const isEmpty = computed(() => text.value.trim() === '');
const inputBytes = computed(() => byteLength(text.value));
const tooLarge = computed(() => inputBytes.value > props.maxInputBytes);

/**
 * O instante que o texto guarda.
 *
 * O texto acima do teto nem chega a ser lido: a conta seria sobre algo que a
 * tela ja recusou.
 */
const instant = computed<Instant | null>(() =>
    isEmpty.value || tooLarge.value ? null : readInstant(text.value),
);

/** O texto colado era um id, e nao um instante escrito. */
const isId = computed(
    () =>
        instant.value?.source === 'uuid_v7' || instant.value?.source === 'ulid',
);

const recognised = computed(() =>
    isId.value
        ? t('tools.timestamp-uuid.recognised', {
              kind: t(
                  `tools.timestamp-uuid.kinds.${instant.value?.source ?? 'ulid'}`,
              ),
          })
        : '',
);

const readings = computed<Reading[]>(() =>
    instant.value === null
        ? []
        : readingsFor(instant.value.date, now.value, dateLocale.value),
);

function labelOf(row: Reading): string {
    return t(`tools.timestamp-uuid.readings.${row.label}`);
}

/*
| As acoes
*/

/** O que o botao Copiar da barra leva: as seis leituras, uma por linha. */
const copyText = computed(() =>
    readings.value.map((row) => `${labelOf(row)}: ${row.text}`).join('\n'),
);

const hasOutput = computed(() => copyText.value !== '');

function loadExample(): void {
    applyText(sample);
}

/**
 * O instante de agora, escrito como o Unix em segundos.
 *
 * E o que vem de um log, e e a forma mais curta: as outras cinco leituras
 * aparecem sozinhas do lado direito.
 */
function useNow(): void {
    applyText(String(Math.floor(Date.now() / 1000)));
}

/**
 * Limpa o documento.
 *
 * Os ids ficam: eles nao saem da entrada: sao de outra conta, e apagar o que
 * alguem acabou de gerar porque limpou a caixa de cima seria perder trabalho.
 */
function clear(): void {
    applyText('');
}

function copyAll(): void {
    void copyFeedback.copy(copyText.value);
}

function copyReading(row: Reading): void {
    void copyFeedback.copy(
        row.text,
        t('tools.timestamp-uuid.row_copied', { label: labelOf(row) }),
    );
}

function generate(): void {
    ids.value = generateIds(idKind.value, idQuantity.value, Date.now());
}

function copyIds(): void {
    void copyFeedback.copy(
        ids.value.join('\n'),
        t('tools.timestamp-uuid.ids_copied', { count: ids.value.length }),
    );
}

function copyId(value: string): void {
    void copyFeedback.copy(value, t('tools.timestamp-uuid.id_copied'));
}

/** Troca o documento inteiro: e o caminho de colar, exemplo, agora e limpar. */
function applyText(next: string): void {
    copyFeedback.clear();
    text.value = next.trim();
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

/** O recado que ocupa o lugar das leituras, quando nao ha leitura nenhuma. */
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

    if (!isEmpty.value && instant.value === null) {
        return {
            text: t('tools.timestamp-uuid.states.unreadable'),
            error: true,
        };
    }

    return null;
});

/**
 * O recado do rodape: o que acabou de acontecer manda, depois o convite (ou a
 * conta dos ids, quando a caixa de cima esta vazia mas ha o que mostrar), e por
 * ultimo o tamanho do texto.
 */
const status = computed<string>(() => {
    const feedbackText = copyFeedback.message.value;

    if (feedbackText !== null) {
        return feedbackText;
    }

    if (!isEmpty.value) {
        return statsMessage.value;
    }

    return ids.value.length > 0
        ? t('tools.timestamp-uuid.ids_title', { count: ids.value.length })
        : t('tools.ui.states.empty');
});
</script>

<template>
  <ToolShell split-key="timestamp-uuid">
    <template #actions>
      <ToolActionBar>
        <ToolButton
          :label="t('tools.timestamp-uuid.now')"
          @click="useNow"
        >
          <template #icon>
            <Clock class="size-3.5" />
          </template>
        </ToolButton>

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
            @click="copyAll"
          >
            <template #icon>
              <Copy class="size-3.5" />
            </template>
          </ToolButton>
        </template>
      </ToolActionBar>
    </template>

    <template #input>
      <div class="relative flex min-h-0 flex-1 flex-col">
        <CodeEditor
          v-model="text"
          testid="timestamp-uuid-editor"
          class="absolute inset-0"
          :placeholder="t('tools.ui.states.empty')"
        />
      </div>
    </template>

    <!--
            Os geradores vivem no cabecalho do painel de **saida**: e nele que a
            lista aparece, e as duas escolhas (tipo e quantidade) valem para o
            que sai, nao para o que entra.
        -->
    <template #output-header>
      <div class="flex flex-wrap items-center gap-2">
        <Select v-model="idKind">
          <SelectTrigger
            class="h-7 w-32 text-xs"
            :aria-label="t('tools.timestamp-uuid.kind')"
            data-test="timestamp-uuid-kind"
          >
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="kind in ID_KINDS"
              :key="kind"
              :value="kind"
            >
              {{ t(`tools.timestamp-uuid.kinds.${kind}`) }}
            </SelectItem>
          </SelectContent>
        </Select>

        <Select v-model="idQuantity">
          <SelectTrigger
            class="h-7 w-20 text-xs"
            :aria-label="t('tools.timestamp-uuid.quantity')"
            data-test="timestamp-uuid-quantity"
          >
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="quantity in ID_QUANTITIES"
              :key="quantity"
              :value="quantity"
            >
              {{ quantity }}
            </SelectItem>
          </SelectContent>
        </Select>

        <ToolButton
          :label="t('tools.timestamp-uuid.generate')"
          data-test="timestamp-uuid-generate"
          @click="generate"
        >
          <template #icon>
            <RefreshCw class="size-3.5" />
          </template>
        </ToolButton>
      </div>
    </template>

    <template #output>
      <div class="min-h-0 flex-1 overflow-auto">
        <p
          v-if="state !== null"
          role="status"
          class="flex h-full flex-col items-center justify-center gap-2 p-6 text-center text-sm"
          :class="state.error ? 'text-destructive' : 'text-subtle'"
          data-test="timestamp-uuid-state"
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
                        Um id colado nao e um instante escrito: o aviso diz de
                        onde o instante saiu, para ninguem achar que o texto de
                        cima era uma data.
                    -->
          <p
            v-if="isId"
            class="flex items-center gap-1.5 border-b border-accent/40 bg-accent/5 px-3 py-1.5 text-xs text-foreground"
            data-test="timestamp-uuid-recognised"
          >
            <Hash
              class="size-3.5 shrink-0 text-subtle"
              aria-hidden="true"
            />
            {{ recognised }}
          </p>

          <ul>
            <li
              v-for="row in readings"
              :key="row.label"
              class="border-b border-border px-3 py-2 last:border-b-0"
              :data-label="row.label"
              data-test="timestamp-uuid-reading"
            >
              <div class="flex items-center gap-2">
                <h3
                  class="text-[11px] font-medium tracking-wide text-subtle uppercase"
                  data-test="timestamp-uuid-reading-label"
                >
                  {{ labelOf(row) }}
                </h3>

                <button
                  type="button"
                  class="hover:bg-surface-muted ml-auto rounded p-1 text-subtle transition-colors hover:text-foreground"
                  :aria-label="`${t('tools.ui.actions.copy')} — ${labelOf(row)}`"
                  data-test="timestamp-uuid-reading-copy"
                  @click="copyReading(row)"
                >
                  <Copy
                    class="size-3.5"
                    aria-hidden="true"
                  />
                </button>
              </div>

              <p
                class="mt-1 font-mono text-sm break-all"
                data-test="timestamp-uuid-reading-value"
              >
                {{ row.text }}
              </p>
            </li>
          </ul>

          <section
            v-if="ids.length > 0"
            class="border-t border-border"
            data-test="timestamp-uuid-ids"
          >
            <div class="flex items-center gap-2 px-3 py-2">
              <h3
                class="text-[11px] font-medium tracking-wide text-subtle uppercase"
                data-test="timestamp-uuid-ids-title"
              >
                {{
                  t('tools.timestamp-uuid.ids_title', {
                    count: ids.length,
                  })
                }}
              </h3>

              <button
                type="button"
                class="hover:bg-surface-muted ml-auto flex items-center gap-1 rounded px-1.5 py-1 text-xs text-subtle transition-colors hover:text-foreground"
                :aria-label="t('tools.timestamp-uuid.copy_ids')"
                data-test="timestamp-uuid-ids-copy"
                @click="copyIds"
              >
                <Copy
                  class="size-3.5"
                  aria-hidden="true"
                />
                {{ t('tools.timestamp-uuid.copy_ids') }}
              </button>
            </div>

            <ul>
              <li
                v-for="(id, index) in ids"
                :key="`${index}-${id}`"
                class="flex items-center gap-2 border-t border-border px-3 py-1.5"
                data-test="timestamp-uuid-id"
              >
                <p class="font-mono text-sm break-all">
                  {{ id }}
                </p>

                <button
                  type="button"
                  class="hover:bg-surface-muted ml-auto rounded p-1 text-subtle transition-colors hover:text-foreground"
                  :aria-label="`${t('tools.ui.actions.copy')} — ${id}`"
                  data-test="timestamp-uuid-id-copy"
                  @click="copyId(id)"
                >
                  <Copy
                    class="size-3.5"
                    aria-hidden="true"
                  />
                </button>
              </li>
            </ul>
          </section>
        </template>
      </div>
    </template>

    <template #status>
      <p
        class="text-xs text-subtle"
        data-test="timestamp-uuid-status"
      >
        {{ status }}
      </p>
    </template>
  </ToolShell>
</template>
