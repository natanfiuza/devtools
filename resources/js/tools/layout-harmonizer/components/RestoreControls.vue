<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { RotateCcw } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { LayoutTheme } from '../engine';

/**
 * Restaurar a geracao automatica por tema e no sistema.
 *
 * O escopo de token fica na propria celula. So confirma quando ha o que
 * perder naquele escopo; sem edicoes o botao fica desabilitado.
 */

type Scope = LayoutTheme | 'all';

const props = defineProps<{
    counts: Record<LayoutTheme, number>;
    total: number;
    restoreTheme: (theme: LayoutTheme) => void;
    restoreAll: () => void;
}>();

const { t } = useI18n();

const pending = ref<Scope | null>(null);

const pendingCount = computed(() => {
    if (pending.value === null) {
        return 0;
    }

    return pending.value === 'all' ? props.total : props.counts[pending.value];
});

function countOf(scope: LayoutTheme): number {
    return props.counts[scope];
}

function ask(scope: Scope): void {
    const count = scope === 'all' ? props.total : props.counts[scope];

    // Confirmar so quando ha mudancas manuais no escopo.
    if (count === 0) {
        return;
    }

    pending.value = scope;
}

function confirm(): void {
    const scope = pending.value;

    pending.value = null;

    if (scope === 'all') {
        props.restoreAll();

        return;
    }

    if (scope !== null) {
        props.restoreTheme(scope);
    }
}
</script>

<template>
  <div
    class="flex flex-wrap items-center gap-2"
    role="group"
    :aria-label="t('tools.layout-harmonizer.a11y.restore')"
    data-test="restore-controls"
  >
    <Button
      type="button"
      variant="outline"
      size="sm"
      :disabled="countOf('light') === 0"
      data-test="restore-theme-light"
      @click="ask('light')"
    >
      <RotateCcw class="size-4" />
      {{
        t('tools.layout-harmonizer.restore.theme', {
          theme: t('tools.layout-harmonizer.view.light'),
          count: countOf('light'),
        })
      }}
    </Button>
    <Button
      type="button"
      variant="outline"
      size="sm"
      :disabled="countOf('dark') === 0"
      data-test="restore-theme-dark"
      @click="ask('dark')"
    >
      <RotateCcw class="size-4" />
      {{
        t('tools.layout-harmonizer.restore.theme', {
          theme: t('tools.layout-harmonizer.view.dark'),
          count: countOf('dark'),
        })
      }}
    </Button>
    <Button
      type="button"
      variant="outline"
      size="sm"
      :disabled="total === 0"
      data-test="restore-all"
      @click="ask('all')"
    >
      <RotateCcw class="size-4" />
      {{ t('tools.layout-harmonizer.restore.all', { count: total }) }}
    </Button>

    <Dialog
      :open="pending !== null"
      @update:open="
        (open) => {
          if (!open) {
            pending = null;
          }
        }
      "
    >
      <DialogContent data-test="restore-dialog">
        <DialogHeader>
          <DialogTitle>
            {{ t('tools.layout-harmonizer.restore.confirm_title') }}
          </DialogTitle>
          <DialogDescription>
            {{
              t('tools.layout-harmonizer.restore.confirm_body', {
                count: pendingCount,
              })
            }}
          </DialogDescription>
        </DialogHeader>
        <DialogFooter>
          <Button
            type="button"
            variant="outline"
            data-test="restore-cancel"
            @click="pending = null"
          >
            {{ t('tools.layout-harmonizer.restore.cancel') }}
          </Button>
          <Button
            type="button"
            data-test="restore-confirm"
            @click="confirm"
          >
            {{ t('tools.layout-harmonizer.restore.confirm') }}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </div>
</template>
