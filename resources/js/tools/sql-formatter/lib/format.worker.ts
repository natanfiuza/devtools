import { serve } from '@/lib/worker/serve';
import { formatSql, type FormatRequest } from './format';

/**
 * O worker da formatacao.
 *
 * Ele existe por um numero: formatar 1 MB de SQL leva cerca de **nove
 * segundos**, e isso na thread principal e a aba travada. O mesmo vale para o
 * que a biblioteca lanca: o erro sai como **resultado** (e nao como excecao
 * solta), senao um texto que nao e SQL derrubaria o worker a cada tecla.
 */
serve<FormatRequest, ReturnType<typeof formatSql>>((payload) =>
    formatSql(payload.text, payload.options),
);
