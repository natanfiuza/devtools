<?php

namespace App\Console\Commands;

use App\Models\Base64ImageConversion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Remove arquivos fisicos de imagens base64 expiradas (ADR-055).
 *
 * - Free: arquivos removidos apos 24 horas (registro no banco permanece para sempre).
 * - Pago: arquivos removidos apos 30 dias (registro no banco permanece para sempre).
 * - Anonimos: arquivos fisicos em storage/app/data/base64_images/anonymous/ removidos apos 24 horas.
 */
final class PruneBase64Images extends Command
{
    /**
     * @var string
     */
    protected $signature = 'base64-images:prune {--dry-run : Apenas informa quantos arquivos seriam removidos}';

    /**
     * @var string
     */
    protected $description = 'Remove arquivos fisicos de imagens base64 apos o periodo de retencao.';

    public function handle(): int
    {
        $freeHours = (int) config('tools.limits.base64_image.free_retention_hours', 24);
        $paidDays = (int) config('tools.limits.base64_image.paid_retention_days', 30);
        $isDryRun = (bool) $this->option('dry-run');

        // 1. Cadastrados Free (> 24h)
        $freeConversions = Base64ImageConversion::query()
            ->where('is_paid_plan', false)
            ->whereNull('file_deleted_at')
            ->where('created_at', '<', now()->subHours($freeHours))
            ->get();

        // 2. Cadastrados Pagos (> 30 dias)
        $paidConversions = Base64ImageConversion::query()
            ->where('is_paid_plan', true)
            ->whereNull('file_deleted_at')
            ->where('created_at', '<', now()->subDays($paidDays))
            ->get();

        $totalDb = $freeConversions->count() + $paidConversions->count();

        // 3. Anonimos no disco (> 24h)
        $anonymousPath = storage_path('app/data/base64_images/anonymous');
        $anonymousExpiredCount = 0;
        $anonymousFilesToDelete = [];

        if (File::isDirectory($anonymousPath)) {
            $files = File::files($anonymousPath);
            $cutoff = now()->subHours($freeHours)->getTimestamp();

            foreach ($files as $file) {
                if ($file->getMTime() < $cutoff) {
                    $anonymousExpiredCount++;
                    $anonymousFilesToDelete[] = $file->getRealPath();
                }
            }
        }

        if ($isDryRun) {
            $this->info("Dry run: {$freeConversions->count()} Free, {$paidConversions->count()} Pagos e {$anonymousExpiredCount} anonimos seriam podados.");

            return self::SUCCESS;
        }

        foreach ($freeConversions as $conversion) {
            $conversion->deletePhysicalFiles();
        }

        foreach ($paidConversions as $conversion) {
            $conversion->deletePhysicalFiles();
        }

        foreach ($anonymousFilesToDelete as $path) {
            File::delete($path);
        }

        $this->info("Poda concluida: {$totalDb} registros de usuarios e {$anonymousExpiredCount} arquivos anonimos processados.");

        return self::SUCCESS;
    }
}
