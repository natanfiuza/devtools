<?php

namespace App\Exceptions\Ai;

use RuntimeException;

/**
 * Base das falhas do nucleo de IA.
 *
 * As mensagens sao tecnicas e curtas de proposito: o schema, a descricao e o
 * texto do prompt nunca entram na excecao, no banco ou no log (CLAUDE.md).
 * Quem precisa do motivo para a tela usa um codigo de `AiFailureCode`.
 */
class AiException extends RuntimeException {}
