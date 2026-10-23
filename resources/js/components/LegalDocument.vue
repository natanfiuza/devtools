<script setup lang="ts">
import { useI18n } from 'vue-i18n';

/**
 * Corpo das paginas legais (Privacidade e Termos). O texto vive nos arquivos de
 * idioma; aqui ficam so a ordem das secoes e a marcacao.
 *
 * A ordem vem do componente, e nao do objeto de traducao, para que as duas
 * paginas leiam na mesma sequencia em qualquer idioma.
 */
const props = defineProps<{
    /** `privacy` ou `terms`: escolhe o ramo do namespace `legal`. */
    namespace: 'privacy' | 'terms';
    /** Chaves das secoes, na ordem em que aparecem. */
    sections: readonly string[];
    /** E-mail de contato (config/legal.php); ausente, a secao nao aparece. */
    contactEmail: string | null;
    /** "Ultima atualizacao: ..." ja formatada no idioma da pagina. */
    updatedLabel: string;
}>();

const { t } = useI18n();
</script>

<template>
  <article class="mx-auto w-full max-w-2xl px-4 py-16">
    <h1 class="text-3xl font-medium tracking-tight">
      {{ t(`legal.${props.namespace}.title`) }}
    </h1>
    <p class="mt-2 text-sm text-muted-foreground">
      {{ updatedLabel }}
    </p>

    <p class="mt-6 leading-relaxed">
      {{ t(`legal.${props.namespace}.intro`, { app: t('app.brand') }) }}
    </p>

    <section
      v-for="section in sections"
      :key="section"
      class="mt-10"
    >
      <h2 class="text-xl font-medium tracking-tight">
        {{ t(`legal.${props.namespace}.sections.${section}.title`) }}
      </h2>
      <p class="mt-2 leading-relaxed text-muted-foreground">
        {{ t(`legal.${props.namespace}.sections.${section}.body`) }}
      </p>
    </section>

    <section
      v-if="contactEmail"
      class="mt-10"
    >
      <h2 class="text-xl font-medium tracking-tight">
        {{ t('legal.contact') }}
      </h2>
      <p class="mt-2">
        <a
          :href="`mailto:${contactEmail}`"
          class="text-brand underline underline-offset-4"
        >
          {{ contactEmail }}
        </a>
      </p>
    </section>
  </article>
</template>
