<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import DeleteLayoutSystemDialog from '@/components/colors/DeleteLayoutSystemDialog.vue';
import LayoutSystemActions from '@/components/colors/LayoutSystemActions.vue';
import LayoutSystemMetaForm from '@/components/colors/LayoutSystemMetaForm.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useCopyFeedback } from '@/composables/useCopyFeedback';
import publicSystems from '@/routes/colors/systems';
import systems from '@/routes/dashboard/colors/systems';
import LayoutHarmonizerPanel from '@/tools/layout-harmonizer/LayoutHarmonizerPanel.vue';
import LayoutExportPanel from '@/tools/layout-harmonizer/components/LayoutExportPanel.vue';
import LayoutSystemPrototypePanel from '@/tools/layout-harmonizer/components/LayoutSystemPrototypePanel.vue';
import ThemeComparison from '@/tools/layout-harmonizer/components/ThemeComparison.vue';
import type { LayoutExportOrigin } from '@/tools/layout-harmonizer/export/types';
import {
    strategyMessageKey,
    type LayoutSystemDetails,
} from '@/tools/layout-harmonizer/lib/save';
import {
    boardsFromSnapshot,
    hydrationMode,
    type LayoutSystemStateV1,
} from '@/tools/layout-harmonizer/lib/state';
import type {
    LayoutSystemEditorProps,
    LayoutSystemVisibility,
} from '@/types/layoutSystem';

/**
 * Editor de um sistema de layout salvo (F4, ADR-168/ADR-169).
 *
 * O painel hidrata o quadro a partir do estado gravado - sem regenerar - e o
 * salvamento (PATCH) sai do modal, com os metadados deste formulario como
 * valores iniciais. A baseline da guarda e o que veio do servidor: editar o
 * quadro ou os metadados pede confirmacao antes de sair, e salvar libera.
 *
 * O sistema arquivado e somente leitura: os quadros aparecem congelados e a
 * edicao fica com o ciclo de vida (restaurar ou excluir).
 */

const props = defineProps<LayoutSystemEditorProps>();

const { t, locale } = useI18n();
const page = usePage();
const copyFeedback = useCopyFeedback();

const panel = ref<InstanceType<typeof LayoutHarmonizerPanel> | null>(null);

const name = ref(props.system.name);
const description = ref(props.system.description ?? '');
const visibility = ref<LayoutSystemVisibility>(props.system.visibility);
const tags = ref<string[]>(props.system.tags.map((tag) => tag.name));

/** Estado exibido; a regeneracao explicita troca o par e remonta o painel. */
const state = ref<LayoutSystemStateV1>(props.system.state);
const panelKey = ref(0);

const deleting = ref(false);

const details = computed<LayoutSystemDetails>(() => ({
    name: name.value,
    description: description.value,
    visibility: visibility.value,
    tags: tags.value,
}));

/** Metadados diferentes do gravado tambem seguram a navegacao. */
function metaDirty(): boolean {
    const system = props.system;

    return (
        name.value.trim() !== system.name ||
        description.value.trim() !== (system.description ?? '') ||
        visibility.value !== system.visibility ||
        tags.value.length !== system.tags.length ||
        tags.value.some((tag, index) => tag !== system.tags[index]?.name)
    );
}

// O PATCH devolve as props novas: os campos acompanham o que foi gravado e a
// baseline da guarda e atualizada pelo proprio painel.
watch(
    () => props.system,
    (system) => {
        name.value = system.name;
        description.value = system.description ?? '';
        visibility.value = system.visibility;
        tags.value = system.tags.map((tag) => tag.name);
        state.value = system.state;
    },
);

const mode = computed(() => hydrationMode(props.system.state));

/** Somente leitura: arquivado ou versao que o editor nao sabe regravar. */
const readOnly = computed(
    () => props.system.archived || mode.value === 'readonly',
);

const boards = computed(() => boardsFromSnapshot(props.system.state.snapshot));

/**
 * Origem do documento exportado (F5): so o credito **congelado** de paleta
 * publica conta; a paleta ao vivo pode ter mudado de nome ou visibilidade.
 */
const exportOrigin = computed<LayoutExportOrigin | null>(() => {
    const source = props.system.credit.source;

    if (source?.palette_public !== true) {
        return null;
    }

    const paletteName = source.palette_name;

    if (paletteName === undefined || paletteName.trim() === '') {
        return null;
    }

    return { name: paletteName, public: true };
});

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

const strategyLabel = computed(() =>
    t(strategyMessageKey(props.system.state.inputs.strategy)),
);

const formattedDate = computed<string | null>(() => {
    if (props.system.updated_at === null) {
        return null;
    }

    const date = new Date(props.system.updated_at);

    if (Number.isNaN(date.getTime())) {
        return null;
    }

    return new Intl.DateTimeFormat(locale.value.replace('_', '-'), {
        dateStyle: 'medium',
    }).format(date);
});

function openSave(): void {
    panel.value?.openSave();
}

function onRegenerated(next: LayoutSystemStateV1): void {
    state.value = next;
    panelKey.value += 1;
}
</script>

<template>
    <Head :title="system.name" />

    <div class="flex flex-col gap-6" data-test="layout-system-editor">
        <Link
            :href="systems.index.url()"
            class="inline-flex w-fit items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
            data-test="layout-system-editor-back"
        >
            <ArrowLeft class="size-4" />
            {{ t('colors.systems.editor.back') }}
        </Link>

        <header class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex min-w-0 flex-col gap-2">
                <div class="flex flex-wrap items-center gap-2">
                    <h2
                        class="text-xl font-semibold"
                        data-test="layout-system-editor-name"
                    >
                        {{ system.name }}
                    </h2>
                    <Badge variant="outline">{{ strategyLabel }}</Badge>
                    <Badge variant="secondary">
                        {{
                            system.visibility === 'public'
                                ? t('colors.list.badge_public')
                                : t('colors.list.badge_private')
                        }}
                    </Badge>
                    <Badge v-if="system.archived" variant="destructive">
                        {{ t('colors.list.badge_archived') }}
                    </Badge>
                </div>

                <p
                    class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground"
                >
                    <span data-test="layout-system-editor-usage">
                        {{
                            system.duplications_count === 0
                                ? t('colors.list.usage_zero')
                                : t('colors.list.usage', {
                                      count: system.duplications_count,
                                  })
                        }}
                    </span>
                    <span data-test="layout-system-editor-tokens">
                        {{
                            t('colors.systems.list.tokens_count', {
                                count: system.tokens_count,
                            })
                        }}
                    </span>
                    <span v-if="formattedDate !== null">
                        {{
                            t('colors.list.updated_at', { date: formattedDate })
                        }}
                    </span>
                </p>

                <Link
                    v-if="publicUrl !== null"
                    :href="publicUrl"
                    class="w-fit text-sm underline-offset-4 hover:underline"
                    data-test="layout-system-editor-public"
                >
                    {{ t('colors.systems.editor.public_link') }}
                </Link>

                <p
                    v-if="!system.archived && mode === 'readonly'"
                    class="max-w-2xl text-sm text-muted-foreground"
                    data-test="layout-system-editor-readonly"
                >
                    {{ t('tools.layout-harmonizer.lifecycle.readonly_notice') }}
                </p>
            </div>

            <div class="flex flex-col items-end gap-2">
                <Button
                    v-if="!system.archived"
                    type="button"
                    size="sm"
                    :disabled="readOnly"
                    data-test="layout-system-editor-save"
                    @click="openSave"
                >
                    {{ t('colors.editor.save') }}
                </Button>

                <LayoutSystemActions
                    :system="system"
                    :show-edit="false"
                    @delete="deleting = true"
                />
            </div>
        </header>

        <section
            v-if="system.archived"
            class="flex flex-col gap-4 rounded-xl border border-dashed border-border p-4"
            data-test="layout-system-editor-archived"
        >
            <div>
                <h3 class="text-sm font-semibold">
                    {{ t('colors.systems.editor.archived_title') }}
                </h3>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ t('colors.systems.editor.archived_description') }}
                </p>
            </div>

            <ThemeComparison
                :light="boards.light"
                :dark="boards.dark"
                :copy="copyFeedback.copy"
                :editable="false"
            />
        </section>

        <LayoutExportPanel
            v-if="system.archived"
            :state="state"
            :name="system.name"
            authenticated
            :mode="mode"
            read-only
            :origin="exportOrigin"
            :export-messages="exportMessages"
            :export-limits="exportLimits"
        />

        <template v-else>
            <LayoutSystemMetaForm
                v-model:name="name"
                v-model:description="description"
                v-model:visibility="visibility"
                v-model:tags="tags"
                :limits="form"
                :suggestions="props.tags"
            />

            <LayoutHarmonizerPanel
                ref="panel"
                :key="panelKey"
                :state="state"
                authenticated
                save-mode="update"
                :system-id="system.id"
                :initial-details="details"
                :limits="form"
                :quota="limits"
                :account-tags="props.tags"
                guard
                :show-save-button="false"
                :extra-dirty="metaDirty"
                :export-messages="exportMessages"
                :export-limits="exportLimits"
                :export-origin="exportOrigin"
                @regenerated="onRegenerated"
            />

            <LayoutSystemPrototypePanel :state="state" />
        </template>

        <DeleteLayoutSystemDialog
            :open="deleting"
            :system-id="system.id"
            :system-name="system.name"
            @close="deleting = false"
        />
    </div>
</template>
