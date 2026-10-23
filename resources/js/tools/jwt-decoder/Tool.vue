<script setup lang="ts">
import { Copy, Eraser, Sparkles, TriangleAlert } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import CodeEditor from '@/components/tool/CodeEditor.vue';
import ToolActionBar from '@/components/tool/ToolActionBar.vue';
import ToolButton from '@/components/tool/ToolButton.vue';
import ToolShell from '@/components/tool/ToolShell.vue';
import { useCopyFeedback } from '@/composables/useCopyFeedback';
import { formatBytes } from '@/lib/bytes';
import { byteLength } from '@/lib/json/parse';
import { decodeJwt, type DecodeResult, type TimeClaim } from './lib/decode';
import {
    paintJson,
    paintToText,
    type JsonToken,
    type JsonTokenKind,
} from './lib/paint';
import sample from './sample.txt?raw';

/*
 * JWT Decoder.
 *
 * Uma entrada (o token) e quatro leituras, na ordem em que quem depura um token
 * as quer: o cabecalho, o payload, as datas dos claims e o tamanho da
 * assinatura. Nao ha abas: as quatro cabem empilhadas, e a segunda e a que
 * interessa - esconder o payload atras de uma aba seria esconder o motivo de a
 * pessoa ter vindo.
 *
 * A **faixa de aviso e fixa**, e nao um estado: ela aparece com a tela vazia,
 * com um token valido e com um token vencido, porque o risco que ela cobre
 * existe nos tres - alguem ler o payload e concluir que o token e confiavel.
 * Um aviso que so aparecesse as vezes seria lido como "as outras vezes esta
 * tudo bem".
 *
 * As **datas** saem em `Intl` sobre o instante do claim: o mesmo `exp` e
 * "01 de out. de 2026" ou "Oct 01, 2026" conforme o idioma da pagina, e o fuso
 * e o de quem esta lendo, que e o relogio com que ele vai comparar. Os claims
 * de tempo aparecem dentro do payload (sao JSON, como os outros) e viram
 * tambem uma linha legivel embaixo - sem esconder o numero cru, que continua
 * no bloco acima.
 *
 * A conta e sincrona: um token tem centenas de bytes, e nao os 5 MB que o teto
 * da entrada permite - o teto vem de `config/tools.php`, como nas outras tools,
 * e serve para a tela nao travar com um texto que nao e um token.
 */

const props = defineProps<{
    /** Limite de entrada em bytes, vindo de `config/tools.php` pelo controller. */
    maxInputBytes: number;
}>();

const { t, locale } = useI18n();

/** O token colado - a fonte da verdade da tela. */
const text = ref('');

const copyFeedback = useCopyFeedback();

/*
| A conta
*/

const isEmpty = computed(() => text.value.trim() === '');
const inputBytes = computed(() => byteLength(text.value));
const tooLarge = computed(() => inputBytes.value > props.maxInputBytes);

/**
 * A leitura do token, ou nada quando nao ha o que ler.
 *
 * O vazio e o texto acima do teto ficam de fora daqui para o `decodeJwt` nao
 * ter de responder sobre um texto que a tela ja recusou.
 */
const result = computed<DecodeResult | null>(() =>
    isEmpty.value || tooLarge.value ? null : decodeJwt(text.value),
);

const token = computed(() =>
    result.value !== null && result.value.ok ? result.value.token : null,
);

const failure = computed(() =>
    result.value !== null && !result.value.ok ? result.value.reason : null,
);

/** As cores do JSON do DESIGN - o mesmo mapa de `TreeRow`, no json-viewer. */
const KIND_CLASS: Record<JsonTokenKind, string> = {
    key: 'text-json-key',
    string: 'text-json-string',
    number: 'text-json-number',
    literal: 'text-json-literal',
    punct: 'text-json-punct',
};

interface Block {
    name: 'header' | 'payload';
    tokens: JsonToken[];
    /** O bloco como texto, para a area de transferencia. */
    text: string;
}

/**
 * Os dois blocos do token, na ordem em que a tela os mostra.
 *
 * O texto de cada um sai dos proprios pedacos coloridos, e nao de um
 * `JSON.stringify` paralelo: assim o que se copia e o que esta na tela, e nao
 * duas versoes que podem divergir.
 */
const blocks = computed<Block[]>(() => {
    const value = token.value;

    if (value === null) {
        return [];
    }

    return (['header', 'payload'] as const).map((name) => {
        const tokens = paintJson(value[name]);

        return { name, tokens, text: paintToText(tokens) };
    });
});

const claims = computed<TimeClaim[]>(() => token.value?.claims ?? []);

/**
 * O instante no idioma da pagina, com o fuso de quem le.
 *
 * As opcoes sao explicitas, e nao `dateStyle`/`timeStyle`: o nome do fuso nao
 * cabe junto com os dois estilos, e sem ele a hora mostrada nao bate com o
 * numero cru do payload para quem esta longe de Greenwich.
 */
const dateFormat = computed(
    () =>
        new Intl.DateTimeFormat(locale.value.replace('_', '-'), {
            year: 'numeric',
            month: 'short',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            timeZoneName: 'short',
        }),
);

/** A linha de um claim de tempo como a tela a desenha. */
interface ClaimView {
    name: TimeClaim['name'];
    /** A data no idioma, ou o valor cru quando ele nao vira data. */
    value: string;
    badge: { text: string; error: boolean } | null;
}

const claimViews = computed<ClaimView[]>(() => {
    const value = token.value;

    return claims.value.map((claim) => ({
        name: claim.name,
        value:
            claim.date === null
                ? JSON.stringify(claim.raw)
                : dateFormat.value.format(claim.date),
        badge:
            value === null
                ? null
                : badgeOf(claim, value.expired, value.notYetValid),
    }));
});

/**
 * O selo do claim: vencido ou ainda nao valido - os outros dois nao tem selo.
 *
 * "Expirado" e erro, e sai em `--destructive`: o token **nao** serve mais.
 * "Ainda nao vale" e aviso: o token esta certo e o relogio de quem emitiu ainda
 * nao chegou la.
 */
function badgeOf(
    claim: TimeClaim,
    expired: boolean,
    notYetValid: boolean,
): { text: string; error: boolean } | null {
    if (claim.name === 'exp' && expired) {
        return { text: t('tools.jwt-decoder.claims.expired'), error: true };
    }

    if (claim.name === 'nbf' && notYetValid) {
        return { text: t('tools.jwt-decoder.claims.not_yet'), error: false };
    }

    return null;
}

const signatureText = computed(() =>
    t('tools.jwt-decoder.signature_bytes', {
        count: token.value?.signatureBytes ?? 0,
    }),
);

/*
| As acoes
*/

/** O que o botao Copiar da barra leva: os dois blocos e o tamanho da assinatura. */
const copyText = computed(() => {
    if (token.value === null) {
        return '';
    }

    const parts = blocks.value.map(
        (block) => `${t(`tools.jwt-decoder.${block.name}`)}:\n${block.text}`,
    );

    parts.push(
        `${t('tools.jwt-decoder.signature')}: ${signatureText.value} (${t('tools.jwt-decoder.not_verified')})`,
    );

    return parts.join('\n');
});

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

function copyBlock(block: Block): void {
    void copyFeedback.copy(
        block.text,
        t('tools.jwt-decoder.block_copied', {
            label: t(`tools.jwt-decoder.${block.name}`),
        }),
    );
}

/**
 * Troca o token inteiro: e o caminho de colar, do exemplo e do limpar.
 *
 * O `.trim()` e do campo, e nao da tela: um token copiado de um cabecalho
 * chega com quebra de linha no fim, e a quebra nao faz parte dele.
 */
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

/** O recado que ocupa o lugar dos blocos, quando nao ha token para mostrar. */
const state = computed<{ text: string } | null>(() => {
    if (tooLarge.value) {
        return {
            text: t('tools.ui.states.too_large', {
                size: formatBytes(inputBytes.value, locale.value),
                limit: formatBytes(props.maxInputBytes, locale.value),
            }),
        };
    }

    return failure.value === null
        ? null
        : { text: t(`tools.jwt-decoder.states.${failure.value}`) };
});

/**
 * O recado do rodape: o que acabou de acontecer manda, e a medida do texto fica
 * para quando nao ha nada acontecendo. Nao ha erro aqui - o que impede a
 * leitura do token sai no painel, no lugar dos blocos.
 */
const status = computed<string>(
    () =>
        copyFeedback.message.value ??
        (isEmpty.value ? t('tools.ui.states.empty') : statsMessage.value),
);
</script>

<template>
  <ToolShell split-key="jwt-decoder">
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
          testid="jwt-decoder-editor"
          class="absolute inset-0"
          :placeholder="t('tools.ui.states.empty')"
        />
      </div>
    </template>

    <template #output>
      <div class="flex min-h-0 flex-1 flex-col">
        <!--
                    A faixa de aviso e **fixa**: ela nao depende de haver token
                    na tela. O risco que ela cobre - ler o payload e concluir que
                    o token e confiavel - e o motivo de a tool existir.
                -->
        <p
          class="flex items-start gap-2 border-b border-accent/40 bg-accent/5 px-3 py-2 text-xs text-foreground"
          data-test="jwt-decoder-warning"
        >
          <TriangleAlert
            class="mt-px size-3.5 shrink-0 text-subtle"
            aria-hidden="true"
          />
          {{ t('tools.jwt-decoder.warning') }}
        </p>

        <div class="min-h-0 flex-1 overflow-auto">
          <p
            v-if="state !== null"
            role="status"
            class="flex h-full flex-col items-center justify-center gap-2 p-6 text-center text-sm text-destructive"
            data-test="jwt-decoder-state"
          >
            <TriangleAlert
              class="size-5"
              aria-hidden="true"
            />
            {{ state.text }}
          </p>

          <template v-else>
            <section
              v-for="block in blocks"
              :key="block.name"
              class="border-b border-border last:border-b-0"
              :data-block="block.name"
              data-test="jwt-decoder-block"
            >
              <div class="flex items-center gap-2 px-3 pt-2">
                <h3
                  class="text-[11px] font-medium tracking-wide text-subtle uppercase"
                  data-test="jwt-decoder-block-label"
                >
                  {{ t(`tools.jwt-decoder.${block.name}`) }}
                </h3>

                <button
                  type="button"
                  class="hover:bg-surface-muted ml-auto rounded p-1 text-subtle transition-colors hover:text-foreground"
                  :aria-label="`${t('tools.ui.actions.copy')} — ${t(`tools.jwt-decoder.${block.name}`)}`"
                  data-test="jwt-decoder-block-copy"
                  @click="copyBlock(block)"
                >
                  <Copy
                    class="size-3.5"
                    aria-hidden="true"
                  />
                </button>
              </div>

              <!--
                                Sem espaco entre a tag e o `<span>`: o `<pre>`
                                desenha o texto como ele esta escrito, e uma
                                quebra de linha aqui viraria espaco na tela.
                            -->
              <pre
                class="px-3 pb-3 font-mono text-xs leading-relaxed whitespace-pre"
                data-test="jwt-decoder-json"
              ><span
                                v-for="(piece, index) in block.tokens"
                                :key="index"
                                :class="KIND_CLASS[piece.kind]"
              >{{ piece.text }}</span></pre>
            </section>

            <!--
                            As datas dos claims. O numero cru continua no bloco
                            acima: o que entra aqui e a leitura dele, e nao um
                            substituto.
                        -->
            <section
              v-if="claimViews.length > 0"
              class="border-b border-border px-3 py-2"
              data-test="jwt-decoder-claims"
            >
              <h3
                class="text-[11px] font-medium tracking-wide text-subtle uppercase"
              >
                {{ t('tools.jwt-decoder.claims_title') }}
              </h3>

              <ul class="mt-1">
                <li
                  v-for="claim in claimViews"
                  :key="claim.name"
                  class="flex flex-wrap items-center gap-x-2 gap-y-1 py-0.5"
                  :data-claim="claim.name"
                  data-test="jwt-decoder-claim"
                >
                  <span
                    class="font-mono text-xs text-subtle"
                    data-test="jwt-decoder-claim-name"
                  >{{ claim.name }}</span>

                  <span
                    class="font-mono text-xs"
                    data-test="jwt-decoder-claim-value"
                  >{{ claim.value }}</span>

                  <span
                    v-if="claim.badge !== null"
                    class="rounded border px-1.5 py-0.5 text-[11px] font-medium"
                    :class="
                      claim.badge.error
                        ? 'border-destructive/40 text-destructive'
                        : 'border-border text-subtle'
                    "
                    data-test="jwt-decoder-claim-badge"
                  >{{ claim.badge.text }}</span>
                </li>
              </ul>
            </section>

            <!--
                            A assinatura aparece **so** como comprimento: a tool
                            nao tem a chave, entao nao ha o que conferir - e o
                            que nao da para conferir nao vira selo verde.
                        -->
            <section
              v-if="token !== null"
              class="flex flex-wrap items-center gap-x-2 gap-y-1 px-3 py-2"
              data-test="jwt-decoder-signature"
            >
              <h3
                class="text-[11px] font-medium tracking-wide text-subtle uppercase"
              >
                {{ t('tools.jwt-decoder.signature') }}
              </h3>

              <span class="font-mono text-xs">{{
                signatureText
              }}</span>
              <span class="text-xs text-subtle">
                {{ t('tools.jwt-decoder.not_verified') }}
              </span>
            </section>
          </template>
        </div>
      </div>
    </template>

    <template #status>
      <p
        class="text-xs text-subtle"
        data-test="jwt-decoder-status"
      >
        {{ status }}
      </p>
    </template>
  </ToolShell>
</template>
