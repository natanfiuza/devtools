<?php

namespace App\Actions\Account;

use App\Models\NewsletterSubscriber;
use App\Models\Payment;
use App\Models\SocialAccount;
use App\Models\Subscription;
use App\Models\UsageLedger;
use App\Models\User;

/**
 * Tudo o que o sistema guarda sobre uma conta, num array pronto para virar
 * JSON (ADR-028).
 *
 * O direito de levar os proprios dados e o par do direito de apagar: quem
 * exclui a conta baixa isto antes. O que sai e o dado da pessoa e o que ele
 * gerou; o que nao sai e segredo de acesso - senha (que nem existe em claro),
 * `remember_token`, token de sessao e o token de gerenciamento da inscricao na
 * newsletter. Exportar credencial seria criar uma copia dela fora do lugar
 * onde ela e protegida.
 *
 * As datas saem em ISO 8601 (e nao no formato da tela): o arquivo e para ser
 * lido por outra maquina, e nao pelo idioma de quem baixou.
 */
final class ExportUserData
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(User $user): array
    {
        return [
            'exported_at' => now()->toIso8601String(),
            'account' => [
                'name' => $user->name,
                'email' => $user->email,
                'locale' => $user->locale,
                'appearance' => $user->appearance,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'created_at' => $user->created_at?->toIso8601String(),
            ],
            'social_accounts' => $user->socialAccounts()
                ->get()
                ->map(fn (SocialAccount $account): array => [
                    'provider' => $account->provider,
                    'linked_at' => $account->created_at?->toIso8601String(),
                ])
                ->all(),
            'newsletter' => $this->newsletter($user),
            'subscriptions' => $user->subscriptions()
                ->with('plan')
                ->orderBy('id')
                ->get()
                ->map(fn (Subscription $subscription): array => [
                    'plan' => $subscription->plan->name,
                    'gateway' => $subscription->gateway,
                    'status' => $subscription->status->value,
                    'start' => $subscription->current_period_start?->toIso8601String(),
                    'end' => $subscription->current_period_end?->toIso8601String(),
                ])
                ->all(),
            'payments' => $user->payments()
                ->with('plan')
                ->orderBy('id')
                ->get()
                ->map(fn (Payment $payment): array => [
                    'plan' => $payment->plan?->name,
                    'amount_cents' => $payment->amount_cents,
                    'currency' => $payment->currency,
                    'method' => $payment->method->value,
                    'status' => $payment->status->value,
                    'paid_at' => $payment->paid_at?->toIso8601String(),
                    'note' => $payment->note,
                ])
                ->all(),
            // O livro-razao do consumo: e da conta, e nao estado do sistema.
            'usage' => $user->usageLedger()
                ->orderBy('id')
                ->get()
                ->map(fn (UsageLedger $entry): array => [
                    'type' => $entry->type->value,
                    'tokens' => $entry->tokens,
                    'uses' => $entry->uses,
                    'at' => $entry->created_at?->toIso8601String(),
                ])
                ->all(),
        ];
    }

    /**
     * A inscricao na newsletter e identificada pelo e-mail: sem ele nao ha o
     * que procurar, e sem a inscricao o campo sai nulo.
     *
     * @return array<string, mixed>|null
     */
    private function newsletter(User $user): ?array
    {
        if ($user->email === '') {
            return null;
        }

        $subscriber = NewsletterSubscriber::query()
            ->where('email', $user->email)
            ->first();

        if ($subscriber === null) {
            return null;
        }

        return [
            'email' => $subscriber->email,
            'locale' => $subscriber->locale,
            'source' => $subscriber->source,
            'confirmed_at' => $subscriber->confirmed_at?->toIso8601String(),
            'unsubscribed_at' => $subscriber->unsubscribed_at?->toIso8601String(),
        ];
    }
}
