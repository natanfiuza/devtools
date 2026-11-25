import { serve } from '@/lib/worker/serve';
import { diffDocuments } from './diff';
import type { DiffRequest, DiffResult } from '../types';

/**
 * O worker do diff.
 *
 * O Myers e o custo de verdade: dois textos grandes e sem relacao em comum
 * pagam caro ate o orcamento estourar, e isso nao pode cair em cima da
 * digitacao. Abaixo de 128 KiB (somados os dois lados) a mesma funcao roda na
 * propria tela.
 *
 * O erro de JSON invalido sai como **resultado**, nunca como excecao: quem
 * digita no painel passa por estados invalidos o tempo todo.
 */
serve<DiffRequest, DiffResult>((payload) => diffDocuments(payload));
