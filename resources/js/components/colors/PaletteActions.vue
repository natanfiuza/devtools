<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    Archive,
    Copy,
    Layers,
    Link2,
    Pencil,
    RotateCcw,
    Trash2,
} from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { useCopyFeedback } from '@/composables/useCopyFeedback';
import publicPalettes from '@/routes/colors/palettes';
import palettes from '@/routes/dashboard/colors/palettes';
import type { PaletteEditor, PaletteRow } from '@/types/colors';

/**
 * Acoes de uma paleta, usadas na lista e no cabecalho do editor (F4).
 *
 * Duplicar, arquivar, restaurar e compartilhar sao requisicoes de conta - a
 * pagina publica e a unica coisa que o visitante ve. Compartilhar so existe
 * para paleta publica com slug: o link e o mesmo em todos os locales.
 */

const props = withDefaults(
    defineProps<{
        palette: PaletteRow | PaletteEditor;
        /** O editor ja esta aberto: a lista mostra "Editar". */
        showEdit?: boolean;
        /** Aba Prototipo existe a partir da etapa 9; a lista ja aponta para ela. */
        showPrototype?: boolean;
    }>(),
    { showEdit: true, showPrototype: true },
);

const emit = defineEmits<{
    delete: [palette: PaletteRow | PaletteEditor];
}>();

const { t } = useI18n();
const page = usePage();
const share = useCopyFeedback();
const shareFeedback = share.message;

const editUrl = computed(
    () => palettes.edit.url({ colorPalette: props.palette.id }),
);

const prototypeUrl = computed(
    () =>
        palettes.edit.url(
            { colorPalette: props.palette.id },
            { query: { tab: 'prototype' } },
        ),
);

const publicUrl = computed<string | null>(() => {
    if (props.palette.visibility !== 'public' || props.palette.slug === null) {
        return null;
    }

    return publicPalettes.show.url({
        locale: page.props.localeSlug ?? 'en',
        slug: props.palette.slug,
    });
});

function duplicate(): void {
    router.post(
        palettes.duplicate.url({ colorPalette: props.palette.id }),
        {},
        { preserveScroll: true },
    );
}

function archive(): void {
    router.patch(
        palettes.archive.url({ colorPalette: props.palette.id }),
        {},
        { preserveScroll: true },
    );
}

function restore(): void {
    router.patch(
        palettes.restore.url({ colorPalette: props.palette.id }),
        {},
        { preserveScroll: true },
    );
}

async function copyLink(): Promise<void> {
    if (publicUrl.value === null) {
        return;
    }

    await share.copy(publicUrl.value, t('colors.actions.shared'));
}
</script>

<template>
  <div class="flex flex-col gap-2">
    <div class="flex flex-wrap items-center gap-1">
      <Button
        v-if="showEdit"
        as-child
        variant="ghost"
        size="sm"
      >
        <Link
          :href="editUrl"
          data-test="palette-action-edit"
        >
          <Pencil class="size-4" />
          {{ t('colors.actions.edit') }}
        </Link>
      </Button>

      <Button
        v-if="showPrototype && !palette.archived"
        as-child
        variant="ghost"
        size="sm"
      >
        <Link
          :href="prototypeUrl"
          data-test="palette-action-prototype"
        >
          <Layers class="size-4" />
          {{ t('colors.actions.prototype') }}
        </Link>
      </Button>

      <Button
        variant="ghost"
        size="sm"
        data-test="palette-action-duplicate"
        @click="duplicate"
      >
        <Copy class="size-4" />
        {{ t('colors.actions.duplicate') }}
      </Button>

      <Button
        variant="ghost"
        size="sm"
        :disabled="publicUrl === null"
        :title="
          publicUrl === null
            ? t('colors.actions.share_unavailable')
            : undefined
        "
        data-test="palette-action-share"
        @click="copyLink"
      >
        <Link2 class="size-4" />
        {{ t('colors.actions.share') }}
      </Button>

      <Button
        v-if="!palette.archived"
        variant="ghost"
        size="sm"
        data-test="palette-action-archive"
        @click="archive"
      >
        <Archive class="size-4" />
        {{ t('colors.actions.archive') }}
      </Button>

      <Button
        v-else
        variant="ghost"
        size="sm"
        data-test="palette-action-restore"
        @click="restore"
      >
        <RotateCcw class="size-4" />
        {{ t('colors.actions.restore') }}
      </Button>

      <Button
        variant="ghost"
        size="sm"
        class="text-destructive"
        data-test="palette-action-delete"
        @click="emit('delete', palette)"
      >
        <Trash2 class="size-4" />
        {{ t('colors.actions.delete') }}
      </Button>
    </div>

    <p
      v-if="shareFeedback !== null"
      class="text-xs text-muted-foreground"
      role="status"
      aria-live="polite"
      data-test="palette-share-feedback"
    >
      {{ shareFeedback }}
    </p>
  </div>
</template>
