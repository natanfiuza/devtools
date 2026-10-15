import { serve } from '@/lib/worker/serve';
import { search, type RegexRequest, type RegexResult } from './run';

/**
 * O worker da busca.
 *
 * Ele existe por causa de uma expressao so: `(a+)+$` sobre trinta `a` e uma
 * letra que nao casa leva **anos** (retrocesso catastrofico), e a mesma conta na
 * thread principal e a aba travada sem saida - nem o botao de parar responde. No
 * worker, quem trava e ele, e o host o mata pelo relogio (`runner.ts`).
 *
 * O padrao invalido sai como **resultado** e nao como excecao: ele esta sendo
 * digitado tecla a tecla, e derrubar o worker a cada tecla jogaria a busca
 * seguinte na thread principal - exatamente onde ela nao pode estar.
 */
serve<RegexRequest, RegexResult>(search);
