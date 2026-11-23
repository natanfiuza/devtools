<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { router } from '@inertiajs/vue3';
import type { RequestPayload } from '@inertiajs/core';
import InputError from '@/components/InputError.vue';
import TagInput from '@/components/colors/TagInput.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import systems from '@/routes/dashboard/colors/systems';
import type {
    LayoutSystemLimits,
    LayoutSystemTag,
    LayoutSystemVisibility,
} from '@/types/layoutSystem';
import {
    layoutSystemPayload,
    suggestCopyName,
    type LayoutMessageTranslator,
    type LayoutSystemDetails,
    type LayoutSystemSaveSource,
} from '../lib/save';

/**
 * Modal de salvar o sistema de layout (F4, ADR-168).
 *
 * O workspace entrega o envelope pronto; o modal so acrescenta nome,
 * descricao, visibilidade e tags. Nome/descricao usam os limites do servidor,
 * e a validacao final (estado, cota, origem) continua no servidor. No editor,
 * "Salvar como novo" envia o mesmo corpo para a criacao e o servidor redireciona
 * ao sistema novo, que passa a ser o vinculado.
 */

const props = defineProps<{
    open: boolean;
    /** `update` no editor vinculado; `create` no tool e em "salvar como novo". */
    mode: 'create' | 'update';
    /** ULID do sistema a atualizar (modo update). */
    systemId?: string | null;
    /** Envelope atual; `null` mantem o envio desabilitado. */
    source: LayoutSystemSaveSource | null;
    /** Nome sugerido na criacao. */
    suggestedName: string;
    /** Metadados correntes no editor; vazio na criacao. */
    initial?: LayoutSystemDetails | null;
    limits: LayoutSystemLimits;
    /** Tags existentes da conta, sugeridas no campo. */
    accountTags: LayoutSystemTag[];
    /** CTA da paleta: a FK so viaja enquanto a paleta for acessivel. */
    originPaletteId?: string | null;
    /** Libera a guarda de navegacao antes de enviar (editor). */
    beforeSave?: (() => void) | null;
}>();

const emit = defineEmits<{
    close: [];
    saved: [];
}>();

const { t } = useI18n();

const name = ref('');
const description = ref('');
const visibility = ref<LayoutSystemVisibility>('private');
const tags = ref<string[]>([]);
const serverErrors = ref<Record<string, string>>({});
const saving = ref(false);

const translator: LayoutMessageTranslator = (key, named) => t(key, named ?? {});

function reset(): void {
    name.value = props.initial?.name ?? props.suggestedName;
    description.value = props.initial?.description ?? '';
    visibility.value = props.initial?.visibility ?? 'private';
    tags.value = [...(props.initial?.tags ?? [])];
    serverErrors.value = {};
    saving.value = false;
}

watch(
    () => props.open,
    (open) => {
        if (open) {
            reset();
        }
    },
);

// Montado ja aberto (ou reaberto pelo pai) comeca com os campos limpos.
reset();

const nameError = computed<string | null>(() => {
    const value = name.value.trim();

    if (value === '') {
        return t('tools.layout-harmonizer.save.name_required');
    }

    if (value.length > props.limits.nameMaxLength) {
        return t('tools.layout-harmonizer.save.name_too_long', {
            max: props.limits.nameMaxLength,
        });
    }

    return serverErrors.value.name ?? null;
});

const descriptionError = computed<string | null>(() => {
    if (description.value.length > props.limits.descriptionMaxLength) {
        return t('tools.layout-harmonizer.save.description_too_long', {
            max: props.limits.descriptionMaxLength,
        });
    }

    return serverErrors.value.description ?? null;
});

const tagsError = computed<string | null>(
    () => serverErrors.value.tags ?? serverErrors.value['tags.0'] ?? null,
);

/** Erros de envelope/cota/origem no lugar do campo. */
const formErrors = computed<string[]>(() =>
    Object.entries(serverErrors.value)
        .filter(
            ([key]) =>
                !['name', 'description', 'tags'].includes(key) &&
                !key.startsWith('tags.'),
        )
        .map(([, message]) => message),
);

const canSubmit = computed(
    () =>
        !saving.value &&
        props.source !== null &&
        nameError.value === null &&
        descriptionError.value === null,
);

function details(nextName: string): LayoutSystemDetails {
    return {
        name: nextName,
        description: description.value,
        visibility: visibility.value,
        tags: tags.value,
    };
}

function submit(kind: 'save' | 'copy'): void {
    const source = props.source;

    if (source === null || !canSubmit.value) {
        return;
    }

    const copyName =
        kind === 'copy'
            ? suggestCopyName({
                  currentName:
                      name.value.trim() === ''
                          ? (props.initial?.name ?? props.suggestedName)
                          : name.value,
                  t: translator,
                  maxLength: props.limits.nameMaxLength,
              })
            : name.value;

    const body = layoutSystemPayload(
        source,
        details(copyName),
        props.originPaletteId ?? null,
    );

    props.beforeSave?.();

    saving.value = true;
    serverErrors.value = {};

    // O envelope e JSON puro em runtime; o tipo estrito do Inertia nao aceita
    // a interface sem indice, entao o recorte acontece aqui, no envio.
    const requestPayload = body as unknown as RequestPayload;

    const options = {
        preserveScroll: true,
        onError: (errors: Record<string, string>) => {
            serverErrors.value = { ...errors };
        },
        onFinish: () => {
            saving.value = false;
        },
    };

    if (props.mode === 'update' && kind === 'save' && props.systemId) {
        router.patch(
            systems.update.url({ layoutSystem: props.systemId }),
            requestPayload,
            {
                ...options,
                onSuccess: () => {
                    emit('saved');
                    emit('close');
                },
            },
        );

        return;
    }

    // Criacao e "salvar como novo": o servidor redireciona ao editor novo.
    router.post(systems.store.url(), requestPayload, options);
}

function setOpen(value: boolean): void {
    if (!value) {
        emit('close');
    }
}
</script>

<template>
  <Dialog
    :open="open"
    @update:open="setOpen"
  >
    <DialogContent
      class="sm:max-w-lg"
      data-test="layout-save-dialog"
    >
      <DialogHeader>
        <DialogTitle>
          {{ t('tools.layout-harmonizer.save.title') }}
        </DialogTitle>
        <DialogDescription>
          {{ t('tools.layout-harmonizer.save.description') }}
        </DialogDescription>
      </DialogHeader>

      <form
        class="flex flex-col gap-4"
        @submit.prevent="submit('save')"
      >
        <div class="flex flex-col gap-2">
          <div class="flex items-baseline justify-between gap-2">
            <Label for="layout-save-name">
              {{ t('tools.layout-harmonizer.save.name_label') }}
            </Label>
            <span class="text-xs text-muted-foreground">
              {{ name.trim().length }}/{{ limits.nameMaxLength }}
            </span>
          </div>
          <Input
            id="layout-save-name"
            v-model="name"
            type="text"
            autocomplete="off"
            :maxlength="limits.nameMaxLength"
            :aria-invalid="nameError !== null"
            :placeholder="
              t('tools.layout-harmonizer.save.name_placeholder')
            "
            data-test="layout-save-name"
          />
          <InputError :message="nameError ?? undefined" />
        </div>

        <div class="flex flex-col gap-2">
          <div class="flex items-baseline justify-between gap-2">
            <Label for="layout-save-description">
              {{
                t(
                  'tools.layout-harmonizer.save.description_label',
                )
              }}
            </Label>
            <span class="text-xs text-muted-foreground">
              {{ description.length }}/{{
                limits.descriptionMaxLength
              }}
            </span>
          </div>
          <textarea
            id="layout-save-description"
            v-model="description"
            :maxlength="limits.descriptionMaxLength"
            :aria-invalid="descriptionError !== null"
            :placeholder="
              t(
                'tools.layout-harmonizer.save.description_placeholder',
              )
            "
            class="min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 aria-invalid:border-destructive"
            data-test="layout-save-description"
          />
          <InputError :message="descriptionError ?? undefined" />
        </div>

        <fieldset class="flex flex-col gap-2">
          <legend class="text-sm font-medium">
            {{ t('tools.layout-harmonizer.save.visibility_label') }}
          </legend>
          <div class="grid gap-2 sm:grid-cols-2">
            <button
              type="button"
              role="radio"
              :aria-checked="visibility === 'private'"
              class="rounded-lg border px-3 py-2 text-left text-sm"
              :class="
                visibility === 'private'
                  ? 'border-primary bg-primary/5'
                  : 'border-border'
              "
              data-test="layout-save-visibility-private"
              @click="visibility = 'private'"
            >
              <span class="block font-medium">
                {{
                  t(
                    'tools.layout-harmonizer.save.visibility_private',
                  )
                }}
              </span>
              <span class="block text-xs text-muted-foreground">
                {{
                  t(
                    'tools.layout-harmonizer.save.visibility_private_hint',
                  )
                }}
              </span>
            </button>
            <button
              type="button"
              role="radio"
              :aria-checked="visibility === 'public'"
              class="rounded-lg border px-3 py-2 text-left text-sm"
              :class="
                visibility === 'public'
                  ? 'border-primary bg-primary/5'
                  : 'border-border'
              "
              data-test="layout-save-visibility-public"
              @click="visibility = 'public'"
            >
              <span class="block font-medium">
                {{
                  t(
                    'tools.layout-harmonizer.save.visibility_public',
                  )
                }}
              </span>
              <span class="block text-xs text-muted-foreground">
                {{
                  t(
                    'tools.layout-harmonizer.save.visibility_public_hint',
                  )
                }}
              </span>
            </button>
          </div>
        </fieldset>

        <div class="flex flex-col gap-1">
          <TagInput
            id="layout-save-tags"
            v-model="tags"
            :suggestions="accountTags"
            :max="limits.tagsPerSystem"
            :name-max-length="limits.tagNameMaxLength"
          />
          <InputError :message="tagsError ?? undefined" />
        </div>

        <div
          v-if="formErrors.length > 0"
          class="flex flex-col gap-1 rounded-lg border border-destructive/40 px-3 py-2 text-xs text-destructive"
          role="alert"
          data-test="layout-save-error"
        >
          <p
            v-for="message in formErrors"
            :key="message"
          >
            {{ message }}
          </p>
        </div>

        <DialogFooter class="gap-2">
          <Button
            type="button"
            variant="ghost"
            size="sm"
            :disabled="saving"
            data-test="layout-save-cancel"
            @click="emit('close')"
          >
            {{ t('tools.layout-harmonizer.save.cancel') }}
          </Button>
          <Button
            v-if="mode === 'update'"
            type="button"
            variant="outline"
            size="sm"
            :disabled="!canSubmit"
            data-test="layout-save-as-new"
            @click="submit('copy')"
          >
            {{ t('tools.layout-harmonizer.save.save_as_new') }}
          </Button>
          <Button
            type="submit"
            size="sm"
            :disabled="!canSubmit"
            data-test="layout-save-submit"
          >
            {{
              saving
                ? t('tools.layout-harmonizer.save.saving')
                : mode === 'update'
                  ? t(
                    'tools.layout-harmonizer.save.submit_update',
                  )
                  : t('tools.layout-harmonizer.save.submit')
            }}
          </Button>
        </DialogFooter>
      </form>
    </DialogContent>
  </Dialog>
</template>
