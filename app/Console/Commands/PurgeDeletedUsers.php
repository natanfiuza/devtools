<?php

namespace App\Console\Commands;

use App\Actions\Account\AnonymizeUser;
use App\Models\User;
use Illuminate\Console\Command;

class PurgeDeletedUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'accounts:purge-deleted {--days=30 : Quantidade de dias de carencia desde o soft delete}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expurga definitivamente as contas em soft delete apos a janela de carencia configurada (padrao: 30 dias).';

    /**
     * Execute the console command.
     */
    public function handle(AnonymizeUser $anonymize): int
    {
        $days = (int) $this->option('days');
        if ($days < 0) {
            $days = 30;
        }

        $cutoff = now()->subDays($days);

        $this->info("Buscando contas em soft delete antes de {$cutoff->toDateTimeString()}...");

        $users = User::onlyTrashed()
            ->where('deleted_at', '<=', $cutoff)
            ->get();

        $count = $users->count();

        if ($count === 0) {
            $this->info('Nenhuma conta elegivel para expurgo.');

            return self::SUCCESS;
        }

        $this->info("Expurgando {$count} conta(s)...");

        $purged = 0;
        foreach ($users as $user) {
            $anonymize($user);
            $purged++;
        }

        $this->info("{$purged} conta(s) expurgada(s) com sucesso.");

        return self::SUCCESS;
    }
}
