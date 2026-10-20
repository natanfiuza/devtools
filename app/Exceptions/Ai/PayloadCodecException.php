<?php

namespace App\Exceptions\Ai;

/**
 * O `canonical_payload` guardado nao pode ser comprimido ou lido.
 *
 * Nao carrega o payload na mensagem: o conteudo e o schema da pessoa.
 */
final class PayloadCodecException extends AiException
{
    public static function encodeFailed(): self
    {
        return new self('Canonical payload could not be compressed.');
    }

    public static function decodeFailed(): self
    {
        return new self('Canonical payload is not a valid compressed payload.');
    }

    public static function invalidShape(): self
    {
        return new self('Canonical payload does not match the expected shape.');
    }
}
