<?php

namespace App\Actions\Subscriptions;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\User;

/**
 * Encerra as assinaturas que ainda valem - a corrente e as futuras (F6).
 *
 * Troca de plano, downgrade para o Free e concessao manual de outro plano
 * passam por aqui. Linhas com periodo ja encerrado nao sao tocadas: elas sao
 * historico, e reescrever o passado so esconderia o que aconteceu.
 *
 * A linha `$except` permite encerrar tudo em volta mantendo a assinatura que o
 * novo pagamento acabou de abrir.
 */
final class CancelActiveSubscriptions
{
    public function __invoke(User $user, ?Subscription $except = null): int
    {
        $subscriptions = $user->subscriptions()
            ->where('status', SubscriptionStatus::Active->value)
            ->where(function ($query): void {
                $query
                    ->whereNull('current_period_end')
                    ->orWhere('current_period_end', '>', now());
            })
            ->when(
                $except !== null,
                fn ($query) => $query->whereKeyNot($except->getKey()),
            )
            ->get();

        foreach ($subscriptions as $subscription) {
            $subscription->forceFill(['status' => SubscriptionStatus::Canceled])->save();
        }

        return $subscriptions->count();
    }
}
