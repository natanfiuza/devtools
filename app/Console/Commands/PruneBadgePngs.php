<?php

namespace App\Console\Commands;

use App\Support\BadgeForge\PngCache;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Remove do cache estatico os PNGs do BadgeForge sem uso recente (F2, ADR-077).
 *
 * O cache e content-addressed e sem teto: cada texto novo gera um arquivo.
 * O poda olha o mtime e apaga o que passou de `badgeforge.png.prune_after_hours`;
 * um PNG apagado e gerado de novo sob demanda, entao a remocao e segura.
 */
final class PruneBadgePngs extends Command
{
    /**
     * @var string
     */
    protected $signature = 'badgeforge:prune {--dry-run : Apenas informa quantos arquivos seriam removidos}';

    /**
     * @var string
     */
    protected $description = 'Remove os PNGs antigos do cache estatico do BadgeForge.';

    public function handle(): int
    {
        $directory = PngCache::directory();
        $hours = (int) config('badgeforge.png.prune_after_hours', 48);
        $isDryRun = (bool) $this->option('dry-run');

        if (! File::isDirectory($directory)) {
            $this->info('Cache do BadgeForge vazio: nada a podar.');

            return self::SUCCESS;
        }

        $cutoff = now()->subHours($hours)->getTimestamp();
        $expired = [];

        foreach (File::files($directory) as $file) {
            if ($file->getExtension() === 'png' && $file->getMTime() < $cutoff) {
                $expired[] = $file->getPathname();
            }
        }

        if ($isDryRun) {
            $this->info('Dry run: '.count($expired).' PNG(s) seriam removidos.');

            return self::SUCCESS;
        }

        foreach ($expired as $path) {
            File::delete($path);
        }

        $this->info('Poda concluida: '.count($expired).' PNG(s) removidos.');

        return self::SUCCESS;
    }
}
