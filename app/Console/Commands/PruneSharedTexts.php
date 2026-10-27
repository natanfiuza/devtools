<?php

namespace App\Console\Commands;

use App\Models\SharedText;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Remove textos anonimos expirados e arquivos fisicos do disco (ADR-058).
 */
final class PruneSharedTexts extends Command
{
    /**
     * @var string
     */
    protected $signature = 'share-text:prune {--dry-run : Apenas informa quantos arquivos seriam removidos}';

    /**
     * @var string
     */
    protected $description = 'Remove textos compartilhados por usuarios anonimos que expiraram apos 72 horas.';

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');

        // 1. Textos anonimos expirados no banco de dados
        $expiredTexts = SharedText::query()
            ->expired()
            ->whereNull('user_id')
            ->get();

        $totalDb = $expiredTexts->count();

        // 2. Arquivos orfaos ou expirados no diretorio anonymous
        $anonymousPath = storage_path('app/data/sharetext/anonymous');
        $anonymousExpiredCount = 0;
        $anonymousFilesToDelete = [];

        $retentionHours = (int) config('tools.limits.share_text.anonymous_retention_hours', 72);
        $cutoff = now()->subHours($retentionHours)->getTimestamp();

        if (File::isDirectory($anonymousPath)) {
            $files = File::files($anonymousPath);

            foreach ($files as $file) {
                if ($file->getMTime() < $cutoff) {
                    $anonymousExpiredCount++;
                    $anonymousFilesToDelete[] = $file->getRealPath();
                }
            }
        }

        if ($isDryRun) {
            $this->info("Dry run: {$totalDb} registros no banco e {$anonymousExpiredCount} arquivos fisicos anonimos seriam podados.");

            return self::SUCCESS;
        }

        foreach ($expiredTexts as $text) {
            $text->deletePhysicalFile();
            $text->delete();
        }

        foreach ($anonymousFilesToDelete as $path) {
            if (File::exists($path)) {
                File::delete($path);
            }
        }

        $this->info("Poda concluida: {$totalDb} registros e {$anonymousExpiredCount} arquivos anonimos processados.");

        return self::SUCCESS;
    }
}
