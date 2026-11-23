<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { prototypeSampleCopy } from '@/lib/prototype/copy';
import { buildPrototype } from '@/lib/prototype/defaults';
import PrototypePreview from '@/lib/prototype/PrototypePreview.vue';
import { renderSvg } from '@/lib/prototype/renderSvg';
import type { PrototypeTemplate } from '@/lib/prototype/types';
import type { PaletteRoles, PaletteTheme } from '@/types/colors';
import { layoutPrototypeColors } from '../lib/layoutRoleMap';
import { boardsFromSnapshot, type LayoutSystemStateV1 } from '../lib/state';

/**
 * Protótipo do sistema de layout (F4, ADR-173).
 *
 * Uma vitrine dos 33 tokens gravados: site e celular, nos dois temas, desenha
 * o mesmo `PrototypePreview` da aba Prototipo da paleta. Não há editor de
 * papéis nem exportação nesta fase - o mapa de papéis é fixo e o quadro é o
 * lugar de editar o token. Só aparece no workspace autenticado.
 */

const props = defineProps<{
    /** Envelope exibido: live ou compatível, sempre o snapshot salvo. */
    state: LayoutSystemStateV1;
}>();

const { t } = useI18n();

const TEMPLATE_OPTIONS: readonly PrototypeTemplate[] = ['site', 'mobile'];
const THEME_OPTIONS: readonly PaletteTheme[] = ['light', 'dark'];

const template = ref<PrototypeTemplate>('site');
const theme = ref<PaletteTheme>('light');

const roles = computed<PaletteRoles>(() => {
    const boards = boardsFromSnapshot(props.state.snapshot);

    return {
        light: layoutPrototypeColors(boards.light),
        dark: layoutPrototypeColors(boards.dark),
    };
});

const label = computed(() =>
    t('tools.layout-harmonizer.prototype.preview_label', {
        template: t(
            `tools.layout-harmonizer.prototype.template_${template.value}`,
        ),
        theme: t(`tools.layout-harmonizer.prototype.theme_${theme.value}`),
    }),
);

const svg = computed(() =>
    renderSvg(
        buildPrototype({
            kind: template.value,
            theme: theme.value,
            roles: roles.value[theme.value],
            copy: prototypeSampleCopy(t),
            label: label.value,
        }),
    ),
);
</script>

<template>
  <section
    class="flex flex-col gap-4"
    data-test="layout-system-prototype"
  >
    <div class="flex flex-col gap-1">
      <h2 class="text-sm font-semibold">
        {{ t('tools.layout-harmonizer.prototype.title') }}
      </h2>
      <p class="text-xs text-muted-foreground">
        {{ t('tools.layout-harmonizer.prototype.description') }}
      </p>
    </div>

    <div class="flex flex-wrap items-end gap-6">
      <div
        class="flex flex-col gap-1"
        role="group"
        :aria-label="
          t('tools.layout-harmonizer.prototype.template_label')
        "
      >
        <span class="text-xs font-medium">
          {{ t('tools.layout-harmonizer.prototype.template_label') }}
        </span>
        <div class="flex gap-1">
          <Button
            v-for="option in TEMPLATE_OPTIONS"
            :key="option"
            type="button"
            size="sm"
            :variant="template === option ? 'default' : 'outline'"
            :aria-pressed="template === option"
            :data-test="`layout-prototype-template-${option}`"
            @click="template = option"
          >
            {{
              t(
                `tools.layout-harmonizer.prototype.template_${option}`,
              )
            }}
          </Button>
        </div>
      </div>

      <div
        class="flex flex-col gap-1"
        role="group"
        :aria-label="t('tools.layout-harmonizer.prototype.theme_label')"
      >
        <span class="text-xs font-medium">
          {{ t('tools.layout-harmonizer.prototype.theme_label') }}
        </span>
        <div class="flex gap-1">
          <Button
            v-for="option in THEME_OPTIONS"
            :key="option"
            type="button"
            size="sm"
            :variant="theme === option ? 'default' : 'outline'"
            :aria-pressed="theme === option"
            :data-test="`layout-prototype-theme-${option}`"
            @click="theme = option"
          >
            {{
              t(
                `tools.layout-harmonizer.prototype.theme_${option}`,
              )
            }}
          </Button>
        </div>
      </div>
    </div>

    <PrototypePreview
      :svg="svg"
      :label="label"
    />
  </section>
</template>
