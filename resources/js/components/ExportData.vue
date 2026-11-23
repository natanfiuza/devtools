<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import profile from '@/routes/profile';

/**
 * Exportar os proprios dados (ADR-028).
 *
 * Download direto: o controlador monta o JSON na hora e o entrega como anexo,
 * entao isto e um link comum - se fosse `Link` do Inertia, a resposta de
 * download nao seria uma pagina.
 */
const { t } = useI18n();
</script>

<template>
  <div class="space-y-6">
    <Heading
      variant="small"
      :title="t('account.export.title')"
      :description="t('account.export.description')"
    />

    <div class="space-y-4">
      <Button
        variant="outline"
        as-child
      >
        <a
          :href="profile.export.url()"
          data-test="export-data-button"
        >
          {{ t('account.export.action') }}
        </a>
      </Button>

      <p class="text-sm text-subtle">
        {{ t('account.export.hint') }}
      </p>
    </div>
  </div>
</template>
