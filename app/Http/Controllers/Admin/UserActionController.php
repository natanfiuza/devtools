<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Account\AnonymizeUser;
use App\Actions\Admin\GrantPlan;
use App\Actions\Admin\GrantQuota;
use App\Enums\AdminActionType;
use App\Enums\UserRole;
use App\Http\Controllers\Admin\Concerns\RecordsAdminActions;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * As nove acoes do admin sobre uma conta (ADR-025).
 *
 * Cada uma autoriza pela policy antes de tocar no dado e registra a auditoria
 * depois de a mudanca ter acontecido: nao existe caminho que mude alguma coisa
 * sem deixar rastro, nem rastro de coisa que nao mudou.
 *
 * As que mexem em identidade ou acesso (suspender, excluir, entrar como) usam
 * `manage`, que recusa a propria conta e a conta de outro admin. As de suporte
 * (verificar, resetar, conceder) usam `update`, que vale para qualquer conta.
 */
class UserActionController extends Controller
{
    use RecordsAdminActions;

    /**
     * Marca o e-mail como verificado. Serve para quem nao recebeu o link.
     */
    public function verifyEmail(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        if ($user->email_verified_at !== null) {
            return $this->toast('info', __('admin.toasts.user_already_verified'));
        }

        $user->forceFill(['email_verified_at' => now()])->save();

        $this->record($request, AdminActionType::UserVerified, $user);

        return $this->toast('success', __('admin.toasts.user_verified'));
    }

    /**
     * Reenvia o e-mail de verificacao.
     */
    public function resendVerification(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        if ($user->email_verified_at !== null) {
            return $this->toast('info', __('admin.toasts.user_already_verified'));
        }

        $user->sendEmailVerificationNotification();

        $this->record($request, AdminActionType::UserVerificationResent, $user);

        return $this->toast('success', __('admin.toasts.user_verification_resent'));
    }

    /**
     * Manda o link de redefinir senha. Tambem e o caminho de quem entrou por
     * login social e quer uma senha (ADR-023).
     */
    public function sendPasswordReset(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        $status = Password::broker()->sendResetLink(['email' => $user->email]);

        if ($status !== Password::RESET_LINK_SENT) {
            return $this->toast('error', __('admin.toasts.user_password_reset_failed'));
        }

        $this->record($request, AdminActionType::UserPasswordResetSent, $user);

        return $this->toast('success', __('admin.toasts.user_password_reset_sent'));
    }

    /**
     * Promove a admin ou rebaixa para usuario comum.
     */
    public function updateRole(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('changeRole', $user);

        $validated = $request->validate([
            'role' => ['required', 'string', 'in:'.UserRole::User->value.','.UserRole::Admin->value],
        ]);

        $role = UserRole::from(is_string($validated['role'] ?? null) ? $validated['role'] : UserRole::User->value);
        $previous = $user->role;

        if ($previous === $role) {
            return $this->toast('info', __('admin.toasts.user_role_unchanged'));
        }

        $user->forceFill(['role' => $role])->save();

        $this->record(
            $request,
            $role->isAdmin() ? AdminActionType::UserRoleGranted : AdminActionType::UserRoleRevoked,
            $user,
            ['de' => $previous->value, 'para' => $role->value],
        );

        return $this->toast('success', __('admin.toasts.user_role_changed'));
    }

    /**
     * Suspende a conta: nao entra mais e a sessao aberta cai.
     */
    public function suspend(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('manage', $user);

        if ($user->isSuspended()) {
            return $this->toast('info', __('admin.toasts.user_already_suspended'));
        }

        $user->forceFill(['suspended_at' => now()])->save();

        $this->record($request, AdminActionType::UserSuspended, $user);

        return $this->toast('success', __('admin.toasts.user_suspended'));
    }

    /**
     * Devolve o acesso. Nao mexe no que a conta tinha: assinatura e cota
     * continuam onde estavam.
     */
    public function unsuspend(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('manage', $user);

        if (! $user->isSuspended()) {
            return $this->toast('info', __('admin.toasts.user_not_suspended'));
        }

        $user->forceFill(['suspended_at' => null])->save();

        $this->record($request, AdminActionType::UserUnsuspended, $user);

        return $this->toast('success', __('admin.toasts.user_unsuspended'));
    }

    /**
     * Concede um plano (venda manual ou cortesia). O Free encerra a assinatura.
     */
    public function grantPlan(Request $request, User $user, GrantPlan $grantPlan): RedirectResponse
    {
        Gate::authorize('update', $user);

        $validated = $request->validate([
            'plan' => ['required', 'integer', 'exists:plans,id'],
        ]);

        $plan = Plan::query()->findOrFail(
            is_numeric($validated['plan'] ?? null) ? (int) $validated['plan'] : 0,
        );

        $subscription = $grantPlan($user, $plan);

        $this->record($request, AdminActionType::UserPlanGranted, $user, [
            'plano' => $plan->slug,
            'ate' => $subscription?->current_period_end?->toDateString(),
        ]);

        return $this->toast('success', __('admin.toasts.user_plan_granted', ['plan' => $plan->name]));
    }

    /**
     * Soma cota na unidade do plano efetivo (usos no Free, tokens nos pagos).
     */
    public function grantQuota(Request $request, User $user, GrantQuota $grantQuota): RedirectResponse
    {
        Gate::authorize('update', $user);

        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1', 'max:100000000'],
        ]);

        $amount = is_numeric($validated['amount'] ?? null) ? (int) $validated['amount'] : 0;

        $entry = $grantQuota($user, $amount);

        $this->record($request, AdminActionType::UserQuotaGranted, $user, [
            'quantidade' => $amount,
            'unidade' => $entry->tokens > 0 ? 'tokens' : 'uses',
        ]);

        return $this->toast('success', __('admin.toasts.user_quota_granted'));
    }

    /**
     * Exclui a conta anonimizando (ADR-028). Exige o e-mail digitado: e a
     * unica acao sem volta do painel, e nao pode sair de um clique errado.
     */
    public function destroy(Request $request, User $user, AnonymizeUser $anonymize): RedirectResponse
    {
        Gate::authorize('manage', $user);

        $validated = $request->validate([
            'email' => ['required', 'string'],
        ]);

        $typed = is_string($validated['email'] ?? null) ? trim($validated['email']) : '';

        if (strcasecmp($typed, $user->email) !== 0) {
            throw ValidationException::withMessages([
                'email' => __('admin.users.delete_mismatch'),
            ]);
        }

        // O e-mail e o unico vestigio que sobra dele: entra na auditoria antes
        // de o dado sair, porque depois nao ha mais de onde tirar.
        $email = $user->email;

        $anonymize($user);

        $this->record($request, AdminActionType::UserDeleted, $user, ['email' => $email]);

        return $this->toast('success', __('admin.toasts.user_deleted'));
    }

    /**
     * Restaura a conta em soft delete durante a janela de carencia (ADR-035).
     */
    public function restore(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('restore', $user);

        $user->restore();

        $this->record($request, AdminActionType::UserRestored, $user);

        return $this->toast('success', __('admin.toasts.user_restored'));
    }

    /**
     * Resposta padrao das acoes: volta para a tela de origem com um aviso.
     * Vale para quem esta na lista e para quem esta no detalhe.
     */
    private function toast(string $type, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => $type, 'message' => $message]);

        return back();
    }
}
