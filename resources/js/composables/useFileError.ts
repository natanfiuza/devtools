import { computed, ref, type ComputedRef, type Ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { formatBytes } from '@/lib/bytes';
import type { FileErrorKind } from '@/lib/file';

/**
 * O motivo pelo qual um arquivo nao entrou, guardado para virar recado.
 *
 * Quem le o arquivo e `readTextFile` (lib/file.ts); aqui mora so o que fazer
 * com a recusa, porque o recado e o mesmo em toda tool: o tamanho em MB quando
 * o arquivo e grande demais, o formato quando e de outro tipo. O texto sai do
 * `tools.ui.states.*`, que e compartilhado.
 */

export interface FileError {
    kind: FileErrorKind;
    /** Tamanho do arquivo recusado, em bytes. */
    size: number;
}

export interface FileErrorState {
    error: Ref<FileError | null>;
    /** O recado traduzido, ou `null` quando nao ha recusa na tela. */
    message: ComputedRef<string | null>;
    fail: (kind: FileErrorKind, size: number) => void;
    clear: () => void;
}

/**
 * @param maxBytes teto de entrada da tool, em bytes - o mesmo que o servidor
 * manda por prop, para o recado citar o limite que realmente vale.
 * @param formats o que a caixa de arquivo aceita, ja traduzido (ex.: "JSON ou
 * TXT"), porque cada tool aceita um conjunto diferente.
 */
export function useFileError(
    maxBytes: () => number,
    formats: () => string,
): FileErrorState {
    const { t, locale } = useI18n();
    const error = ref<FileError | null>(null);

    const message = computed(() => {
        const current = error.value;

        if (current === null) {
            return null;
        }

        if (current.kind === 'file_too_large') {
            return t('tools.ui.states.file_too_large', {
                size: formatBytes(current.size, locale.value),
                limit: formatBytes(maxBytes(), locale.value),
            });
        }

        return current.kind === 'file_type'
            ? t('tools.ui.states.file_type', { formats: formats() })
            : t('tools.ui.states.file_unreadable');
    });

    return {
        error,
        message,
        fail: (kind, size) => {
            error.value = { kind, size };
        },
        clear: () => {
            error.value = null;
        },
    };
}
