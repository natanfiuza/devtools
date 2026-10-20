<?php

namespace App\Enums;

/**
 * Estado de uma solicitacao de IA.
 *
 * `queued` e `processing` sao estados de posse do job; `done` e `failed` sao
 * terminais e nao voltam a ser escritos (a liquidacao e idempotente). O polling
 * so devolve estes quatro.
 */
enum AiRequestStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Done = 'done';
    case Failed = 'failed';

    public function isTerminal(): bool
    {
        return $this === self::Done || $this === self::Failed;
    }
}
