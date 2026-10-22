<?php

namespace App\Enums;

/**
 * Estado do processamento de uma notificacao do gateway.
 *
 * `ignored` e para topico desconhecido ou sem efeito local; `failed` guarda o
 * erro para o admin ver e reprocessar. O evento e gravado antes de o job
 * rodar, entao um replay nunca perde a notificacao.
 */
enum WebhookEventStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Processed = 'processed';
    case Failed = 'failed';
    case Ignored = 'ignored';
}
