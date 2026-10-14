<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Actions\Admin\RecordAdminAction;
use App\Enums\AdminActionType;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Atalho para a auditoria nos controladores do painel (ADR-025).
 *
 * Existe para a chamada ficar de uma linha no meio da acao: `$this->record(...)`
 * logo depois da mudanca. O ator e sempre quem esta autenticado - nas rotas do
 * painel isso e o admin, porque a impersonation nao alcanca o painel.
 */
trait RecordsAdminActions
{
    /**
     * @param  array<string, mixed>  $meta
     */
    protected function record(
        Request $request,
        AdminActionType $action,
        ?Model $target = null,
        array $meta = [],
    ): void {
        $actor = $request->user();

        app(RecordAdminAction::class)(
            $actor instanceof User ? $actor : null,
            $action,
            $target,
            $meta,
        );
    }
}
