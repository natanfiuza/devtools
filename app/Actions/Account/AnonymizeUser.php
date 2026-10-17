<?php

namespace App\Actions\Account;

use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Exclui a conta apagando o que identifica a pessoa e mantendo o que a
 * contabilidade precisa (ADR-028).
 *
 * A linha nao pode ser apagada: `payments.user_id` e uma chave estrangeira com
 * `restrictOnDelete`, porque nota fiscal nao se apaga. Entao "excluir" aqui e
 * anonimizar - nome, e-mail, senha, vinculos e preferencias saem, e o que
 * sobra e um identificador sem dono.
 *
 * Depois disto a conta nao entra mais: sem senha (o login por senha recusa) e
 * sem vinculo social (o login social nao encontra). O e-mail volta a ficar
 * livre, porque o endereco da linha passa a ser um endereco de dominio
 * reservado, que nao existe e nao recebe nada.
 *
 * O que fica: `payments` e `subscriptions` (as linhas, com a assinatura
 * vigente ja encerrada) e o livro-razao do consumo, que nao identifica
 * ninguem. O que sai: sessoes abertas, para a exclusao valer na hora.
 */
final class AnonymizeUser
{
    /**
     * Dominio reservado pela RFC 2606: nunca resolve, nunca recebe e-mail.
     */
    private const PLACEHOLDER_DOMAIN = 'removido.invalid';

    public function __invoke(User $user): void
    {
        $id = $user->getKey();

        DB::transaction(function () use ($user, $id): void {
            $user->subscriptions()
                ->current()
                ->update(['status' => SubscriptionStatus::Canceled->value]);

            $user->forceFill([
                'name' => '',
                'username' => null,
                'email' => 'removido+'.$id.'@'.self::PLACEHOLDER_DOMAIN,
                'password' => null,
                'remember_token' => null,
                'email_verified_at' => null,
                'suspended_at' => null,
                'role' => UserRole::User,
                // Idioma e tema sao preferencia da pessoa, e nao estado da
                // conta: vao junto.
                'locale' => null,
                'appearance' => null,
                'anonymized_at' => now(),
            ])->save();

            $user->socialAccounts()->delete();
            $user->subscriptionIntents()->delete();

            DB::table('sessions')->where('user_id', $id)->delete();
        });
    }
}
