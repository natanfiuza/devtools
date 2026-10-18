<?php

namespace App\Policies;

use App\Models\User;

/**
 * Quem pode o que sobre uma conta.
 *
 * O acesso ao painel e igual para todo admin; o que a policy protege sao as
 * acoes sobre uma conta especifica. Suspender, excluir e entrar como um admin
 * nao pode - nem sobre a propria conta. E a mesma trava para as tres, entao
 * ela vive em `manage` (ADR-025).
 */
final class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->isAdmin();
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->isAdmin();
    }

    /**
     * Acoes destrutivas ou de identidade: suspender, excluir e entrar como.
     */
    public function manage(User $actor, User $target): bool
    {
        return $actor->isAdmin() && ! $target->isAdmin() && ! $actor->is($target);
    }

    /**
     * Promover e rebaixar. Vale sobre qualquer conta, inclusive a de outro
     * admin - e por aqui que se deixa de ser admin - mas nunca sobre a
     * propria: rebaixar a si mesmo e trancar a porta por dentro, sem querer.
     */
    public function changeRole(User $actor, User $target): bool
    {
        return $actor->isAdmin() && ! $actor->is($target);
    }

    /**
     * Entrar como. A conta suspensa nao recebe visita: seria dar acesso a quem
     * acabou de perder o acesso, e a sessao do admin morreria no primeiro
     * clique, pelo middleware.
     */
    public function impersonate(User $actor, User $target): bool
    {
        return $this->manage($actor, $target) && ! $target->isSuspended();
    }

    /**
     * Restaurar conta em soft delete. Vale para qualquer conta comum que
     * esteja em soft delete e nao anonimizada, e nunca sobre a propria conta.
     */
    public function restore(User $actor, User $target): bool
    {
        return $this->manage($actor, $target) && $target->trashed() && ! $target->isAnonymized();
    }
}
