<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { NO_LOGO } from './builder';
import { DEFAULT_FONT } from './lib/fonts';
import {
    exampleParams,
    findDefinition,
    findProvider,
    invalidProviderParam,
    type ProviderFormState,
} from './lib/providerUrl';
import type { BadgeForgeProviderDefinition, BadgeForgeToolProps } from './types';

/**
 * Formulario do modo provider (F4, ADR-080).
 *
 * A metrica define os params; trocar de metrica recomeca com o exemplo do
 * catalogo. Os campos cosmeticos reaproveitam os mesmos valores do modo
 * estatico; o cache nao aparece porque o TTL e do servidor.
 */

const props = defineProps<{
    modelValue: ProviderFormState;
    toolProps: BadgeForgeToolProps;
}>();

const emit = defineEmits<{ 'update:modelValue': [value: ProviderFormState] }>();

const { t, te } = useI18n();

const selectedProvider = computed(() =>
    findProvider(props.toolProps, props.modelValue.provider),
);

const selectedDefinition = computed(() =>
    findDefinition(selectedProvider.value, props.modelValue.definition),
);

const invalidParam = computed<string | null>(() => {
    const definition = selectedDefinition.value;

    return definition === undefined
        ? null
        : invalidProviderParam(
              definition,
              props.modelValue.params,
              props.toolProps.limits.providers.maxParamLength,
          );
});

/** Nome traduzido da metrica; o nome do catalogo e so fallback. */
function metricLabel(
    providerSlug: string,
    definition: BadgeForgeProviderDefinition,
): string {
    const key = `tools.badgeforge.provider.metrics.${providerSlug}_${definition.slug}`;

    return te(key) ? t(key) : definition.name;
}

function paramLabel(key: string): string {
    const path = `tools.badgeforge.provider.params.${key}`;

    return te(path) ? t(path) : key;
}

function patch(partial: Partial<ProviderFormState>): void {
    emit('update:modelValue', { ...props.modelValue, ...partial });
}

function setProvider(slug: string): void {
    const provider = findProvider(props.toolProps, slug);
    const definition = provider?.definitions[0];

    patch({
        provider: slug,
        definition: definition?.slug ?? '',
        params: definition === undefined ? {} : exampleParams(definition),
    });
}

function setDefinition(slug: string): void {
    const definition = findDefinition(selectedProvider.value, slug);

    patch({
        definition: slug,
        params: definition === undefined ? {} : exampleParams(definition),
    });
}

function setParam(name: string, value: string | number): void {
    patch({
        params: { ...props.modelValue.params, [name]: String(value) },
    });
}

/** Exemplo do catalogo para o campo (`natanfiuza`, `devtools`, ...). */
function examplePlaceholder(name: string): string {
    const definition = selectedDefinition.value;

    return definition === undefined ? '' : (exampleParams(definition)[name] ?? '');
}
</script>

<template>
  <section class="space-y-6">
    <div class="space-y-3">
      <h2 class="text-sm font-semibold">
        {{ t('tools.badgeforge.provider.sections.metric') }}
      </h2>

      <div class="grid gap-4 sm:grid-cols-2">
        <div class="space-y-2">
          <Label for="badge-provider">
            {{ t('tools.badgeforge.provider.fields.provider') }}
          </Label>
          <Select
            :model-value="modelValue.provider"
            @update:model-value="(value) => setProvider(String(value))"
          >
            <SelectTrigger
              id="badge-provider"
              class="w-full"
              data-test="badge-provider"
            >
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem
                v-for="provider in toolProps.providers"
                :key="provider.slug"
                :value="provider.slug"
              >
                {{ provider.name }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>

        <div class="space-y-2">
          <Label for="badge-definition">
            {{ t('tools.badgeforge.provider.fields.definition') }}
          </Label>
          <Select
            :model-value="modelValue.definition"
            @update:model-value="(value) => setDefinition(String(value))"
          >
            <SelectTrigger
              id="badge-definition"
              class="w-full"
              data-test="badge-definition"
            >
              <SelectValue>
                {{
                  selectedDefinition === undefined
                    ? ''
                    : metricLabel(
                      modelValue.provider,
                      selectedDefinition,
                    )
                }}
              </SelectValue>
            </SelectTrigger>
            <SelectContent>
              <SelectItem
                v-for="definition in selectedProvider?.definitions ?? []"
                :key="definition.slug"
                :value="definition.slug"
              >
                {{ metricLabel(modelValue.provider, definition) }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>
      </div>

      <div
        v-if="selectedDefinition !== undefined"
        class="grid gap-4 sm:grid-cols-2"
        data-test="badge-provider-params"
      >
        <div
          v-for="param in selectedDefinition.params"
          :key="param.name"
          class="space-y-2"
        >
          <Label :for="`badge-param-${param.name}`">
            {{ paramLabel(param.placeholderKey) }}
          </Label>
          <Input
            :id="`badge-param-${param.name}`"
            :model-value="modelValue.params[param.name] ?? ''"
            :maxlength="toolProps.limits.providers.maxParamLength"
            :placeholder="examplePlaceholder(param.name)"
            :aria-invalid="invalidParam === param.name"
            :data-test="`badge-param-${param.name}`"
            @update:model-value="(value) => setParam(param.name, value)"
          />
        </div>
      </div>

      <p
        v-if="invalidParam !== null"
        class="text-xs text-destructive"
        data-test="badge-provider-invalid"
      >
        {{ t('tools.badgeforge.provider.invalid_param') }}
      </p>

      <p
        v-else-if="selectedDefinition !== undefined"
        class="text-xs text-muted-foreground"
        data-test="badge-provider-example"
      >
        {{
          t('tools.badgeforge.provider.example', {
            path: selectedDefinition.examplePath,
          })
        }}
      </p>
    </div>

    <!-- Cosmeticos -->
    <div class="space-y-3">
      <h2 class="text-sm font-semibold">
        {{ t('tools.badgeforge.sections.colors') }}
      </h2>

      <div class="grid gap-4 sm:grid-cols-2">
        <div class="space-y-2">
          <Label for="badge-provider-style">
            {{ t('tools.badgeforge.fields.style') }}
          </Label>
          <Select
            :model-value="modelValue.style"
            @update:model-value="
              (value) => patch({ style: String(value) })
            "
          >
            <SelectTrigger
              id="badge-provider-style"
              class="w-full"
              data-test="badge-provider-style"
            >
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem
                v-for="style in toolProps.styles"
                :key="style"
                :value="style"
              >
                {{ style }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>

        <div class="space-y-2">
          <Label for="badge-provider-font">
            {{ t('tools.badgeforge.fields.font') }}
          </Label>
          <Select
            :model-value="modelValue.font"
            @update:model-value="(value) => patch({ font: String(value) })"
          >
            <SelectTrigger
              id="badge-provider-font"
              class="w-full"
              data-test="badge-provider-font"
            >
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem :value="DEFAULT_FONT">
                {{ t('tools.badgeforge.fonts.default') }}
              </SelectItem>
              <SelectItem
                v-for="font in toolProps.fonts"
                :key="font.slug"
                :value="font.slug"
              >
                {{ font.name }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>

        <div class="space-y-2">
          <Label for="badge-provider-label-color">
            {{ t('tools.badgeforge.fields.label_color') }}
          </Label>
          <Input
            id="badge-provider-label-color"
            :model-value="modelValue.labelColor"
            :placeholder="t('tools.badgeforge.placeholders.label_color')"
            data-test="badge-provider-label-color"
            @update:model-value="(value) => patch({ labelColor: String(value) })"
          />
        </div>

        <div class="space-y-2">
          <Label for="badge-provider-color">
            {{ t('tools.badgeforge.fields.color') }}
          </Label>
          <Input
            id="badge-provider-color"
            :model-value="modelValue.color"
            :placeholder="t('tools.badgeforge.placeholders.color')"
            data-test="badge-provider-color"
            @update:model-value="(value) => patch({ color: String(value) })"
          />
        </div>
      </div>
    </div>

    <!-- Logo -->
    <div class="space-y-3">
      <h2 class="text-sm font-semibold">
        {{ t('tools.badgeforge.sections.logo') }}
      </h2>

      <div class="grid gap-4 sm:grid-cols-2">
        <div class="space-y-2">
          <Label for="badge-provider-logo">
            {{ t('tools.badgeforge.fields.logo') }}
          </Label>
          <Select
            :model-value="modelValue.logo"
            @update:model-value="(value) => patch({ logo: String(value) })"
          >
            <SelectTrigger
              id="badge-provider-logo"
              class="w-full"
              data-test="badge-provider-logo"
            >
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem :value="NO_LOGO">
                {{ t('tools.badgeforge.logo_none') }}
              </SelectItem>
              <SelectItem
                v-for="logo in toolProps.logos"
                :key="logo.slug"
                :value="logo.slug"
              >
                {{ logo.name }}
              </SelectItem>
              <SelectItem
                v-for="logo in toolProps.customLogos"
                :key="logo.slug"
                :value="logo.slug"
              >
                {{ logo.name }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>

        <div class="space-y-2">
          <Label for="badge-provider-logo-position">
            {{ t('tools.badgeforge.fields.logo_position') }}
          </Label>
          <Select
            :model-value="modelValue.logoPosition"
            @update:model-value="
              (value) =>
                patch({
                  logoPosition:
                    String(value) === 'right' ? 'right' : 'left',
                })
            "
          >
            <SelectTrigger
              id="badge-provider-logo-position"
              class="w-full"
              data-test="badge-provider-logo-position"
            >
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem
                v-for="position in ['left', 'right'] as const"
                :key="position"
                :value="position"
              >
                {{ t(`tools.badgeforge.positions.${position}`) }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>

        <div class="space-y-2">
          <Label for="badge-provider-logo-color">
            {{ t('tools.badgeforge.fields.logo_color') }}
          </Label>
          <Input
            id="badge-provider-logo-color"
            :model-value="modelValue.logoColor"
            :placeholder="t('tools.badgeforge.placeholders.logo_color')"
            data-test="badge-provider-logo-color"
            @update:model-value="(value) => patch({ logoColor: String(value) })"
          />
        </div>

        <div class="space-y-2">
          <Label for="badge-provider-logo-size">
            {{ t('tools.badgeforge.fields.logo_size') }}
          </Label>
          <Input
            id="badge-provider-logo-size"
            type="number"
            :model-value="modelValue.logoSize"
            :min="toolProps.limits.logoSize.min"
            :max="toolProps.limits.logoSize.max"
            :placeholder="String(toolProps.limits.logoSize.max)"
            data-test="badge-provider-logo-size"
            @update:model-value="(value) => patch({ logoSize: String(value) })"
          />
        </div>
      </div>
    </div>

    <!-- Avancado -->
    <div class="space-y-3">
      <h2 class="text-sm font-semibold">
        {{ t('tools.badgeforge.sections.advanced') }}
      </h2>

      <div class="space-y-2">
        <Label for="badge-provider-link">
          {{ t('tools.badgeforge.fields.link') }}
        </Label>
        <Input
          id="badge-provider-link"
          type="url"
          :model-value="modelValue.link"
          :placeholder="t('tools.badgeforge.placeholders.link')"
          data-test="badge-provider-link"
          @update:model-value="(value) => patch({ link: String(value) })"
        />
      </div>

      <p class="text-xs text-muted-foreground">
        {{ t('tools.badgeforge.provider.cache_note') }}
      </p>
    </div>
  </section>
</template>
