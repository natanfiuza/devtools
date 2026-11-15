<?php

namespace App\Contracts\Ai;

use App\Support\Ai\LlmCompletion;

/**
 * Fronteira com o provedor de modelos.
 *
 * O contrato e minimo de proposito: mensagens do prompt entram, texto e usage
 * saem. Quem monta o prompt, valida o contrato e decide retentativa e o job -
 * trocar DeepSeek por outro provedor compativel nao muda o nucleo.
 */
interface LlmProvider
{
    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @param  string|null  $model  Modelo especifico do uso; null usa o modelo
     *                              configurado em `ai.model` (F7, ADR-140).
     */
    public function complete(array $messages, ?string $model = null): LlmCompletion;
}
