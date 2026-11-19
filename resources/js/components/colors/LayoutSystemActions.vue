<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Archive, Copy, Link2, Pencil, RotateCcw, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { useCopyFeedback } from '@/composables/useCopyFeedback';
import publicSystems from '@/routes/colors/systems';
import systems from '@/routes/dashboard/colors/systems';
import type { LayoutSystemEditor, LayoutSystemRow } from '@/types/layoutSystem';

/**
 * Acoes de um sistema de layout, na lista e no cabecalho do editor (F4).
 *
 * Duplicar, arquivar, restaurar e compartilhar sao requisicoes de conta. O link
 * publico so existe para item publico com slug; arquivar revoga a URL, entao o
 * botao fica desabilitado com o motivo.
 */

const props = withDefaults(
    defineProps<{
        system: LayoutSystemRow | LayoutSystemEditor;
        /** O editor ja esta aberto: a lista mostra "Editar". */
        showEdit?: boolean;
    }>(),
    { showEdit: true },
);

const emit = defineEmits<{
    delete: [system: LayoutSystemRow | LayoutSystemEditor];
}>();

const { t } = useI18n();
const page = usePage();
const share = useCopyFeedback();
const shareFeedback = share.message;

const editUrl = computed(() =>
    systems.edit.url({ layoutSystem: props.system.id }),
);

const publicUrl = computed<string | null>(() => {
    if (
        props.system.visibility !== 'public' ||
        props.system.slug === null ||
        props.system.archived
    ) {
        return null;
    }

    return publicSystems.show.url({
        locale: page.props.localeSlug ?? 'en',
        slug: props.system.slug,
    });
});

function duplicate(): void {
    router.post(
        systems.duplicate.url({ layoutSystem: props.system.id }),
        {},
        { preserveScroll: true },
    );
}

function archive(): void {
    router.patch(
        systems.archive.url({ layoutSystem: props.system.id }),
        {},
        { preserveScroll: true },
    );
}

function restore(): void {
    router.patch(
        systems.restore.url({ layoutSystem: props.system.id }),
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
            <Button v-if="showEdit" as-child variant="ghost" size="sm">
                <Link :href="editUrl" data-test="layout-system-action-edit">
                    <Pencil class="size-4" />
                    {{ t('colors.actions.edit') }}
                </Link>
            </Button>

            <Button
                variant="ghost"
                size="sm"
                data-test="layout-system-action-duplicate"
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
                        ? t('colors.systems.actions.share_unavailable')
                        : undefined
                "
                data-test="layout-system-action-share"
                @click="copyLink"
            >
                <Link2 class="size-4" />
                {{ t('colors.actions.share') }}
            </Button>

            <Button
                v-if="!system.archived"
                variant="ghost"
                size="sm"
                data-test="layout-system-action-archive"
                @click="archive"
            >
                <Archive class="size-4" />
                {{ t('colors.actions.archive') }}
            </Button>

            <Button
                v-else
                variant="ghost"
                size="sm"
                data-test="layout-system-action-restore"
                @click="restore"
            >
                <RotateCcw class="size-4" />
                {{ t('colors.actions.restore') }}
            </Button>

            <Button
                variant="ghost"
                size="sm"
                class="text-destructive"
                data-test="layout-system-action-delete"
                @click="emit('delete', system)"
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
            data-test="layout-system-share-feedback"
        >
            {{ shareFeedback }}
        </p>
    </div>
</template>
