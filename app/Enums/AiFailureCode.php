<?php

namespace App\Enums;

/**
 * Motivo de uma solicitacao `failed`.
 *
 * O codigo e o contrato com a tela (traduzido por `ai.errors.*`); a mensagem do
 * provedor nunca entra no banco nem no log - pode conter eco do prompt. As
 * falhas de provedor que valem retry sao tratadas no job; so chega aqui o que
 * esgotou as tentativas ou nao tinha volta.
 */
enum AiFailureCode: string
{
    case ProviderUnavailable = 'provider_unavailable';
    case InvalidResponse = 'invalid_response';
    case UnsafeSql = 'unsafe_sql';
    case Timeout = 'timeout';
    case InvalidContract = 'invalid_contract';
    case Internal = 'internal';
}
