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
import {
    exampleCustomParams,
    findCustomProvider,
    invalidCustomParam,
    type CustomProviderFormState,
} from './lib/customProviderUrl';
import { DEFAULT_FONT } from './lib/fonts';
import type {
    BadgeForgeCustomProvider,
    BadgeForgeToolProps,
} from './types';

/**
 * Formulario do modo provider customizado (F7, ADR-087).
 *
 * A metrica vem do conjunto fixo criado pelo dono e os params sao exatamente
 * os placeholders do template. O conjunto de caracteres aceito aqui e o mesmo
 * de `CustomPathParams`; a origem nunca aparece porque o cliente nao a tem.
 */

const props = defineProps<{
    modelValue: CustomProviderFormState;
    toolProps: BadgeForgeToolProps;
}>();

const emit = defineEmits<{ 'update:modelValue': [value: CustomProviderFormState] }>();

const { t } = useI18n();

const selectedProvider = computed(() =>
    findCustomProvider(props.toolProps, props.modelValue.provider),
);

const invalidParam = computed<string | null>(() => {
    const provider = selectedProvider.value;

    return provider === undefined
        ? null
        : invalidCustomParam(
              provider,
              props.modelValue.params,
              props.toolProps.limits.customProviders.paramMaxLength,
          );
});

function patch(partial: Partial<CustomProviderFormState>): void {
    emit('update:modelValue', { ...props.modelValue, ...partial });
}

function setProvider(id: string): void {
    const provider = findCustomProvider(props.toolProps, id);

    patch({
        provider: id,
        definition: provider?.definitions[0]?.slug ?? '',
        params: provider === undefined ? {} : exampleCustomParams(provider),
    });
}

function setDefinition(slug: string): void {
    patch({ definition: slug });
}

function setParam(name: string, value: string | number): void {
    patch({
        params: { ...props.modelValue.params, [name]: String(value) },
    });
}

function examplePlaceholder(provider: BadgeForgeCustomProvider, name: string): string {
    return exampleCustomParams(provider)[name] ?? '';
}

function providerLabel(provider: BadgeForgeCustomProvider): string {
    return provider.enabled
        ? provider.name
        : `${provider.name} (${t('tools.badgeforge.custom_providers.disabled')})`;
}
</script>

<template>
  <section class="space-y-6">
    <div class="space-y-3">
      <h2 class="text-sm font-semibold">
        {{ t('tools.badgeforge.custom_providers.builder.sections.metric') }}
      </h2>

      <div class="grid gap-4 sm:grid-cols-2">
        <div class="space-y-2">
          <Label for="badge-custom-provider">
            {{ t('tools.badgeforge.custom_providers.builder.fields.provider') }}
          </Label>
          <Select
            :model-value="modelValue.provider"
            @update:model-value="(value) => setProvider(String(value))"
          >
            <SelectTrigger
              id="badge-custom-provider"
              class="w-full"
              data-test="badge-custom-provider"
            >
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem
                v-for="provider in toolProps.customProviders"
                :key="provider.id"
                :value="provider.id"
              >
                {{ providerLabel(provider) }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>

        <div class="space-y-2">
          <Label for="badge-custom-definition">
            {{ t('tools.badgeforge.custom_providers.builder.fields.definition') }}
          </Label>
          <Select
            :model-value="modelValue.definition"
            @update:model-value="(value) => setDefinition(String(value))"
          >
            <SelectTrigger
              id="badge-custom-definition"
              class="w-full"
              data-test="badge-custom-definition"
            >
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem
                v-for="definition in selectedProvider?.definitions ?? []"
                :key="definition.slug"
                :value="definition.slug"
              >
                {{ definition.label }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>
      </div>

      <div
        v-if="selectedProvider !== undefined"
        class="grid gap-4 sm:grid-cols-2"
        data-test="badge-custom-params"
      >
        <div
          v-for="name in selectedProvider.pathParams"
          :key="name"
          class="space-y-2"
        >
          <Label :for="`badge-custom-param-${name}`">
            {{ name }}
          </Label>
          <Input
            :id="`badge-custom-param-${name}`"
            :model-value="modelValue.params[name] ?? ''"
            :maxlength="toolProps.limits.customProviders.paramMaxLength"
            :placeholder="examplePlaceholder(selectedProvider, name)"
            :aria-invalid="invalidParam === name"
            :data-test="`badge-custom-param-${name}`"
            @update:model-value="(value) => setParam(name, value)"
          />
        </div>
      </div>

      <p
        v-if="invalidParam !== null"
        class="text-xs text-destructive"
        data-test="badge-custom-invalid"
      >
        {{ t('tools.badgeforge.custom_providers.builder.invalid_param') }}
      </p>

      <p
        v-else-if="selectedProvider !== undefined"
        class="text-xs text-muted-foreground"
        data-test="badge-custom-example"
      >
        {{
          t('tools.badgeforge.custom_providers.builder.example', {
            path: selectedProvider.examplePath,
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
          <Label for="badge-custom-style">
            {{ t('tools.badgeforge.fields.style') }}
          </Label>
          <Select
            :model-value="modelValue.style"
            @update:model-value="(value) => patch({ style: String(value) })"
          >
            <SelectTrigger
              id="badge-custom-style"
              class="w-full"
              data-test="badge-custom-style"
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
          <Label for="badge-custom-font">
            {{ t('tools.badgeforge.fields.font') }}
          </Label>
          <Select
            :model-value="modelValue.font"
            @update:model-value="(value) => patch({ font: String(value) })"
          >
            <SelectTrigger
              id="badge-custom-font"
              class="w-full"
              data-test="badge-custom-font"
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
          <Label for="badge-custom-label-color">
            {{ t('tools.badgeforge.fields.label_color') }}
          </Label>
          <Input
            id="badge-custom-label-color"
            :model-value="modelValue.labelColor"
            :placeholder="t('tools.badgeforge.placeholders.label_color')"
            data-test="badge-custom-label-color"
            @update:model-value="(value) => patch({ labelColor: String(value) })"
          />
        </div>

        <div class="space-y-2">
          <Label for="badge-custom-color">
            {{ t('tools.badgeforge.fields.color') }}
          </Label>
          <Input
            id="badge-custom-color"
            :model-value="modelValue.color"
            :placeholder="t('tools.badgeforge.placeholders.color')"
            data-test="badge-custom-color"
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
          <Label for="badge-custom-logo">
            {{ t('tools.badgeforge.fields.logo') }}
          </Label>
          <Select
            :model-value="modelValue.logo"
            @update:model-value="(value) => patch({ logo: String(value) })"
          >
            <SelectTrigger
              id="badge-custom-logo"
              class="w-full"
              data-test="badge-custom-logo"
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
          <Label for="badge-custom-logo-position">
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
              id="badge-custom-logo-position"
              class="w-full"
              data-test="badge-custom-logo-position"
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
          <Label for="badge-custom-logo-color">
            {{ t('tools.badgeforge.fields.logo_color') }}
          </Label>
          <Input
            id="badge-custom-logo-color"
            :model-value="modelValue.logoColor"
            :placeholder="t('tools.badgeforge.placeholders.logo_color')"
            data-test="badge-custom-logo-color"
            @update:model-value="(value) => patch({ logoColor: String(value) })"
          />
        </div>

        <div class="space-y-2">
          <Label for="badge-custom-logo-size">
            {{ t('tools.badgeforge.fields.logo_size') }}
          </Label>
          <Input
            id="badge-custom-logo-size"
            type="number"
            :model-value="modelValue.logoSize"
            :min="toolProps.limits.logoSize.min"
            :max="toolProps.limits.logoSize.max"
            :placeholder="String(toolProps.limits.logoSize.max)"
            data-test="badge-custom-logo-size"
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
        <Label for="badge-custom-link">
          {{ t('tools.badgeforge.fields.link') }}
        </Label>
        <Input
          id="badge-custom-link"
          type="url"
          :model-value="modelValue.link"
          :placeholder="t('tools.badgeforge.placeholders.link')"
          data-test="badge-custom-link"
          @update:model-value="(value) => patch({ link: String(value) })"
        />
      </div>

      <p class="text-xs text-muted-foreground">
        {{ t('tools.badgeforge.custom_providers.builder.cache_note') }}
      </p>
    </div>
  </section>
</template>
