import { onBeforeUnmount, ref, type Ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { writeClipboard } from '@/lib/clipboard';

/**
 * O recado de "copiado" que aparece na barra de baixo da tool.
 *
 * Toda tool que copia cai no mesmo par: deu certo, o recado diz que copiou; nao
 * deu (sem permissao, ou sem area de transferencia), o recado diz para copiar
 * na mao - e a saida continua na tela, selecionavel. O recado sai sozinho
 * depois de alguns segundos, e o relogio e um so: copiar duas vezes seguidas
 * reinicia a contagem em vez de deixar dois avisos na fila.
 */

/** Quanto tempo o recado fica na tela, em milissegundos. */
export const FEEDBACK_MS = 2500;

/** Quanto tempo o icone de copiar fica como check, em milissegundos. */
export const COPY_CONFIRMATION_MS = 2000;

export interface CopyFeedback {
    /** O recado na tela, ja traduzido; `null` quando nao ha nenhum. */
    message: Ref<string | null>;
    /**
     * Copia e mostra o recado. Devolve se a copia deu certo.
     *
     * @param success recado proprio da tool (ex.: "caminho copiado: {path}"),
     * no lugar do "copiado" generico.
     */
    copy: (value: string, success?: string) => Promise<boolean>;
    clear: () => void;
}

export interface CopyConfirmation {
    /** `true` enquanto o botao deve mostrar o check no lugar do icone. */
    copied: Ref<boolean>;
    /** Marca o check; copiar de novo reinicia a contagem. */
    confirm: () => void;
}

/**
 * O check que aparece no proprio botao de copiar.
 *
 * O recado da barra de baixo continua sendo o `useCopyFeedback`; aqui e so o
 * icone, que vira check verde por `COPY_CONFIRMATION_MS` e volta. Cada botao
 * tem o seu, porque cada um copia um valor diferente.
 */
export function useCopyConfirmation(): CopyConfirmation {
    const copied = ref(false);

    let timer = 0;

    function confirm(): void {
        copied.value = true;

        window.clearTimeout(timer);
        timer = window.setTimeout(() => {
            copied.value = false;
        }, COPY_CONFIRMATION_MS);
    }

    onBeforeUnmount(() => {
        window.clearTimeout(timer);
    });

    return { copied, confirm };
}

export function useCopyFeedback(): CopyFeedback {
    const { t } = useI18n();
    const message = ref<string | null>(null);

    let timer = 0;

    function show(text: string): void {
        message.value = text;

        window.clearTimeout(timer);
        timer = window.setTimeout(() => {
            message.value = null;
        }, FEEDBACK_MS);
    }

    async function copy(value: string, success?: string): Promise<boolean> {
        try {
            await writeClipboard(value);
            show(success ?? t('tools.ui.actions.copied'));

            return true;
        } catch {
            // Sem permissao (ou sem API): sobra copiar na mao, e o recado diz.
            show(t('tools.ui.actions.copy_manual'));

            return false;
        }
    }

    onBeforeUnmount(() => {
        window.clearTimeout(timer);
    });

    return {
        message,
        copy,
        clear: () => {
            message.value = null;
        },
    };
}
