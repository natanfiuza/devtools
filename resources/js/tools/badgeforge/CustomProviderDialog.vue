<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    store as storeCustomProvider,
    update as updateCustomProvider,
} from '@/routes/badgeforge/custom-providers';
import type {
    BadgeForgeCustomProvider,
    BadgeForgeToolProps,
    CustomMetricFormatter,
} from './types';

/**
 * Cadastro e edicao de provider customizado (F7, ADR-087).
 *
 * Na criacao o dono informa o template, os placeholders, um path de exemplo e
 * as metricas (slug + JSON Pointer + formatador). O probe do servidor valida o
 * shape antes de gravar. Na edicao o template e os slugs sao imutaveis: so
 * nome, label, pointer e formatador mudam. A origem nunca volta do servidor,
 * por isso a edicao nao a exibe.
 */

interface MetricInput {
    slug: string;
    label: string;
    pointer: string;
    formatter: CustomMetricFormatter;
}

const props = defineProps<{
    open: boolean;
    toolProps: BadgeForgeToolProps;
    provider: BadgeForgeCustomProvider | null;
}>();

const emit = defineEmits<{ 'update:open': [value: boolean] }>();

const { t } = useI18n();

const pathParamsText = ref('');

const formatters: CustomMetricFormatter[] = ['text', 'number', 'percent'];

const form = useForm({
    name: '',
    url_template: '',
    path_params: [] as string[],
    example_path: '',
    metrics: [] as MetricInput[],
});

watch(
    () => [props.open, props.provider] as const,
    () => {
        if (!props.open) {
            form.reset();
            form.clearErrors();
            pathParamsText.value = '';

            return;
        }

        form.clearErrors();

        if (props.provider === null) {
            form.name = '';
            form.url_template = '';
            form.path_params = [];
            form.example_path = '';
            form.metrics = [
                {
                    slug: '',
                    label: '',
                    pointer: '',
                    formatter: 'number',
                },
            ];
            pathParamsText.value = '';

            return;
        }

        form.name = props.provider.name;
        form.url_template = '';
        form.path_params = [...props.provider.pathParams];
        form.example_path = props.provider.examplePath;
        form.metrics = props.provider.definitions.map((definition) => ({
            slug: definition.slug,
            label: definition.label,
            pointer: definition.pointer,
            formatter: definition.formatter,
        }));
        pathParamsText.value = props.provider.pathParams.join(', ');
    },
    { immediate: true },
);

function addMetric(): void {
    if (
        props.provider !== null ||
        form.metrics.length >= props.toolProps.limits.customProviders.maxMetrics
    ) {
        return;
    }

    form.metrics.push({
        slug: '',
        label: '',
        pointer: '',
        formatter: 'number',
    });
}

function removeMetric(index: number): void {
    if (props.provider !== null || form.metrics.length <= 1) {
        return;
    }

    form.metrics.splice(index, 1);
}

function errorFor(key: string): string | undefined {
    return (form.errors as Record<string, string | undefined>)[key];
}

function submit(): void {
    if (form.processing) {
        return;
    }

    const editing = props.provider;

    form.path_params = pathParamsText.value
        .split(',')
        .map((name) => name.trim())
        .filter((name) => name !== '');

    form.transform((data) =>
        editing === null
            ? data
            : { name: data.name, metrics: data.metrics },
    );

    const options = {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            emit('update:open', false);
        },
    };

    if (editing === null) {
        form.post(storeCustomProvider.url(), options);

        return;
    }

    form.patch(
        updateCustomProvider.url({ badgeCustomProvider: editing.id }),
        options,
    );
}
</script>

<template>
  <Dialog
    :open="open"
    @update:open="(value: boolean) => emit('update:open', value)"
  >
    <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
      <DialogHeader>
        <DialogTitle>
          {{
            provider === null
              ? t('tools.badgeforge.custom_providers.dialog_create_title')
              : t('tools.badgeforge.custom_providers.dialog_edit_title')
          }}
        </DialogTitle>
        <DialogDescription>
          {{ t('tools.badgeforge.custom_providers.dialog_description') }}
        </DialogDescription>
      </DialogHeader>

      <form
        class="space-y-4"
        data-test="badge-custom-provider-form"
        @submit.prevent="submit"
      >
        <div class="space-y-2">
          <Label for="badge-custom-provider-name">
            {{ t('tools.badgeforge.custom_providers.fields.name') }}
          </Label>
          <Input
            id="badge-custom-provider-name"
            v-model="form.name"
            :maxlength="toolProps.limits.customProviders.nameMaxLength"
            data-test="badge-custom-provider-name"
          />
          <InputError :message="form.errors.name" />
        </div>

        <template v-if="provider === null">
          <div class="space-y-2">
            <Label for="badge-custom-provider-template">
              {{ t('tools.badgeforge.custom_providers.fields.url_template') }}
            </Label>
            <Input
              id="badge-custom-provider-template"
              v-model="form.url_template"
              type="url"
              autocomplete="off"
              spellcheck="false"
              :maxlength="toolProps.limits.customProviders.urlMaxLength"
              :placeholder="
                t('tools.badgeforge.custom_providers.hints.url_template')
              "
              data-test="badge-custom-provider-template"
            />
            <InputError :message="form.errors.url_template" />
            <p class="text-xs text-muted-foreground">
              {{ t('tools.badgeforge.custom_providers.hints.template_note') }}
            </p>
          </div>

          <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
              <Label for="badge-custom-provider-params">
                {{ t('tools.badgeforge.custom_providers.fields.path_params') }}
              </Label>
              <Input
                id="badge-custom-provider-params"
                v-model="pathParamsText"
                autocomplete="off"
                spellcheck="false"
                :placeholder="
                  t('tools.badgeforge.custom_providers.hints.path_params')
                "
                data-test="badge-custom-provider-params"
              />
              <InputError :message="form.errors.path_params" />
            </div>

            <div class="space-y-2">
              <Label for="badge-custom-provider-example">
                {{ t('tools.badgeforge.custom_providers.fields.example_path') }}
              </Label>
              <Input
                id="badge-custom-provider-example"
                v-model="form.example_path"
                autocomplete="off"
                spellcheck="false"
                :maxlength="
                  toolProps.limits.customProviders.examplePathMaxLength
                "
                :placeholder="
                  t('tools.badgeforge.custom_providers.hints.example_path')
                "
                data-test="badge-custom-provider-example"
              />
              <InputError :message="form.errors.example_path" />
            </div>
          </div>
        </template>

        <p
          v-else
          class="rounded-md border border-dashed px-3 py-2 text-xs text-muted-foreground"
          data-test="badge-custom-provider-immutable"
        >
          {{ t('tools.badgeforge.custom_providers.template_immutable', {
            path: provider.examplePath,
          }) }}
        </p>

        <div class="space-y-3">
          <div class="flex items-center justify-between gap-2">
            <h4 class="text-sm font-medium">
              {{ t('tools.badgeforge.custom_providers.fields.metrics') }}
            </h4>
            <Button
              v-if="provider === null"
              variant="outline"
              size="sm"
              type="button"
              :disabled="
                form.metrics.length >=
                  toolProps.limits.customProviders.maxMetrics
              "
              data-test="badge-custom-provider-add-metric"
              @click="addMetric"
            >
              {{ t('tools.badgeforge.custom_providers.add_metric') }}
            </Button>
          </div>

          <InputError :message="form.errors.metrics" />

          <div
            v-for="(metric, index) in form.metrics"
            :key="index"
            class="space-y-3 rounded-md border border-border px-3 py-3"
            data-test="badge-custom-provider-metric"
          >
            <div class="grid gap-3 sm:grid-cols-2">
              <div class="space-y-2">
                <Label :for="`badge-custom-metric-slug-${index}`">
                  {{ t('tools.badgeforge.custom_providers.fields.metric_slug') }}
                </Label>
                <Input
                  :id="`badge-custom-metric-slug-${index}`"
                  v-model="metric.slug"
                  :readonly="provider !== null"
                  :maxlength="toolProps.limits.customProviders.slugMaxLength"
                  autocomplete="off"
                  spellcheck="false"
                  data-test="badge-custom-provider-metric-slug"
                />
                <InputError :message="errorFor(`metrics.${index}.slug`)" />
              </div>

              <div class="space-y-2">
                <Label :for="`badge-custom-metric-label-${index}`">
                  {{ t('tools.badgeforge.custom_providers.fields.metric_label') }}
                </Label>
                <Input
                  :id="`badge-custom-metric-label-${index}`"
                  v-model="metric.label"
                  :maxlength="toolProps.limits.customProviders.labelMaxLength"
                  data-test="badge-custom-provider-metric-label"
                />
                <InputError :message="errorFor(`metrics.${index}.label`)" />
              </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
              <div class="space-y-2">
                <Label :for="`badge-custom-metric-pointer-${index}`">
                  {{ t('tools.badgeforge.custom_providers.fields.pointer') }}
                </Label>
                <Input
                  :id="`badge-custom-metric-pointer-${index}`"
                  v-model="metric.pointer"
                  :maxlength="toolProps.limits.customProviders.pointerMaxLength"
                  autocomplete="off"
                  spellcheck="false"
                  :placeholder="
                    t('tools.badgeforge.custom_providers.hints.pointer')
                  "
                  data-test="badge-custom-provider-metric-pointer"
                />
                <InputError :message="errorFor(`metrics.${index}.pointer`)" />
              </div>

              <div class="space-y-2">
                <Label :for="`badge-custom-metric-formatter-${index}`">
                  {{ t('tools.badgeforge.custom_providers.fields.formatter') }}
                </Label>
                <Select v-model="metric.formatter">
                  <SelectTrigger
                    :id="`badge-custom-metric-formatter-${index}`"
                    class="w-full"
                    data-test="badge-custom-provider-metric-formatter"
                  >
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="value in formatters"
                      :key="value"
                      :value="value"
                    >
                      {{
                        t(
                          `tools.badgeforge.custom_providers.formatters.${value}`,
                        )
                      }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <InputError :message="errorFor(`metrics.${index}.formatter`)" />
              </div>
            </div>

            <div
              v-if="provider === null && form.metrics.length > 1"
              class="flex justify-end"
            >
              <Button
                variant="ghost"
                size="sm"
                type="button"
                data-test="badge-custom-provider-remove-metric"
                @click="removeMetric(index)"
              >
                {{ t('tools.badgeforge.custom_providers.remove_metric') }}
              </Button>
            </div>
          </div>
        </div>

        <div class="flex items-center justify-end gap-2">
          <Button
            variant="outline"
            size="sm"
            type="button"
            data-test="badge-custom-provider-cancel"
            @click="emit('update:open', false)"
          >
            {{ t('tools.badgeforge.management.cancel') }}
          </Button>
          <Button
            size="sm"
            type="submit"
            :disabled="form.processing"
            data-test="badge-custom-provider-submit"
          >
            {{
              provider === null
                ? t('tools.badgeforge.custom_providers.create_submit')
                : t('tools.badgeforge.custom_providers.update_submit')
            }}
          </Button>
        </div>
      </form>
    </DialogContent>
  </Dialog>
</template>
