<?php

namespace App\Support\Ai;

/**
 * Estimativa usada para reservar cota antes de o provedor responder.
 *
 * Entrada: quatro caracteres por token, arredondando para cima para nao
 * subestimar. Saida: `ai.estimate_output_tokens`, porque o tamanho da resposta
 * nao aparece no pedido. O job troca a estimativa pelo `usage` real.
 */
final class TokenEstimator
{
    /**
     * @param  list<array{role: string, content: string}>  $messages
     */
    public function totalTokens(array $messages): int
    {
        return $this->inputTokens($messages) + $this->outputTokens();
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     */
    public function inputTokens(array $messages): int
    {
        $characters = 0;

        foreach ($messages as $message) {
            $characters += mb_strlen($message['content'], 'UTF-8');
        }

        return $this->fromCharacters($characters);
    }

    public function outputTokens(): int
    {
        return max(0, (int) config('ai.estimate_output_tokens', 900));
    }

    public function fromCharacters(int $characters): int
    {
        return max(1, (int) ceil($characters / 4));
    }
}
