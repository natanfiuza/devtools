<script setup lang="ts">
import { computed, ref, useId } from 'vue';
import { useI18n } from 'vue-i18n';
import { Lock, LockOpen, RotateCcw } from '@lucide/vue';
import ColorInputField from '@/components/colors/ColorInputField.vue';
import { cssColor } from '@/components/colors/lib/style';
import { Button } from '@/components/ui/button';
import { PopoverContent } from '@/components/ui/popover';
import { useColorState } from '@/composables/useColorState';
import { formatColor, parseColor, type Rgba } from '@/lib/color';
import type { LayoutToken } from '../engine';

/**
 * Editor de um token, dentro do popover da celula.
 *
 * Trabalha numa copia local: so "Aplicar" escreve no quadro. O token e sempre
 * opaco, entao o alpha escolhido no picker e ignorado de proposito, com aviso.
 * O bloqueio age imediatamente; restaurar pede confirmacao dentro do popover,
 * porque apaga o override e o bloqueio do token (CONTRATO-TECNICO/ADR-167).
 * O id do campo vem de `useId`: dois editores abertos nao repetem id.
 */

const props = defineProps<{
    token: LayoutToken;
    locked: boolean;
    canRestore: boolean;
}>();

const emit = defineEmits<{
    apply: [color: Rgba];
    toggleLock: [];
    restore: [];
    close: [];
}>();

const { t } = useI18n();

const picker = useColorState({ initial: props.token.rgba });

const oklch = computed(() => formatColor(picker.rgba.value, 'oklch'));

const textValid = computed(() => parseColor(picker.text.value).ok);

const alphaIgnored = computed(() => picker.rgba.value.a < 1);

const fieldId = `layout-token-color-value-${useId()}`;

const confirmingRestore = ref(false);

function apply(): void {
    if (!textValid.value) {
        return;
    }

    emit('apply', { ...picker.rgba.value, a: 1 });
    emit('close');
}

function confirmRestore(): void {
    confirmingRestore.value = false;
    emit('restore');
}
</script>

<template>
  <PopoverContent
    align="end"
    class="w-80 space-y-3"
    data-test="token-editor"
  >
    <div class="space-y-1">
      <h3 class="text-sm font-medium">
        {{
          t('tools.layout-harmonizer.edit.title', {
            token: token.id,
          })
        }}
      </h3>
      <p class="text-xs text-muted-foreground">
        {{ t(`tools.layout-harmonizer.tokens.${token.id}`) }}
      </p>
    </div>

    <div class="flex items-center gap-3">
      <span
        class="size-10 shrink-0 rounded-md border"
        :style="{ backgroundColor: cssColor(picker.rgba.value) }"
        aria-hidden="true"
      />
      <div class="min-w-0 text-xs">
        <p
          class="font-mono"
          data-test="token-editor-hex"
        >
          {{ token.hex }}
        </p>
        <p class="font-mono text-muted-foreground">
          {{ oklch }}
        </p>
      </div>
    </div>

    <ColorInputField
      :id="fieldId"
      :model-value="picker.text.value"
      :error="picker.error.value"
      test-id="token-color-input"
      @update="picker.setFromText($event)"
      @finalize="picker.finalizeText()"
    />

    <p
      v-if="alphaIgnored"
      class="text-xs text-muted-foreground"
      data-test="token-editor-alpha"
    >
      {{ t('tools.layout-harmonizer.edit.alpha_ignored') }}
    </p>

    <div class="flex flex-wrap items-center gap-2">
      <Button
        type="button"
        size="sm"
        :disabled="!textValid"
        data-test="token-editor-apply"
        @click="apply"
      >
        {{ t('tools.layout-harmonizer.edit.apply') }}
      </Button>
      <Button
        type="button"
        variant="outline"
        size="sm"
        data-test="token-editor-cancel"
        @click="emit('close')"
      >
        {{ t('tools.layout-harmonizer.edit.cancel') }}
      </Button>
      <Button
        type="button"
        variant="ghost"
        size="sm"
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
        data-test="token-editor-lock"
        @click="emit('toggleLock')"
      >
        <component
          :is="locked ? Lock : LockOpen"
          class="size-4"
        />
        {{
          locked
            ? t('tools.layout-harmonizer.lock.unlock')
            : t('tools.layout-harmonizer.lock.lock')
        }}
      </Button>
      <Button
        type="button"
        variant="ghost"
        size="sm"
        :disabled="!canRestore"
        :aria-label="
          t('tools.layout-harmonizer.restore.token_aria', {
            token: token.id,
          })
        "
        data-test="token-editor-restore"
        @click="confirmingRestore = !confirmingRestore"
      >
        <RotateCcw class="size-4" />
        {{ t('tools.layout-harmonizer.restore.token') }}
      </Button>
    </div>

    <div
      v-if="confirmingRestore"
      class="space-y-2 rounded-md border border-destructive/40 p-2"
      data-test="token-restore-confirm"
    >
      <p class="text-xs font-medium">
        {{ t('tools.layout-harmonizer.restore.confirm_title') }}
      </p>
      <p class="text-xs text-muted-foreground">
        {{
          t('tools.layout-harmonizer.restore.confirm_body', {
            count: 1,
          })
        }}
      </p>
      <div class="flex gap-2">
        <Button
          type="button"
          variant="outline"
          size="sm"
          data-test="token-restore-cancel"
          @click="confirmingRestore = false"
        >
          {{ t('tools.layout-harmonizer.restore.cancel') }}
        </Button>
        <Button
          type="button"
          variant="destructive"
          size="sm"
          data-test="token-restore-confirm-button"
          @click="confirmRestore"
        >
          {{ t('tools.layout-harmonizer.restore.confirm') }}
        </Button>
      </div>
    </div>
  </PopoverContent>
</template>
