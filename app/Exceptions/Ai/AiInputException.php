<?php

namespace App\Exceptions\Ai;

/**
 * A entrada nao pode ser enviada ao provedor.
 *
 * `field` e `reason` sao o contrato com a API: a tela traduz o codigo e a
 * mensagem tecnicamente correta nao inclui o schema nem a descricao.
 */
final class AiInputException extends AiException
{
    private function __construct(
        public readonly string $field,
        public readonly string $reason,
    ) {
        parent::__construct('The AI input is not allowed: '.$reason.'.');
    }

    public static function schemaTooLarge(): self
    {
        return new self('schema', 'schema_too_large');
    }

    public static function descriptionTooLong(): self
    {
        return new self('description', 'description_too_long');
    }

    public static function unsupportedDialect(): self
    {
        return new self('dialect', 'unsupported_dialect');
    }
}
