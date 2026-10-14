<?php

namespace App\Actions\Admin;

use App\Enums\LedgerEntryType;
use App\Models\UsageLedger;
use App\Models\User;
use App\Support\Quota;

/**
 * Concede cota a uma conta: uma linha `grant` no livro-razao (ADR-027).
 *
 * A quantidade e na unidade do plano efetivo - usos no Free, tokens nos pagos
 * (App\Support\Quota). Conceder "1000" para quem esta no Free e conceder 1000
 * usos, nao 1000 tokens: a unidade e do plano, e nao uma escolha da tela.
 *
 * Nao existe saldo em coluna: a concessao aumenta o que sobra porque entra
 * como credito na soma, do mesmo jeito que um estorno.
 */
final class GrantQuota
{
    public function __invoke(User $user, int $amount): UsageLedger
    {
        $unit = Quota::for($user)->unit();

        return $user->usageLedger()->create([
            'type' => LedgerEntryType::Grant,
            'tokens' => $unit === 'tokens' ? $amount : 0,
            'uses' => $unit === 'uses' ? $amount : 0,
        ]);
    }
}
