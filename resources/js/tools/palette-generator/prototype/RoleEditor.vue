<script setup lang="ts">
import { RotateCcw } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { PALETTE_ROLES } from '@/lib/palettePayload';
import type { PaletteRole, PaletteRoleColors } from '@/types/colors';
import RoleColorField from './RoleColorField.vue';

/**
 * Os 8 papeis do tema em edicao (F4, ADR-126).
 *
 * "Restaurar padroes" nao mexe sozinho no estado: quem chama decide de onde
 * vêm os padroes (a escala/extraida atual) e devolve os papeis prontos, para o
 * editor continuar sendo a unica fonte do payload.
 */

const props = defineProps<{
    colors: PaletteRoleColors;
    defaults: PaletteRoleColors;
}>();

const emit = defineEmits<{
    'update:colors': [colors: PaletteRoleColors];
    restore: [];
}>();

const { t } = useI18n();

function setRole(role: PaletteRole, hex: string): void {
    emit('update:colors', { ...props.colors, [role]: hex });
}
</script>

<template>
  <section
    class="flex flex-col gap-3"
    data-test="role-editor"
  >
    <div class="flex flex-wrap items-start justify-between gap-2">
      <div>
        <h3 class="text-sm font-semibold">
          {{ t('colors.prototype.roles_title') }}
        </h3>
        <p class="mt-1 text-xs text-muted-foreground">
          {{ t('colors.prototype.roles_description') }}
        </p>
      </div>

      <Button
        type="button"
        variant="ghost"
        size="sm"
        data-test="role-editor-restore"
        @click="emit('restore')"
      >
        <RotateCcw class="size-4" />
        {{ t('colors.prototype.roles_restore') }}
      </Button>
    </div>

    <div class="grid gap-3 sm:grid-cols-2">
      <RoleColorField
        v-for="role in PALETTE_ROLES"
        :key="role"
        :label="t(`colors.prototype.role_${role}`)"
        :model-value="colors[role]"
        :test-id="`role-field-${role}`"
        @update:model-value="setRole(role, $event)"
      />
    </div>
  </section>
</template>
