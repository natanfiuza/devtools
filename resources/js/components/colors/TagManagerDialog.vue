<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, Check, Trash2, X } from '@lucide/vue';
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import tagsRoutes from '@/routes/dashboard/colors/tags';
import type { PaletteFormLimits, PaletteTag } from '@/types/colors';

/**
 * Gestao das tags da conta (F4, ADR-103).
 *
 * A tag pertence ao usuario: renomear alcanca todas as paletas e a ordem e
 * unica na conta. Cada operacao vai ao servidor e a lista volta pelos props do
 * Inertia; excluir pede um segundo clique porque tira a tag de todas as
 * paletas de uma vez.
 */

const props = defineProps<{
    open: boolean;
    tags: PaletteTag[];
    limits: PaletteFormLimits;
}>();

const emit = defineEmits<{
    close: [];
}>();

const { t } = useI18n();

const newName = ref('');
const newError = ref<string | null>(null);
const editingId = ref<string | null>(null);
const editingName = ref('');
const editingError = ref<string | null>(null);
const confirmingId = ref<string | null>(null);
const serverError = ref<string | null>(null);

function reset(): void {
    newName.value = '';
    newError.value = null;
    editingId.value = null;
    editingName.value = '';
    editingError.value = null;
    confirmingId.value = null;
    serverError.value = null;
}

watch(
    () => props.open,
    (open) => {
        if (open) {
            reset();
        }
    },
);

function validate(value: string): string | null {
    if (value === '') {
        return t('colors.tags_manager.errors.name_required');
    }

    if (value.length > props.limits.tagNameMaxLength) {
        return t('colors.tags_manager.errors.name_too_long', {
            max: props.limits.tagNameMaxLength,
        });
    }

    return null;
}

function create(): void {
    const name = newName.value.trim();
    const message = validate(name);

    newError.value = message;

    if (message !== null) {
        return;
    }

    router.post(
        tagsRoutes.store.url(),
        { name },
        {
            preserveScroll: true,
            preserveState: true,
            onError: (errors) => {
                newError.value = errors.name ?? null;
            },
            onSuccess: () => {
                newName.value = '';
            },
        },
    );
}

function startRename(tag: PaletteTag): void {
    confirmingId.value = null;
    editingId.value = tag.id;
    editingName.value = tag.name;
    editingError.value = null;
}

function cancelRename(): void {
    editingId.value = null;
    editingName.value = '';
    editingError.value = null;
}

function rename(tag: PaletteTag): void {
    const name = editingName.value.trim();
    const message = validate(name);

    editingError.value = message;

    if (message !== null) {
        return;
    }

    router.patch(
        tagsRoutes.update.url({ colorTag: tag.id }),
        { name },
        {
            preserveScroll: true,
            preserveState: true,
            onError: (errors) => {
                editingError.value = errors.name ?? null;
            },
            onSuccess: () => {
                cancelRename();
            },
        },
    );
}

function move(tag: PaletteTag, delta: number): void {
    const ids = props.tags.map((entry) => entry.id);
    const from = ids.indexOf(tag.id);
    const to = from + delta;

    if (from < 0 || to < 0 || to >= ids.length) {
        return;
    }

    const [moved] = ids.splice(from, 1);

    if (moved === undefined) {
        return;
    }

    ids.splice(to, 0, moved);

    router.patch(
        tagsRoutes.reorder.url(),
        { ids },
        {
            preserveScroll: true,
            preserveState: true,
            onError: () => {
                serverError.value = t('colors.validation.rate_limited');
            },
        },
    );
}

function remove(tag: PaletteTag): void {
    if (confirmingId.value !== tag.id) {
        confirmingId.value = tag.id;

        return;
    }

    router.delete(tagsRoutes.destroy.url({ colorTag: tag.id }), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            confirmingId.value = null;
        },
    });
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
      data-test="tag-manager"
    >
      <DialogHeader>
        <DialogTitle>{{ t('colors.tags_manager.title') }}</DialogTitle>
        <DialogDescription>
          {{ t('colors.tags_manager.description') }}
        </DialogDescription>
      </DialogHeader>

      <form
        class="flex flex-col gap-1"
        @submit.prevent="create"
      >
        <div class="flex gap-2">
          <Input
            v-model="newName"
            type="text"
            autocomplete="off"
            :maxlength="limits.tagNameMaxLength"
            :aria-invalid="newError !== null"
            :placeholder="t('colors.tags_manager.new_placeholder')"
            data-test="tag-manager-new"
          />
          <Button
            type="submit"
            size="sm"
            data-test="tag-manager-create"
          >
            {{ t('colors.tags_manager.create') }}
          </Button>
        </div>
        <p
          v-if="newError !== null"
          class="text-xs text-destructive"
          role="alert"
          data-test="tag-manager-new-error"
        >
          {{ newError }}
        </p>
      </form>

      <p
        v-if="tags.length === 0"
        class="rounded-lg border border-dashed border-border px-3 py-2 text-xs text-muted-foreground"
        data-test="tag-manager-empty"
      >
        {{ t('colors.tags_manager.empty') }}
      </p>

      <ul
        v-else
        class="flex flex-col divide-y divide-border rounded-lg border border-border"
      >
        <li
          v-for="(tag, index) in tags"
          :key="tag.id"
          class="flex flex-wrap items-center gap-2 px-3 py-2"
          data-test="tag-manager-row"
        >
          <template v-if="editingId === tag.id">
            <Input
              v-model="editingName"
              type="text"
              autocomplete="off"
              :maxlength="limits.tagNameMaxLength"
              :aria-invalid="editingError !== null"
              class="h-8 max-w-48"
              data-test="tag-manager-name-input"
              @keydown.esc="cancelRename"
            />
            <Button
              type="button"
              size="sm"
              data-test="tag-manager-save"
              @click="rename(tag)"
            >
              <Check class="size-4" />
              {{ t('colors.tags_manager.save') }}
            </Button>
            <Button
              type="button"
              variant="ghost"
              size="sm"
              data-test="tag-manager-cancel"
              @click="cancelRename"
            >
              <X class="size-4" />
              {{ t('colors.tags_manager.cancel') }}
            </Button>
            <p
              v-if="editingError !== null"
              class="w-full text-xs text-destructive"
              role="alert"
            >
              {{ editingError }}
            </p>
          </template>

          <template v-else>
            <span
              class="min-w-0 flex-1 truncate text-sm"
              data-test="tag-manager-name"
            >
              {{ tag.name }}
            </span>
            <Button
              type="button"
              variant="ghost"
              size="icon"
              :disabled="index === 0"
              :aria-label="t('colors.tags_manager.up', { name: tag.name })"
              data-test="tag-manager-up"
              @click="move(tag, -1)"
            >
              <ArrowUp class="size-4" />
            </Button>
            <Button
              type="button"
              variant="ghost"
              size="icon"
              :disabled="index === tags.length - 1"
              :aria-label="t('colors.tags_manager.down', { name: tag.name })"
              data-test="tag-manager-down"
              @click="move(tag, 1)"
            >
              <ArrowDown class="size-4" />
            </Button>
            <Button
              type="button"
              variant="ghost"
              size="sm"
              data-test="tag-manager-rename"
              @click="startRename(tag)"
            >
              {{ t('colors.tags_manager.rename') }}
            </Button>
            <Button
              type="button"
              variant="ghost"
              size="sm"
              class="text-destructive"
              data-test="tag-manager-delete"
              @click="remove(tag)"
            >
              <Trash2 class="size-4" />
              {{
                confirmingId === tag.id
                  ? t('colors.tags_manager.confirm_delete_action')
                  : t('colors.tags_manager.delete')
              }}
            </Button>
          </template>

          <p
            v-if="confirmingId === tag.id"
            class="w-full text-xs text-muted-foreground"
            role="status"
            data-test="tag-manager-delete-confirm"
          >
            {{ t('colors.tags_manager.confirm_delete', { name: tag.name }) }}
            {{ t('colors.tags_manager.confirm_delete_hint') }}
          </p>
        </li>
      </ul>

      <p
        v-if="serverError !== null"
        class="text-xs text-destructive"
        role="alert"
        data-test="tag-manager-error"
      >
        {{ serverError }}
      </p>

      <div class="flex justify-end">
        <Button
          type="button"
          variant="ghost"
          size="sm"
          data-test="tag-manager-close"
          @click="emit('close')"
        >
          {{ t('colors.tags_manager.close') }}
        </Button>
      </div>
    </DialogContent>
  </Dialog>
</template>
