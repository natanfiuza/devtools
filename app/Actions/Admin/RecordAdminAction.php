<?php

namespace App\Actions\Admin;

use App\Enums\AdminActionType;
use App\Models\AdminAction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Grava a auditoria de uma acao administrativa.
 *
 * Ponto unico de escrita: toda acao do painel passa por aqui, para nao existir
 * caminho que mude alguma coisa sem deixar rastro. `meta` e para o contexto
 * curto da mudanca; conteudo de schema, de prompt ou dado sensivel nunca entra
 * (regra do CLAUDE.md).
 *
 * Na linha de comando nao existe usuario autenticado: o ator vai nulo e quem
 * chamou se identifica em `meta`.
 */
final class RecordAdminAction
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __invoke(
        ?User $actor,
        AdminActionType $action,
        ?Model $target = null,
        array $meta = [],
    ): AdminAction {
        return AdminAction::create([
            'actor_id' => $actor?->id,
            'action' => $action,
            'target_type' => $target?->getMorphClass(),
            'target_id' => $target?->getKey(),
            'meta' => $meta === [] ? null : $meta,
            'ip' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }
}
