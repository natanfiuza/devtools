<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import type { CommentThreadError } from '@/composables/useCommentThread';

/**
 * Formulario de comentario, resposta e edicao (F7, ADR-139).
 *
 * O teto vem da config do servidor; o contador e informativo e a validacao
 * final continua no servidor. A mesma caixa serve aos tres usos para que o
 * texto visivel e as mensagens nao divirjam entre criar, responder e editar.
 */

const props = withDefaults(
    defineProps<{
        maxLength: number;
        busy?: boolean;
        error?: CommentThreadError | null;
        initialBody?: string;
        kind?: 'root' | 'reply' | 'edit';
        compact?: boolean;
    }>(),
    {
        busy: false,
        error: null,
        initialBody: '',
        kind: 'root',
        compact: false,
    },
);

const emit = defineEmits<{
    submit: [body: string];
    cancel: [];
}>();

const { t } = useI18n();

const body = ref(props.initialBody);
const localError = ref<'empty' | 'too_long' | null>(null);

const placeholder = computed(() =>
    props.kind === 'reply'
        ? t('colors.comments.reply_placeholder')
        : t('colors.comments.placeholder'),
);

const submitLabel = computed(() => {
    if (props.kind === 'edit') {
        return t('colors.comments.edit_save');
    }

    return props.kind === 'reply'
        ? t('colors.comments.reply_submit')
        : t('colors.comments.submit');
});

const errorMessage = computed<string | null>(() => {
    if (localError.value === 'empty') {
        return t('colors.comments.error_empty');
    }

    if (localError.value === 'too_long') {
        return t('colors.comments.error_too_long', {
            max: props.maxLength,
        });
    }

    if (props.error === null) {
        return null;
    }

    return t(`colors.comments.error_${props.error}`);
});

function submit(): void {
    const text = body.value.trim();

    if (text === '') {
        localError.value = 'empty';

        return;
    }

    if (text.length > props.maxLength) {
        localError.value = 'too_long';

        return;
    }

    localError.value = null;
    emit('submit', text);
}
</script>

<template>
  <form
    class="flex flex-col gap-2"
    data-test="comment-form"
    @submit.prevent="submit"
  >
    <textarea
      v-model="body"
      :maxlength="maxLength"
      :placeholder="placeholder"
      :rows="compact ? 2 : 3"
      class="w-full resize-y rounded-lg border border-border bg-surface px-3 py-2 text-sm focus:outline-2 focus:outline-offset-2 focus:outline-ring"
      :aria-label="placeholder"
      data-test="comment-form-input"
    />

    <div class="flex flex-wrap items-center justify-between gap-2">
      <span
        class="text-xs text-muted-foreground"
        data-test="comment-form-counter"
      >
        {{ body.length }} / {{ maxLength }}
      </span>

      <div class="flex items-center gap-2">
        <Button
          v-if="kind !== 'root'"
          type="button"
          variant="ghost"
          size="sm"
          data-test="comment-form-cancel"
          @click="emit('cancel')"
        >
          {{ t('colors.comments.cancel') }}
        </Button>
        <Button
          type="submit"
          size="sm"
          :disabled="busy"
          data-test="comment-form-submit"
        >
          {{ submitLabel }}
        </Button>
      </div>
    </div>

    <p
      v-if="errorMessage !== null"
      class="text-xs text-destructive"
      role="alert"
      data-test="comment-form-error"
    >
      {{ errorMessage }}
    </p>
  </form>
</template>
