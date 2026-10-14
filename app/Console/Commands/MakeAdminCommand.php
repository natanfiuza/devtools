<?php

namespace App\Console\Commands;

use App\Actions\Admin\RecordAdminAction;
use App\Enums\AdminActionType;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Cria o primeiro admin - e os seguintes, se preciso.
 *
 * O deploy roda `migrate --force`, nao `db:seed`, e nao existe tela antes de
 * existir admin: o primeiro admin nasce aqui (SPEC secao 11.2, ADR-025). Os
 * demais sao promovidos pela propria tela de contas.
 */
class MakeAdminCommand extends Command
{
    protected $signature = 'devtools:admin {email : E-mail da conta} {--revoke : Rebaixa para usuario comum}';

    protected $description = 'Promove (ou rebaixa) uma conta ao papel de admin';

    public function handle(RecordAdminAction $record): int
    {
        $email = $this->argument('email');

        if (! is_string($email) || trim($email) === '') {
            $this->error('Informe o e-mail da conta: devtools:admin usuario@exemplo.com');

            return self::FAILURE;
        }

        $revoke = $this->option('revoke') === true;

        $email = trim($email);

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->error("Nenhuma conta com o e-mail {$email}.");

            return self::FAILURE;
        }

        $role = $revoke ? UserRole::User : UserRole::Admin;

        if ($user->role === $role) {
            $this->info("A conta {$email} ja esta como {$role->value}.");

            return self::SUCCESS;
        }

        $user->forceFill(['role' => $role])->save();

        $record(
            null,
            $revoke ? AdminActionType::UserRoleRevoked : AdminActionType::UserRoleGranted,
            $user,
            ['via' => 'cli', 'para' => $role->value],
        );

        $this->info($revoke
            ? "A conta {$email} voltou a ser usuario comum."
            : "A conta {$email} agora e admin.");

        return self::SUCCESS;
    }
}
