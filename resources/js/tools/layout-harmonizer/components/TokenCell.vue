<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Lock, LockOpen, Pencil } from '@lucide/vue';
import CopyStatusIcon from '@/components/colors/CopyStatusIcon.vue';
import { cssColor } from '@/components/colors/lib/style';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Popover, PopoverTrigger } from '@/components/ui/popover';
import { useCopyConfirmation } from '@/composables/useCopyFeedback';
import { formatColor, type Rgba } from '@/lib/color';
import type { LayoutPairCheck, LayoutToken } from '../engine';
import { formatRatio } from '../lib/boardView';
import TokenEditorPopover from './TokenEditorPopover.vue';

/**
 * Uma linha do quadro semantico.
 *
 * O swatch mostra a cor real do token e fica oculto de leitores; todo texto
 * (chave, finalidade, valores, selos) fica na superficie da interface, nunca
 * por cima da cor gerada - assim um par com contraste ruim nao some.
 *
 * Na F3 a celula ganhou o bloqueio (no cabecalho), o editor em popover e um
 * indicador discreto quando ha sugestao de contraste para o token.
 */

const props = withDefaults(
    defineProps<{
        token: LayoutToken;
        purpose: string;
        checks: LayoutPairCheck[];
        copy: (value: string, success?: string) => Promise<boolean>;
        locked?: boolean;
        /** Existe override (manual, aceito ou de bloqueio) para o token. */
        edited?: boolean;
        suggested?: boolean;
        editable?: boolean;
        applyColor?: (color: Rgba) => void;
        toggleLock?: () => void;
        restoreColor?: () => void;
    }>(),
    {
        locked: false,
        edited: false,
        suggested: false,
        editable: true,
        applyColor: () => {},
        toggleLock: () => {},
        restoreColor: () => {},
    },
);

const { t, locale } = useI18n();
const { copied: hexCopied, confirm: confirmHex } = useCopyConfirmation();
const { copied: oklchCopied, confirm: confirmOklch } = useCopyConfirmation();

const editorOpen = ref(false);

const oklch = computed(() => formatColor(props.token.rgba, 'oklch'));

const canRestore = computed(() => props.locked || props.edited);

function ratio(value: number): string {
    return formatRatio(value, locale.value);
}

function against(background: LayoutPairCheck['background']): string {
    return t('tools.layout-harmonizer.contrast.against', { background });
}

function applyFromEditor(color: Rgba): void {
    props.applyColor(color);
}

function restoreFromEditor(): void {
    props.restoreColor();
    editorOpen.value = false;
}

async function copyHex(): Promise<void> {
    if (
        await props.copy(
            props.token.hex,
            t('tools.layout-harmonizer.copy.hex_copied', {
                token: props.token.id,
            }),
        )
    ) {
        confirmHex();
    }
}

async function copyOklch(): Promise<void> {
    if (
        await props.copy(
            oklch.value,
            t('tools.layout-harmonizer.copy.oklch_copied', {
                token: props.token.id,
            }),
        )
    ) {
        confirmOklch();
    }
}
</script>

<template>
    <li
        class="flex flex-col gap-3 rounded-lg border p-3 sm:flex-row sm:items-start"
        data-test="token-cell"
        :data-token="token.id"
    >
        <span
            class="size-10 shrink-0 rounded-md border"
            :style="{ backgroundColor: cssColor(token.rgba) }"
            aria-hidden="true"
            data-test="token-swatch"
        />

        <div class="min-w-0 flex-1 space-y-1">
            <div class="flex flex-wrap items-center gap-2">
                <code class="font-mono text-sm font-medium">{{
                    token.id
                }}</code>
                <Badge variant="secondary">
                    {{
                        t(`tools.layout-harmonizer.provenance.${token.source}`)
                    }}
                </Badge>
                <span
                    v-if="token.clipped"
                    :title="t('tools.layout-harmonizer.clipped.note')"
                >
                    <Badge variant="outline">
                        {{ t('tools.layout-harmonizer.clipped.badge') }}
                    </Badge>
                </span>
                <Badge
                    v-if="suggested"
                    variant="outline"
                    data-test="token-suggested"
                >
                    {{ t('tools.layout-harmonizer.suggestions.badge') }}
                </Badge>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    :aria-pressed="locked"
                    :aria-label="
                        locked
                            ? t('tools.layout-harmonizer.lock.unlock_aria', {
                                  token: token.id,
                              })
                            : t('tools.layout-harmonizer.lock.lock_aria', {
                                  token: token.id,
                              })
                    "
                    :disabled="!editable"
                    data-test="token-lock"
                    @click="toggleLock"
                >
                    <component :is="locked ? Lock : LockOpen" class="size-4" />
                </Button>
            </div>

            <p class="text-xs text-muted-foreground">{{ purpose }}</p>

            <div
                class="flex flex-wrap items-center gap-x-4 gap-y-1 font-mono text-xs"
            >
                <span data-test="token-hex">{{ token.hex }}</span>
                <span class="text-muted-foreground" data-test="token-oklch">
                    {{ oklch }}
                </span>
            </div>

            <ul v-if="checks.length" class="flex flex-wrap gap-1.5 pt-1">
                <li
                    v-for="check in checks"
                    :key="check.background"
                    :title="against(check.background)"
                >
                    <Badge
                        :variant="
                            check.verdict.aaNormal ? 'default' : 'destructive'
                        "
                    >
                        {{
                            check.verdict.aaNormal
                                ? t('tools.layout-harmonizer.contrast.aa')
                                : t('tools.layout-harmonizer.contrast.aa_fail')
                        }}
                        {{ ratio(check.verdict.ratio) }}
                    </Badge>
                </li>
            </ul>
        </div>

        <div class="flex shrink-0 gap-1">
            <Popover v-model:open="editorOpen">
                <PopoverTrigger as-child>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        :aria-label="
                            t('tools.layout-harmonizer.edit.open_aria', {
                                token: token.id,
                            })
                        "
                        :disabled="!editable"
                        data-test="token-edit"
                    >
                        <Pencil class="size-4" />
                    </Button>
                </PopoverTrigger>
                <TokenEditorPopover
                    v-if="editorOpen"
                    :token="token"
                    :locked="locked"
                    :can-restore="canRestore"
                    @apply="applyFromEditor"
                    @toggle-lock="toggleLock"
                    @restore="restoreFromEditor"
                    @close="editorOpen = false"
                />
            </Popover>
            <Button
                type="button"
                variant="ghost"
                size="icon-sm"
                :aria-label="
                    t('tools.layout-harmonizer.copy.hex_aria', {
                        token: token.id,
                    })
                "
                data-test="copy-hex"
                @click="copyHex"
            >
                <CopyStatusIcon :copied="hexCopied" />
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="icon-sm"
                :aria-label="
                    t('tools.layout-harmonizer.copy.oklch_aria', {
                        token: token.id,
                    })
                "
                data-test="copy-oklch"
                @click="copyOklch"
            >
                <CopyStatusIcon :copied="oklchCopied" />
            </Button>
        </div>
    </li>
</template>
