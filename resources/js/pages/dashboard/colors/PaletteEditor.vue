<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import DeletePaletteDialog from '@/components/colors/DeletePaletteDialog.vue';
import OgImageButton from '@/components/colors/OgImageButton.vue';
import PaletteActions from '@/components/colors/PaletteActions.vue';
import PaletteMetaForm from '@/components/colors/PaletteMetaForm.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { TabsContent, TabsTrigger } from '@/components/ui/tabs';
import { usePaletteOg } from '@/composables/usePaletteOg';
import { parseColor, type PaletteScale } from '@/lib/color';
import { buildFrozenScale } from '@/lib/frozenScale';
import {
    paletteOgSourceFromPayload,
    type PaletteOgSource,
} from '@/lib/paletteOg';
import {
    payloadFromExtracted,
    payloadFromScale,
} from '@/lib/palettePayload';
import type { WorkspaceSnapshot } from '@/lib/paletteSave';
import publicPalettes from '@/routes/colors/palettes';
import palettes from '@/routes/dashboard/colors/palettes';
import GeneratorWorkspace from '@/tools/palette-generator/GeneratorWorkspace.vue';
import type {
    ExtractedColor,
    ExtractionCount,
} from '@/tools/palette-generator/lib/extractContract';
import type { ExtractedPalette } from '@/tools/palette-generator/lib/extractedPalette';
import PrototypePanel from '@/tools/palette-generator/prototype/PrototypePanel.vue';
import type {
    PaletteDetails,
    PaletteEditorProps,
    PalettePayload,
    PaletteRoles,
    PaletteScaleItem,
    PaletteVisibility,
} from '@/types/colors';

/**
 * Editor de uma paleta salva (F4, ADR-123).
 *
 * Reusa o `GeneratorWorkspace` do gerador: a escala vem congelada dos HEX
 * persistidos (`buildFrozenScale`) e a extraida e remontada a partir das cores
 * gravadas, sem recalcular. Salvar manda o payload completo - cores, papeis,
 * tipo e versao - para o servidor, que so valida e grava.
 *
 * A paleta arquivada e somente leitura: o workspace nem monta, para nao dar a
 * impressao de que a edicao vai valer. O caminho de volta e restaurar.
 */

const props = defineProps<PaletteEditorProps>();

const { t, locale } = useI18n();
const page = usePage();

const workspace = ref<InstanceType<typeof GeneratorWorkspace> | null>(null);

const name = ref(props.palette.name);
const description = ref(props.palette.description ?? '');
const visibility = ref<PaletteVisibility>(props.palette.visibility);
const tags = ref<string[]>(props.palette.tags.map((tag) => tag.name));

/** Papeis editaveis na aba Prototipo (ADR-126); o editor e quem persiste. */
const roles = ref<PaletteRoles>(cloneRoles(props.palette.roles));

/** Fonte do cartao social enquanto a pagina nao muda de props (ADR-125). */
const ogSource = computed<PaletteOgSource>(() => ({
    name: props.palette.name,
    description: props.palette.description,
    colors: props.palette.colors,
    roles: props.palette.roles,
}));

const ogImage = usePaletteOg(
    () => props.palette.id,
    () => ogSource.value,
);
const ogBusy = ogImage.busy;
const ogError = ogImage.error;
const ogUploaded = ogImage.uploaded;

onMounted(() => {
    // Paleta publica sem cartao (recem-criada no gerador, p.ex.): gera assim
    // que o editor abre. Depois de cada salvamento com mudanca visual o
    // servidor invalida a OG, e o fluxo de `save` refaz o mesmo caminho.
    if (
        props.palette.visibility === 'public' &&
        !props.palette.has_og &&
        !props.palette.archived
    ) {
        void ogImage.generate();
    }
});

const saving = ref(false);
const localError = ref<string | null>(null);
const serverErrors = ref<Record<string, string>>({});
const deleting = ref(false);

const nameValid = computed(() => {
    const value = name.value.trim();

    return value !== '' && value.length <= props.form.nameMaxLength;
});

const descriptionValid = computed(
    () => description.value.length <= props.form.descriptionMaxLength,
);

const tagsValid = computed(
    () => tags.value.length <= props.form.tagsPerPalette,
);

const canSave = computed(
    () =>
        workspace.value !== null &&
        workspace.value.isEmpty !== true &&
        nameValid.value &&
        descriptionValid.value &&
        tagsValid.value,
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

const formattedDate = computed<string | null>(() => {
    if (props.palette.updated_at === null) {
        return null;
    }

    const date = new Date(props.palette.updated_at);

    if (Number.isNaN(date.getTime())) {
        return null;
    }

    return new Intl.DateTimeFormat(locale.value.replace('_', '-'), {
        dateStyle: 'medium',
    }).format(date);
});

/** Copia rasa dos dois temas: os papeis nunca sao mutados no lugar. */
function cloneRoles(source: PaletteRoles): PaletteRoles {
    return {
        light: { ...source.light },
        dark: { ...source.dark },
    };
}

/** Aba de abertura: a da query quando valida, senao a do tipo salvo. */
function initialTab(): string {
    const url = new URL(page.url, 'http://localhost');
    const tab = url.searchParams.get('tab');

    if (
        tab === 'scale' ||
        tab === 'harmonies' ||
        tab === 'extraction' ||
        tab === 'library' ||
        tab === 'prototype'
    ) {
        return tab;
    }

    return props.palette.kind === 'extracted' ? 'extraction' : 'scale';
}

function scaleItems(): PaletteScaleItem[] {
    return props.palette.colors.items.filter(
        (item): item is PaletteScaleItem => 'tone' in item,
    );
}

function frozenScale(): PaletteScale | null {
    if (props.palette.kind !== 'scale') {
        return null;
    }

    try {
        return buildFrozenScale({
            base: props.palette.colors.base,
            items: scaleItems(),
            algorithmVersion: props.palette.algorithm_version,
        });
    } catch {
        // Dado corrompido fica impossivel de reproduzir; a base vira a fonte.
        return null;
    }
}

/** Extracao salva: pesos e percentuais nao foram gravados (ADR-122). */
function extractedFromPalette(): ExtractedPalette | null {
    if (props.palette.kind !== 'extracted') {
        return null;
    }

    const colors: ExtractedColor[] = [];

    for (const item of props.palette.colors.items) {
        const parsed = parseColor(item.hex);

        if (!parsed.ok) {
            return null;
        }

        colors.push({
            rgba: parsed.color.rgba,
            hex: item.hex,
            weight: 0,
            percentage: 0,
        });
    }

    if (colors.length === 0) {
        return null;
    }

    return {
        colors,
        requested: props.palette.colors_count as ExtractionCount,
        algorithmVersion: props.palette.algorithm_version,
        sufficient: true,
        visiblePixels: 0,
        source:
            props.palette.source ??
            {
                kind: 'file',
                label: t('tools.palette-generator.extraction.source_unknown'),
            },
    };
}

const initialScale = frozenScale();
const initialExtracted = extractedFromPalette();
const tab = initialTab();

/** Erro do servidor que nao pertence aos campos do formulario. */
const extraError = computed<string | null>(() => {
    const fieldErrors = new Set(['name', 'description', 'tags']);

    for (const [field, message] of Object.entries(serverErrors.value)) {
        if (!fieldErrors.has(field)) {
            return message;
        }
    }

    return null;
});

const alert = computed<string | null>(
    () => localError.value ?? extraError.value,
);

function details(): PaletteDetails {
    const trimmed = description.value.trim();

    return {
        name: name.value.trim(),
        description: trimmed === '' ? null : trimmed,
        visibility: visibility.value,
        tags: tags.value,
    };
}

function payloadFromSnapshot(snapshot: WorkspaceSnapshot): PalettePayload {
    if (snapshot.kind === 'extracted' && snapshot.extracted !== null) {
        return payloadFromExtracted(
            snapshot.extracted,
            snapshot.base,
            details(),
        );
    }

    return payloadFromScale(snapshot.scale, details());
}

/**
 * Padroes da aba Prototipo: saem do tipo/base que esta no workspace agora.
 *
 * Editar um papel nao recalcula os demais; "Restaurar padroes" usa este valor.
 * Se o workspace ainda nao montou (ou o dado estiver corrompido), vale o que
 * veio do servidor.
 */
const roleDefaults = computed<PaletteRoles>(() => {
    const current = workspace.value;

    if (current === null) {
        return props.palette.roles;
    }

    try {
        return payloadFromSnapshot(current.snapshot()).roles;
    } catch {
        return props.palette.roles;
    }
});

function save(): void {
    const current = workspace.value;

    if (current === null || !canSave.value || saving.value) {
        return;
    }

    let payload: PalettePayload;

    try {
        payload = payloadFromSnapshot(current.snapshot());
    } catch {
        localError.value = t('colors.editor.error');

        return;
    }

    // Os papeis da aba Prototipo vencem os defaults calculados: sao os que o
    // usuario viu e editou.
    payload.roles = cloneRoles(roles.value);

    saving.value = true;
    localError.value = null;
    serverErrors.value = {};

    router.patch(
        palettes.update.url({ colorPalette: props.palette.id }),
        payload,
        {
            preserveScroll: true,
            onSuccess: () => {
                // Publica: o servidor invalidou o cartao se algo visual
                // mudou; regenerar aqui mantem a OG em dia. Falha no upload
                // nao desfaz o salvamento -- o botao permite tentar de novo.
                if (payload.visibility === 'public') {
                    void ogImage.generate(
                        paletteOgSourceFromPayload(payload),
                    );
                }
            },
            onError: (errors) => {
                serverErrors.value = errors;
            },
            onFinish: () => {
                saving.value = false;
            },
        },
    );
}
</script>

<template>
  <Head :title="palette.name" />

  <div
    class="flex flex-col gap-6"
    data-test="palette-editor"
  >
    <Link
      :href="palettes.index.url()"
      class="inline-flex w-fit items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
      data-test="palette-editor-back"
    >
      <ArrowLeft class="size-4" />
      {{ t('colors.editor.back') }}
    </Link>

    <header class="flex flex-wrap items-start justify-between gap-4">
      <div class="flex min-w-0 flex-col gap-2">
        <div class="flex flex-wrap items-center gap-2">
          <h2
            class="text-xl font-semibold"
            data-test="palette-editor-name"
          >
            {{ palette.name }}
          </h2>
          <Badge variant="outline">
            {{ t(`colors.list.kind_${palette.kind}`) }}
          </Badge>
          <Badge variant="secondary">
            {{
              palette.visibility === 'public'
                ? t('colors.list.badge_public')
                : t('colors.list.badge_private')
            }}
          </Badge>
          <Badge
            v-if="palette.archived"
            variant="destructive"
          >
            {{ t('colors.list.badge_archived') }}
          </Badge>
        </div>

        <p class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
          <span>
            {{
              palette.duplications_count === 0
                ? t('colors.editor.usage_zero')
                : t('colors.editor.usage', {
                  count: palette.duplications_count,
                })
            }}
          </span>
          <span>
            {{ t('colors.editor.colors_count', { count: palette.colors_count }) }}
          </span>
          <span v-if="formattedDate !== null">
            {{ t('colors.editor.updated_at', { date: formattedDate }) }}
          </span>
        </p>

        <Link
          v-if="publicUrl !== null"
          :href="publicUrl"
          class="w-fit text-sm underline-offset-4 hover:underline"
          data-test="palette-editor-public"
        >
          {{ t('colors.editor.public_link') }}
        </Link>
      </div>

      <div class="flex flex-col items-end gap-2">
        <Button
          v-if="!palette.archived"
          type="button"
          size="sm"
          :disabled="!canSave || saving"
          data-test="palette-editor-save"
          @click="save"
        >
          {{
            saving ? t('colors.editor.saving') : t('colors.editor.save')
          }}
        </Button>

        <OgImageButton
          v-if="palette.visibility === 'public' && !palette.archived"
          :has-og="palette.has_og || ogUploaded"
          :busy="ogBusy"
          :error="ogError"
          @generate="ogImage.generate()"
        />

        <PaletteActions
          v-if="!palette.archived"
          :palette="palette"
          :show-edit="false"
          :show-prototype="false"
          @delete="deleting = true"
        />
      </div>
    </header>

    <p
      v-if="alert !== null"
      class="rounded-lg border border-destructive/40 bg-destructive/5 px-3 py-2 text-sm text-destructive"
      role="alert"
      data-test="palette-editor-error"
    >
      {{ alert }}
    </p>

    <section
      v-if="palette.archived"
      class="flex flex-col gap-4 rounded-xl border border-dashed border-border p-4"
      data-test="palette-editor-archived"
    >
      <div>
        <h3 class="text-sm font-semibold">
          {{ t('colors.editor.archived_title') }}
        </h3>
        <p class="mt-1 text-sm text-muted-foreground">
          {{ t('colors.editor.archived_description') }}
        </p>
      </div>

      <div
        class="flex h-14 overflow-hidden rounded-lg border border-border"
        role="img"
        :aria-label="t('colors.list.preview_alt', { name: palette.name })"
      >
        <span
          v-for="(item, index) in palette.colors.items"
          :key="`${item.hex}-${index}`"
          class="h-full flex-1"
          :style="{ backgroundColor: item.hex }"
          :title="item.hex"
        />
      </div>

      <PaletteActions
        :palette="palette"
        :show-edit="false"
        :show-prototype="false"
        @delete="deleting = true"
      />
    </section>

    <template v-else>
      <PaletteMetaForm
        v-model:name="name"
        v-model:description="description"
        v-model:visibility="visibility"
        v-model:tags="tags"
        :limits="form"
        :suggestions="props.tags"
        :name-error="serverErrors.name ?? null"
        :description-error="serverErrors.description ?? null"
        :tags-error="serverErrors.tags ?? null"
      />

      <GeneratorWorkspace
        ref="workspace"
        :image="image"
        :library="props.library"
        :initial-base="palette.colors.base"
        :initial-scale="initialScale"
        :initial-extracted="initialExtracted"
        :initial-tab="tab"
      >
        <template #tabs-extra>
          <TabsTrigger
            value="prototype"
            data-test="palette-editor-tab-prototype"
          >
            {{ t('colors.prototype.tab') }}
          </TabsTrigger>
        </template>

        <template #panels-extra>
          <TabsContent
            value="prototype"
            class="mt-3"
          >
            <PrototypePanel
              v-model:roles="roles"
              :defaults="roleDefaults"
            />
          </TabsContent>
        </template>
      </GeneratorWorkspace>
    </template>

    <DeletePaletteDialog
      :open="deleting"
      :palette-id="palette.id"
      :palette-name="palette.name"
      @close="deleting = false"
    />
  </div>
</template>
