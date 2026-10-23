<?php

namespace App\Console\Commands;

use App\Actions\Account\AnonymizeUser;
use App\Actions\Account\ExportUserData;
use App\Models\User;
use App\Notifications\AccountPurgedNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

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
    public function handle(AnonymizeUser $anonymize, ExportUserData $exportUserData): int
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
            try {
                $export = $exportUserData($user);
                $json = json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                if ($json === false) {
                    throw new \RuntimeException('Falha ao serializar dados do usuario para JSON.');
                }

                $identifier = $user->username ?: (string) $user->getKey();
                $date = now()->format('Ymd');
                $fileName = "account-export-{$identifier}-{$date}.json";

                $user->notify((new AccountPurgedNotification($json, $fileName))->locale($user->locale));

                $anonymize($user);
                $purged++;
            } catch (Throwable $e) {
                Log::error("Falha ao processar expurgo/notificacao da conta ID {$user->getKey()}: {$e->getMessage()}", [
                    'user_id' => $user->getKey(),
                    'exception' => $e,
                ]);
                $this->error("Erro ao expurgar usuario ID {$user->getKey()}: {$e->getMessage()}");
            }
        }

        $this->info("{$purged} conta(s) expurgada(s) com sucesso.");

        return self::SUCCESS;
    }
}
