<?php

namespace App\Support\BadgeForge\Personal;

use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Regras de conta das badges pessoais (F4, ADR-081).
 *
 * Criar, renomear, trocar PAT e atualizar exigem plano pago; excluir e copiar
 * a URL continuam liberados depois de um downgrade. A conta precisa de
 * `username` valido porque ele e o primeiro segmento da URL publica.
 */
final class PersonalBadgeGuard
{
    /**
     * @throws ValidationException
     */
    public function ensurePaid(User $user): void
    {
        if ($user->effectivePlan()->isFree()) {
            throw ValidationException::withMessages([
                'badge' => __('validation.badge_asset.paid_only'),
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    public function ensureUsername(User $user): string
    {
        $username = $user->username;

        if (! is_string($username)
            || $username === '.'
            || $username === '..'
            || preg_match('/\A[A-Za-z0-9_][A-Za-z0-9._-]{0,39}\z/', $username) !== 1) {
            throw ValidationException::withMessages([
                'username' => __('validation.badge_asset.username_required'),
            ]);
        }

        return $username;
    }

    /**
     * @throws ValidationException
     */
    public function fail(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => __($message)]);
    }
}
