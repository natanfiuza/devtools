<?php

namespace App\Exceptions\Ai;

/**
 * Arquivo de prompt ausente, invalido ou fora do formato versionado.
 *
 * O nome do arquivo pode aparecer na mensagem (e metadado), mas o conteudo do
 * prompt nunca - ele pode conter instrucoes que nao devem vazar para o log.
 */
final class PromptException extends AiException
{
    public static function invalidName(string $template): self
    {
        return new self(sprintf('Prompt template name is invalid: %s', $template));
    }

    public static function notFound(string $template): self
    {
        return new self(sprintf('Prompt template is missing or unreadable: %s', $template));
    }

    public static function empty(string $template): self
    {
        return new self(sprintf('Prompt template is empty: %s', $template));
    }

    public static function versionMismatch(int $expected, int $actual): self
    {
        return new self(sprintf('Prompt version mismatch: expected %d, got %d.', $expected, $actual));
    }

    public static function targetMismatch(string $expected, string $actual): self
    {
        return new self(sprintf('Prompt target mismatch: expected %s, got %s.', $expected, $actual));
    }
}
