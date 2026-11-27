<script setup lang="ts">
import { Copy, Eraser, Sparkles, TriangleAlert } from '@lucide/vue';
import { computed, onUnmounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import ToolActionBar from '@/components/tool/ToolActionBar.vue';
import ToolButton from '@/components/tool/ToolButton.vue';
import ToolShell from '@/components/tool/ToolShell.vue';
import { Input } from '@/components/ui/input';
import { useCopyFeedback } from '@/composables/useCopyFeedback';
import { describeCron } from './lib/describe';
import { browserTimeZone, nextRuns } from './lib/next';
import { formatRun, buildReport } from './lib/report';
import { parseCron } from './lib/parse';
import type { CronFieldId, FieldDescription } from './types';
import sample from './sample.txt?raw';

/*
 * Explicador de cron.
 *
 * A expressao e curta por natureza - cinco campos, uma linha -, entao a
 * entrada e um campo de texto, nao um editor: o que interessa nao e o codigo,
 * e o significado. A explicacao fica no painel de entrada, campo a campo, para
 * a pessoa conferir passo, nomes e intervalos sem sair do lugar; a saida mostra o
 * fuso do navegador e as proximas dez execucoes.
 *
 * O calculo nao usa o fuso do processo: `browserTimeZone()` entrega o fuso de
 * quem esta vendo, e o motor converte parede e instante por `Intl`. A lista
 * envelhece enquanto a pagina esta aberta, entao o "agora" anda junto (meio
 * minuto, como no timestamp).
 */

const { t, locale } = useI18n();

/** A expressao digitada. */
const expression = ref('');

const copyFeedback = useCopyFeedback();

/** O fuso exibido e usado no calculo, lido uma vez ao montar. */
const zone = browserTimeZone();

/** O "agora" da lista; meio minuto e fino o bastante sem custar nada. */
const now = ref(Date.now());
const tick = setInterval(() => {
    now.value = Date.now();
}, 30_000);

onUnmounted(() => {
    clearInterval(tick);
});

const dateLocale = computed(() => locale.value.replace('_', '-'));

const parsed = computed(() => parseCron(expression.value));
const spec = computed(() => (parsed.value.ok ? parsed.value.spec : null));
const error = computed(() => (parsed.value.ok ? null : parsed.value.error));
const fields = computed(() =>
    spec.value === null ? [] : describeCron(spec.value),
);

const next = computed(() =>
    spec.value === null
        ? null
        : nextRuns(spec.value, new Date(now.value), 10, zone),
);

/*
| Os textos da explicacao
*/

function fieldName(id: CronFieldId): string {
    return t(`tools.cron-explainer.fields.${id}`);
}

/** Mes e dia da semana saem por extenso; os outros campos, por numero. */
function valueLabel(id: CronFieldId, value: number): string {
    if (id === 'month') {
        return t(`tools.cron-explainer.names.months.${value}`);
    }

    if (id === 'day_of_week') {
        return t(`tools.cron-explainer.names.days.${value}`);
    }

    return String(value);
}

function meaningOf(field: FieldDescription): string {
    const from = valueLabel(field.id, field.from ?? 0);
    const to = valueLabel(field.id, field.to ?? 0);

    switch (field.kind) {
        case 'any':
            return t(`tools.cron-explainer.meanings.any.${field.id}`);
        case 'step_star':
            return t(`tools.cron-explainer.meanings.step_star.${field.id}`, {
                count: field.step ?? 1,
                step: field.step ?? 1,
            });
        case 'step_range':
            return t(`tools.cron-explainer.meanings.step_range.${field.id}`, {
                count: field.step ?? 1,
                step: field.step ?? 1,
                from,
                to,
            });
        case 'range':
            return t('tools.cron-explainer.meanings.range', { from, to });
        case 'at':
            return t('tools.cron-explainer.meanings.at', {
                values: field.values
                    .map((value) => valueLabel(field.id, value))
                    .join(', '),
            });
    }
}

const errorText = computed<string>(() => {
    const current = error.value;

    if (current === null) {
        return '';
    }

    return t(`tools.cron-explainer.errors.${current.code}`, {
        field: current.field === null ? '' : fieldName(current.field),
        token: current.token,
    });
});

/*
| A saida e as acoes
*/

const runs = computed(() => next.value?.runs ?? []);
const exhausted = computed(() => next.value?.exhausted ?? false);

function runText(ms: number): string {
    return formatRun(ms, zone, dateLocale.value);
}

const copyText = computed(() => {
    if (spec.value === null || next.value === null) {
        return '';
    }

    return buildReport({
        expression: spec.value.input,
        zone,
        locale: dateLocale.value,
        runs: next.value.runs.map((run) => run.getTime()),
        exhausted: next.value.exhausted,
        labels: {
            zone: t('tools.cron-explainer.report.zone'),
            runs: t('tools.cron-explainer.report.runs'),
            empty: t('tools.cron-explainer.report.empty'),
        },
    });
});

const hasOutput = computed(() => copyText.value !== '');
const isEmpty = computed(() => expression.value.trim() === '');

const status = computed<string>(() => {
    const feedbackText = copyFeedback.message.value;

    if (feedbackText !== null) {
        return feedbackText;
    }

    if (isEmpty.value) {
        return t('tools.ui.states.empty');
    }

    if (error.value !== null) {
        return errorText.value;
    }

    return t('tools.cron-explainer.status.valid', {
        count: runs.value.length,
        zone,
    });
});

function loadExample(): void {
    copyFeedback.clear();
    expression.value = sample.trim();
}

function clear(): void {
    copyFeedback.clear();
    expression.value = '';
}

function copyReport(): void {
    void copyFeedback.copy(copyText.value);
}
</script>

<template>
    <ToolShell
        split-key="cron-explainer"
        :pane-labels="{
            input: t('tools.cron-explainer.panes.expression'),
            output: t('tools.cron-explainer.panes.runs'),
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
                        @click="copyReport"
                    >
                        <template #icon>
                            <Copy class="size-3.5" />
                        </template>
                    </ToolButton>
                </template>
            </ToolActionBar>
        </template>

        <template #input>
            <div class="flex min-h-0 flex-1 flex-col gap-3 p-3">
                <div class="space-y-1">
                    <label
                        for="cron-expression"
                        class="text-[11px] font-medium tracking-wide text-subtle uppercase"
                    >
                        {{ t('tools.cron-explainer.expression') }}
                    </label>

                    <Input
                        id="cron-expression"
                        v-model="expression"
                        class="font-mono"
                        spellcheck="false"
                        autocomplete="off"
                        autocapitalize="off"
                        :aria-invalid="!isEmpty && error !== null"
                        aria-describedby="cron-expression-help"
                        data-test="cron-expression"
                    />

                    <p
                        id="cron-expression-help"
                        class="text-xs"
                        :class="
                            !isEmpty && error !== null
                                ? 'text-destructive'
                                : 'text-subtle'
                        "
                        role="status"
                        data-test="cron-error"
                    >
                        {{
                            isEmpty || error === null
                                ? t('tools.cron-explainer.hint')
                                : errorText
                        }}
                    </p>
                </div>

                <div
                    v-if="spec !== null"
                    class="min-h-0 flex-1 overflow-auto rounded-lg border"
                >
                    <table
                        class="w-full text-left text-xs"
                        data-test="cron-fields"
                    >
                        <thead class="bg-surface-muted text-subtle">
                            <tr>
                                <th class="px-2 py-1.5 font-medium">
                                    {{ t('tools.cron-explainer.table.field') }}
                                </th>
                                <th class="px-2 py-1.5 font-medium">
                                    {{ t('tools.cron-explainer.table.value') }}
                                </th>
                                <th class="px-2 py-1.5 font-medium">
                                    {{
                                        t('tools.cron-explainer.table.meaning')
                                    }}
                                </th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr
                                v-for="field in fields"
                                :key="field.id"
                                class="border-t border-border"
                                data-test="cron-field"
                                :data-field="field.id"
                            >
                                <th
                                    scope="row"
                                    class="px-2 py-1.5 font-medium whitespace-nowrap"
                                >
                                    {{ fieldName(field.id) }}
                                </th>

                                <td
                                    class="px-2 py-1.5 font-mono whitespace-nowrap"
                                >
                                    {{ spec.fields[field.id].raw }}
                                </td>

                                <td
                                    class="px-2 py-1.5"
                                    data-test="cron-field-meaning"
                                >
                                    {{ meaningOf(field) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>

        <template #output-header>
            <p class="text-xs text-subtle" data-test="cron-zone">
                {{ t('tools.cron-explainer.zone', { zone }) }}
            </p>
        </template>

        <template #output>
            <div class="min-h-0 flex-1 overflow-auto">
                <p
                    v-if="spec === null"
                    role="status"
                    class="flex h-full flex-col items-center justify-center gap-2 p-6 text-center text-sm text-subtle"
                    :data-state="isEmpty ? 'empty' : 'invalid'"
                    data-test="cron-state"
                >
                    {{
                        isEmpty
                            ? t('tools.cron-explainer.states.empty')
                            : t('tools.cron-explainer.states.invalid')
                    }}
                </p>

                <template v-else>
                    <ol class="divide-y divide-border">
                        <li
                            v-for="(run, index) in runs"
                            :key="run.getTime()"
                            class="flex items-baseline gap-3 px-3 py-2"
                            data-test="cron-run"
                        >
                            <span
                                class="w-6 shrink-0 text-right text-xs text-subtle"
                            >
                                {{ index + 1 }}
                            </span>

                            <span class="font-mono text-sm">
                                {{ runText(run.getTime()) }}
                            </span>
                        </li>
                    </ol>

                    <p
                        v-if="exhausted"
                        class="flex items-start gap-2 border-t border-accent/40 bg-accent/5 px-3 py-2 text-xs text-foreground"
                        data-test="cron-exhausted"
                    >
                        <TriangleAlert
                            class="mt-0.5 size-3.5 shrink-0 text-subtle"
                            aria-hidden="true"
                        />
                        {{ t('tools.cron-explainer.exhausted') }}
                    </p>
                </template>
            </div>
        </template>

        <template #status>
            <p class="text-xs text-subtle" data-test="cron-status">
                {{ status }}
            </p>
        </template>
    </ToolShell>
</template>
