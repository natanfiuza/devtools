<?php

namespace App\Exceptions\Ai;

/**
 * O provedor de IA nao esta configurado neste ambiente.
 *
 * A mensagem nao carrega valores de configuracao: `AI_BASE_URL`, `AI_MODEL` e
 * `AI_API_KEY` podem estar ausentes, mas nada disso vai para a resposta.
 */
final class AiUnavailableException extends AiException
{
    public static function notConfigured(): self
    {
        return new self('The AI provider is not configured.');
    }
}
