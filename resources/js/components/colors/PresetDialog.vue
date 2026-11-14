<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
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
import { colorsApi } from '@/lib/colors/api';
import type { ColorPreset, ColorPresetList } from '@/types/colors';

/**
 * Presets do gerador da conta (F5, ADR-093).
 *
 * Um preset guarda o nome e a cor base; aplicar devolve a base ao gerador,
 * que regenera a escala pelo algoritmo atual. Nada aqui e uma paleta: nao ha
 * visibilidade, link publico nem consumo da cota de paletas.
 *
 * A lista e a cota vem do servidor a cada abertura; o cliente so desabilita o
 * que o proprio servidor recusaria (nome vazio/duplicado, teto atingido). A
 * base e imutavel: para trocar a cor, o usuario salva outro preset.
 */

const props = defineProps<{
    open: boolean;
    /** Base atual do gerador, ja em HEX6/HEX8. */
    base: string;
    /** `false` quando o gerador esta vazio e nao ha base para salvar. */
    hasBase: boolean;
}>();

const emit = defineEmits<{
    close: [];
    apply: [base: string];
}>();

const { t, locale } = useI18n();

const presets = ref<ColorPreset[]>([]);
const limit = ref<number | null>(null);
const nameMaxLength = ref<number | null>(null);
const loading = ref(false);
const loadError = ref(false);

const name = ref('');
const saving = ref(false);
const formError = ref<string | null>(null);

const renamingId = ref<string | null>(null);
const renameName = ref('');
const renaming = ref(false);
const rowErrorId = ref<string | null>(null);
const rowError = ref<string | null>(null);

const confirmingId = ref<string | null>(null);
const deletingId = ref<string | null>(null);

const used = computed(() => presets.value.length);
const limitReached = computed(
    () => limit.value !== null && used.value >= limit.value,
);

const duplicate = computed(() => {
    const value = name.value.trim().toLowerCase();

    return (
        value !== '' &&
        presets.value.some((preset) => preset.name.toLowerCase() === value)
    );
});

const canCreate = computed(
    () =>
        props.hasBase &&
        !saving.value &&
        !loading.value &&
        nameMaxLength.value !== null &&
        name.value.trim() !== '' &&
        !duplicate.value &&
        !limitReached.value,
);

const canRename = computed(
    () =>
        !renaming.value &&
        renameName.value.trim() !== '' &&
        (nameMaxLength.value === null ||
            renameName.value.trim().length <= nameMaxLength.value),
);

function formatDate(value: string | null): string | null {
    if (value === null) {
        return null;
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return null;
    }

    return new Intl.DateTimeFormat(locale.value.replace('_', '-'), {
        dateStyle: 'medium',
    }).format(date);
}

function reset(): void {
    name.value = '';
    formError.value = null;
    saving.value = false;
    cancelRename();
    confirmingId.value = null;
    deletingId.value = null;
}

async function load(): Promise<void> {
    loading.value = true;
    loadError.value = false;

    try {
        const list = await colorsApi<ColorPresetList>('/presets');

        presets.value = list.data;
        limit.value = list.limits.limit;
        nameMaxLength.value = list.form.name_max_length;
    } catch {
        loadError.value = true;
    } finally {
        loading.value = false;
    }
}

watch(
    () => props.open,
    (open) => {
        if (open) {
            reset();
            void load();
        }
    },
    { immediate: true },
);

async function create(): Promise<void> {
    if (!canCreate.value) {
        return;
    }

    saving.value = true;
    formError.value = null;

    try {
        const response = await colorsApi<{ preset: ColorPreset }>('/presets', {
            method: 'POST',
            body: { name: name.value.trim(), base: props.base },
        });

        presets.value = [response.preset, ...presets.value];
        name.value = '';
    } catch {
        formError.value = t('colors.presets.error');
    } finally {
        saving.value = false;
    }
}

function startRename(preset: ColorPreset): void {
    renamingId.value = preset.id;
    renameName.value = preset.name;
    rowErrorId.value = null;
    rowError.value = null;
}

function cancelRename(): void {
    renamingId.value = null;
    renameName.value = '';
    rowErrorId.value = null;
    rowError.value = null;
    renaming.value = false;
}

async function saveRename(preset: ColorPreset): Promise<void> {
    if (!canRename.value) {
        return;
    }

    renaming.value = true;
    rowErrorId.value = null;
    rowError.value = null;

    try {
        const response = await colorsApi<{ preset: ColorPreset }>(
            `/presets/${preset.id}`,
            { method: 'PATCH', body: { name: renameName.value.trim() } },
        );

        // O servidor ordena por `updated_at` desc; renomear move para o topo.
        presets.value = [
            response.preset,
            ...presets.value.filter((item) => item.id !== preset.id),
        ];
        cancelRename();
    } catch {
        rowErrorId.value = preset.id;
        rowError.value = t('colors.presets.error');
    } finally {
        renaming.value = false;
    }
}

async function remove(preset: ColorPreset): Promise<void> {
    deletingId.value = preset.id;
    rowErrorId.value = null;
    rowError.value = null;

    try {
        await colorsApi<{ deleted: boolean }>(`/presets/${preset.id}`, {
            method: 'DELETE',
        });

        presets.value = presets.value.filter((item) => item.id !== preset.id);
        confirmingId.value = null;
    } catch {
        rowErrorId.value = preset.id;
        rowError.value = t('colors.presets.error');
    } finally {
        deletingId.value = null;
    }
}

function applyPreset(preset: ColorPreset): void {
    emit('apply', preset.base);
    emit('close');
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
      data-test="presets-dialog"
    >
      <DialogHeader>
        <DialogTitle>{{ t('colors.presets.title') }}</DialogTitle>
        <DialogDescription>
          {{ t('colors.presets.description') }}
        </DialogDescription>
      </DialogHeader>

      <form
        class="flex flex-col gap-3 rounded-xl border border-border p-3"
        @submit.prevent="create"
      >
        <div class="flex items-center gap-2 text-sm">
          <span
            v-if="hasBase"
            class="size-5 rounded-md border border-border"
            :style="{ backgroundColor: base }"
            aria-hidden="true"
          />
          <span
            v-if="hasBase"
            class="font-mono text-xs text-muted-foreground"
            data-test="presets-current-base"
          >
            {{ t('colors.presets.current_base', { hex: base }) }}
          </span>
          <span
            v-else
            class="text-xs text-muted-foreground"
            data-test="presets-no-base"
          >
            {{ t('colors.presets.empty_base') }}
          </span>
        </div>

        <div class="flex flex-col gap-2">
          <div class="flex items-baseline justify-between gap-2">
            <Label for="preset-name">
              {{ t('colors.presets.name_label') }}
            </Label>
            <span
              v-if="nameMaxLength !== null"
              class="text-xs text-muted-foreground"
            >
              {{ name.trim().length }}/{{ nameMaxLength }}
            </span>
          </div>
          <Input
            id="preset-name"
            v-model="name"
            type="text"
            autocomplete="off"
            :maxlength="nameMaxLength ?? undefined"
            :aria-invalid="duplicate || formError !== null"
            :placeholder="t('colors.presets.name_placeholder')"
            data-test="preset-name"
          />
        </div>

        <p
          v-if="limitReached"
          class="text-xs text-muted-foreground"
          data-test="presets-limit"
        >
          {{ t('colors.presets.limit_reached', { max: limit }) }}
        </p>
        <p
          v-else-if="duplicate"
          class="text-xs text-destructive"
          data-test="presets-duplicate"
        >
          {{ t('colors.presets.duplicate') }}
        </p>
        <p
          v-if="formError !== null"
          class="text-xs text-destructive"
          role="alert"
          data-test="presets-form-error"
        >
          {{ formError }}
        </p>

        <div class="flex justify-end">
          <Button
            type="submit"
            size="sm"
            :disabled="!canCreate"
            data-test="presets-create"
          >
            {{
              saving
                ? t('colors.presets.saving')
                : t('colors.presets.create')
            }}
          </Button>
        </div>
      </form>

      <p
        v-if="loading"
        class="text-sm text-muted-foreground"
        data-test="presets-loading"
      >
        {{ t('colors.presets.loading') }}
      </p>

      <div
        v-else-if="loadError"
        class="flex items-center justify-between gap-2 rounded-lg border border-destructive/40 px-3 py-2 text-xs text-destructive"
        role="alert"
        data-test="presets-load-error"
      >
        <span>{{ t('colors.presets.load_error') }}</span>
        <Button
          type="button"
          variant="ghost"
          size="sm"
          @click="load"
        >
          {{ t('colors.presets.retry') }}
        </Button>
      </div>

      <p
        v-else-if="presets.length === 0"
        class="text-sm text-muted-foreground"
        data-test="presets-empty"
      >
        {{ t('colors.presets.empty') }}
      </p>

      <ul
        v-else
        class="flex max-h-80 flex-col gap-2 overflow-y-auto"
      >
        <li
          v-for="preset in presets"
          :key="preset.id"
          class="flex flex-col gap-2 rounded-lg border border-border px-3 py-2"
          data-test="preset-row"
        >
          <div class="flex items-center gap-2">
            <span
              class="size-6 shrink-0 rounded-md border border-border"
              :style="{ backgroundColor: preset.base }"
              aria-hidden="true"
            />
            <div class="min-w-0 flex-1">
              <template v-if="renamingId === preset.id">
                <Input
                  v-model="renameName"
                  type="text"
                  autocomplete="off"
                  :maxlength="nameMaxLength ?? undefined"
                  :aria-invalid="rowError !== null"
                  :aria-label="t('colors.presets.name_label')"
                  data-test="preset-rename-input"
                  @keydown.enter.prevent="saveRename(preset)"
                  @keydown.esc.prevent="cancelRename"
                />
              </template>
              <template v-else>
                <p
                  class="truncate text-sm font-medium"
                  data-test="preset-name-label"
                >
                  {{ preset.name }}
                </p>
                <p class="font-mono text-xs text-muted-foreground">
                  {{ preset.base }}
                  <template v-if="formatDate(preset.updated_at) !== null">
                    · {{
                      t('colors.presets.updated_at', {
                        date: formatDate(preset.updated_at),
                      })
                    }}
                  </template>
                </p>
              </template>
            </div>
          </div>

          <p
            v-if="rowErrorId === preset.id && rowError !== null"
            class="text-xs text-destructive"
            role="alert"
            data-test="preset-row-error"
          >
            {{ rowError }}
          </p>

          <div class="flex flex-wrap justify-end gap-1">
            <template v-if="renamingId === preset.id">
              <Button
                type="button"
                variant="ghost"
                size="sm"
                :disabled="renaming"
                data-test="preset-rename-cancel"
                @click="cancelRename"
              >
                {{ t('colors.presets.cancel') }}
              </Button>
              <Button
                type="button"
                size="sm"
                :disabled="!canRename"
                data-test="preset-rename-save"
                @click="saveRename(preset)"
              >
                {{
                  renaming
                    ? t('colors.presets.renaming')
                    : t('colors.presets.rename_save')
                }}
              </Button>
            </template>
            <template v-else>
              <Button
                type="button"
                size="sm"
                data-test="preset-apply"
                @click="applyPreset(preset)"
              >
                {{ t('colors.presets.apply') }}
              </Button>
              <Button
                type="button"
                variant="ghost"
                size="sm"
                :disabled="deletingId !== null"
                data-test="preset-rename"
                @click="startRename(preset)"
              >
                {{ t('colors.presets.rename') }}
              </Button>
              <Button
                type="button"
                variant="ghost"
                size="sm"
                :disabled="deletingId !== null"
                data-test="preset-delete"
                @click="confirmingId = preset.id"
              >
                {{ t('colors.presets.delete') }}
              </Button>
            </template>
          </div>

          <div
            v-if="confirmingId === preset.id"
            class="flex flex-col gap-2 rounded-lg border border-dashed border-destructive/40 px-3 py-2"
            data-test="preset-delete-confirm"
          >
            <p class="text-xs">
              {{ t('colors.presets.confirm_delete', { name: preset.name }) }}
            </p>
            <p class="text-xs text-muted-foreground">
              {{ t('colors.presets.confirm_delete_hint') }}
            </p>
            <div class="flex justify-end gap-1">
              <Button
                type="button"
                variant="ghost"
                size="sm"
                :disabled="deletingId !== null"
                data-test="preset-delete-cancel"
                @click="confirmingId = null"
              >
                {{ t('colors.presets.cancel') }}
              </Button>
              <Button
                type="button"
                variant="destructive"
                size="sm"
                :disabled="deletingId !== null"
                data-test="preset-delete-confirm-button"
                @click="remove(preset)"
              >
                {{
                  deletingId === preset.id
                    ? t('colors.presets.deleting')
                    : t('colors.presets.delete')
                }}
              </Button>
            </div>
          </div>
        </li>
      </ul>

      <DialogFooter>
        <Button
          type="button"
          variant="ghost"
          size="sm"
          data-test="presets-close"
          @click="emit('close')"
        >
          {{ t('colors.presets.close') }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
