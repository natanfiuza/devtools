import { serve } from '@/lib/worker/serve';
import { convert } from './convert';
import type { ConvertRequest, ConvertResult } from '../types';

/**
 * O worker da conversao.
 *
 * Ele existe pelo tamanho: o teto de entrada e de 5 MB, e um YAML desse
 * tamanho leva o parser e o serializador a um custo que nao pode cair em cima
 * da digitacao. Abaixo de 128 KiB a mesma funcao roda na propria tela, sem o
 * custo de levantar um worker para uma conta de milissegundos.
 *
 * O erro do parser sai como **resultado** (e nao como excecao solta): um texto
 * invalido derrubaria o worker a cada tecla.
 */
serve<ConvertRequest, ConvertResult>((payload) => convert(payload));
