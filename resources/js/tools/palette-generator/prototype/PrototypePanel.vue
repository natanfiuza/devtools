<script setup lang="ts">
import { Copy, Download } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { useCopyFeedback } from '@/composables/useCopyFeedback';
import type {
    PaletteRoleColors,
    PaletteRoles,
    PaletteTheme,
} from '@/types/colors';
import { buildPrototype, type PrototypeBuildInput } from './defaults';
import {
    downloadPrototypePng,
    downloadPrototypeSvg,
    prototypeSvg,
} from './exportPrototype';
import { renderSvg } from './renderSvg';
import PrototypePreview from './PrototypePreview.vue';
import RoleEditor from './RoleEditor.vue';
import type { PrototypeCopy, PrototypeTemplate } from './types';

/**
 * Aba Prototipo do editor (F4, ADR-126).
 *
 * Dois templates (site e celular) e dois temas. O preview e o proprio SVG que
 * a exportacao gera; o painel so nao embute a fonte nele porque o app ja
 * carrega a Libre Franklin. Os papeis vem por `v-model:roles`: o editor guarda
 * o estado e manda no PATCH, entao editar um papel e salvar persiste.
 */

const props = defineProps<{
    roles: PaletteRoles;
    /** Padroes do tipo/base atuais, recalculados pelo editor. */
    defaults: PaletteRoles;
}>();

const emit = defineEmits<{ 'update:roles': [roles: PaletteRoles] }>();

const { t } = useI18n();
const copyFeedback = useCopyFeedback();
const feedback = copyFeedback.message;

const TEMPLATE_OPTIONS: readonly PrototypeTemplate[] = ['site', 'mobile'];
const THEME_OPTIONS: readonly PaletteTheme[] = ['light', 'dark'];

const template = ref<PrototypeTemplate>('site');
const theme = ref<PaletteTheme>('light');
const pngError = ref<string | null>(null);
const busy = ref(false);

/** Texto de exemplo do prototipo, traduzido (o renderizador e puro). */
const sample = computed<PrototypeCopy>(() => ({
    brand: t('colors.prototype.sample.brand'),
    nav: [
        t('colors.prototype.sample.nav_1'),
        t('colors.prototype.sample.nav_2'),
        t('colors.prototype.sample.nav_3'),
    ],
    heading: t('colors.prototype.sample.heading'),
    body: t('colors.prototype.sample.body'),
    primaryAction: t('colors.prototype.sample.primary'),
    secondaryAction: t('colors.prototype.sample.secondary'),
    links: [
        t('colors.prototype.sample.link_1'),
        t('colors.prototype.sample.link_2'),
        t('colors.prototype.sample.link_3'),
    ],
    sectionTitle: t('colors.prototype.sample.section'),
    cards: [
        t('colors.prototype.sample.card_1'),
        t('colors.prototype.sample.card_2'),
        t('colors.prototype.sample.card_3'),
    ],
    cardBody: t('colors.prototype.sample.card_body'),
    cardLink: t('colors.prototype.sample.card_link'),
    formTitle: t('colors.prototype.sample.form_title'),
    formLabel: t('colors.prototype.sample.form_label'),
    formPlaceholder: t('colors.prototype.sample.form_placeholder'),
    formAction: t('colors.prototype.sample.form_action'),
    footer: t('colors.prototype.sample.footer'),
    tabs: [
        t('colors.prototype.sample.tab_1'),
        t('colors.prototype.sample.tab_2'),
        t('colors.prototype.sample.tab_3'),
    ],
    tagline: t('colors.prototype.sample.tagline'),
}));

const templateName = computed(() =>
    t(`colors.prototype.template_${template.value}`),
);

const label = computed(() =>
    t('colors.prototype.preview_label', { template: templateName.value }),
);

/** O que o painel desenha e o que as exportacoes levam. */
function buildInput(): PrototypeBuildInput {
    return {
        kind: template.value,
        theme: theme.value,
        roles: props.roles[theme.value],
        copy: sample.value,
        label: label.value,
    };
}

const svg = computed(() => renderSvg(buildPrototype(buildInput())));

/** Os papeis do tema em edicao, ja resolvidos. */
const themeColors = computed<PaletteRoleColors>(() => props.roles[theme.value]);

const themeDefaults = computed<PaletteRoleColors>(
    () => props.defaults[theme.value],
);

function updateColors(colors: PaletteRoleColors): void {
    emit('update:roles', { ...props.roles, [theme.value]: colors });
}

function restoreDefaults(): void {
    emit('update:roles', {
        ...props.roles,
        [theme.value]: { ...themeDefaults.value },
    });
}

async function copySvg(): Promise<void> {
    await copyFeedback.copy(await prototypeSvg(buildInput()), t(
        'colors.prototype.copied',
    ));
}

async function exportSvg(): Promise<void> {
    await downloadPrototypeSvg(buildInput());
}

async function exportPng(scale: 1 | 2): Promise<void> {
    if (busy.value) {
        return;
    }

    busy.value = true;
    pngError.value = null;

    try {
        await downloadPrototypePng(buildInput(), scale);
    } catch {
        // Canvas bloqueado ou memoria curta: o PNG falha, o SVG continua.
        pngError.value = t('colors.prototype.png_error');
    } finally {
        busy.value = false;
    }
}
</script>

<template>
  <div
    class="flex flex-col gap-4"
    data-test="prototype-panel"
  >
    <div class="flex flex-col gap-1">
      <h3 class="text-sm font-semibold">
        {{ t('colors.prototype.title') }}
      </h3>
      <p class="text-xs text-muted-foreground">
        {{ t('colors.prototype.description') }}
      </p>
    </div>

    <div class="flex flex-wrap items-end gap-6">
      <div
        class="flex flex-col gap-1"
        role="group"
        :aria-label="t('colors.prototype.template_label')"
      >
        <span class="text-xs font-medium">
          {{ t('colors.prototype.template_label') }}
        </span>
        <div class="flex gap-1">
          <Button
            v-for="option in TEMPLATE_OPTIONS"
            :key="option"
            type="button"
            size="sm"
            :variant="template === option ? 'default' : 'outline'"
            :aria-pressed="template === option"
            :data-test="`prototype-template-${option}`"
            @click="template = option"
          >
            {{ t(`colors.prototype.template_${option}`) }}
          </Button>
        </div>
      </div>

      <div
        class="flex flex-col gap-1"
        role="group"
        :aria-label="t('colors.prototype.theme_label')"
      >
        <span class="text-xs font-medium">
          {{ t('colors.prototype.theme_label') }}
        </span>
        <div class="flex gap-1">
          <Button
            v-for="option in THEME_OPTIONS"
            :key="option"
            type="button"
            size="sm"
            :variant="theme === option ? 'default' : 'outline'"
            :aria-pressed="theme === option"
            :data-test="`prototype-theme-${option}`"
            @click="theme = option"
          >
            {{ t(`colors.prototype.theme_${option}`) }}
          </Button>
        </div>
      </div>
    </div>

    <PrototypePreview
      :svg="svg"
      :label="label"
    />

    <div class="flex flex-wrap gap-2">
      <Button
        type="button"
        size="sm"
        data-test="prototype-export-svg"
        @click="exportSvg"
      >
        <Download class="size-4" />
        {{ t('colors.prototype.download_svg') }}
      </Button>
      <Button
        type="button"
        size="sm"
        variant="outline"
        :disabled="busy"
        data-test="prototype-export-png-1x"
        @click="exportPng(1)"
      >
        {{ t('colors.prototype.download_png_1x') }}
      </Button>
      <Button
        type="button"
        size="sm"
        variant="outline"
        :disabled="busy"
        data-test="prototype-export-png-2x"
        @click="exportPng(2)"
      >
        {{ t('colors.prototype.download_png_2x') }}
      </Button>
      <Button
        type="button"
        size="sm"
        variant="ghost"
        data-test="prototype-copy-svg"
        @click="copySvg"
      >
        <Copy class="size-4" />
        {{ t('colors.prototype.copy_svg') }}
      </Button>
    </div>

    <p
      v-if="feedback !== null"
      class="text-xs text-muted-foreground"
      role="status"
      aria-live="polite"
      data-test="prototype-feedback"
    >
      {{ feedback }}
    </p>

    <p
      v-if="pngError !== null"
      class="text-xs text-destructive"
      role="alert"
      data-test="prototype-png-error"
    >
      {{ pngError }}
    </p>

    <RoleEditor
      :colors="themeColors"
      :defaults="themeDefaults"
      @update:colors="updateColors"
      @restore="restoreDefaults"
    />
  </div>
</template>
