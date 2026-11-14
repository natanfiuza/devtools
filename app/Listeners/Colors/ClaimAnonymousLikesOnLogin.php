<?php

namespace App\Listeners\Colors;

use App\Actions\Colors\ClaimAnonymousLikes;
use App\Models\User;
use App\Support\Colors\AnonymousIdentity;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reivindica as curtidas anonimas no login (F6, ADR-135).
 *
 * O login nunca falha por causa desta operacao: em erro, a conta entra, o
 * cookie permanece e a reivindicacao e tentada no proximo login. O log e uma
 * frase fixa - cookie, identificador, IP e payload nunca entram em log.
 */
final class ClaimAnonymousLikesOnLogin
{
    public function __construct(private readonly ClaimAnonymousLikes $claim) {}

    public function handle(Login $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        $identifier = AnonymousIdentity::read(request());

        if ($identifier === null) {
            return;
        }

        try {
            ($this->claim)($user, $identifier);
        } catch (Throwable) {
            Log::warning('colors: falha ao reivindicar curtidas anonimas.');

            return;
        }

        Cookie::queue(AnonymousIdentity::forget());
    }
}
