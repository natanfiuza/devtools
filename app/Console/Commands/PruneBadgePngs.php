<?php

namespace App\Console\Commands;

use App\Models\BadgeAsset;
use App\Support\BadgeForge\Personal\BadgeAssetStorage;
use App\Support\BadgeForge\PngCache;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Poda os arquivos do BadgeForge (F2/F4, ADR-077/ADR-081).
 *
 * Dois alvos: os PNGs do cache estatico, content-addressed e sem teto, e os
 * arquivos orfaos de badges pessoais (linha excluida ou troca de username).
 * Um arquivo ainda referenciado por `svg_path`/`png_path` nunca e apagado.
 * Se a tabela de badges nao existir (deploy em curso), a poda pessoal e
 * ignorada: sem a lista de referencias, apagar seria arriscado.
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
    protected $description = 'Remove os PNGs antigos do cache estatico e os arquivos orfaos das badges pessoais.';

    public function handle(BadgeAssetStorage $storage): int
    {
        $isDryRun = (bool) $this->option('dry-run');

        $this->pruneStatic($isDryRun);
        $this->prunePersonal($storage, $isDryRun);

        return self::SUCCESS;
    }

    private function pruneStatic(bool $isDryRun): void
    {
        $directory = PngCache::directory();
        $hours = (int) config('badgeforge.png.prune_after_hours', 48);

        if (! File::isDirectory($directory)) {
            $this->info('Cache do BadgeForge vazio: nada a podar.');

            return;
        }

        $cutoff = now()->subHours($hours)->getTimestamp();
        $expired = [];

        foreach (File::files($directory) as $file) {
            if ($file->getExtension() === 'png' && $file->getMTime() < $cutoff) {
                $expired[] = $file->getPathname();
            }
        }

        if ($isDryRun) {
            $this->info('Dry run: '.count($expired).' PNG(s) estaticos seriam removidos.');

            return;
        }

        foreach ($expired as $path) {
            File::delete($path);
        }

        $this->info('Poda estatica concluida: '.count($expired).' PNG(s) removidos.');
    }

    private function prunePersonal(BadgeAssetStorage $storage, bool $isDryRun): void
    {
        $directory = $storage->base().'/personal';
        $hours = (int) config('badgeforge.personal_badges.prune_after_hours', 48);

        if (! File::isDirectory($directory)) {
            return;
        }

        try {
            $referenced = [];

            foreach (BadgeAsset::query()->get(['svg_path', 'png_path']) as $asset) {
                foreach ([$asset->svg_path, $asset->png_path] as $path) {
                    if (is_string($path) && $path !== '') {
                        $referenced[$path] = true;
                    }
                }
            }
        } catch (Throwable) {
            $this->warn('Badges pessoais indisponiveis: poda pessoal ignorada.');

            return;
        }

        $cutoff = now()->subHours($hours)->getTimestamp();
        $expired = [];

        foreach (File::allFiles($directory) as $file) {
            // O pathname entra sob `<base>/personal`, entao o relativo ja
            // comeca com `personal/` - prefixar de novo tornaria todo arquivo
            // "orfao" e apagaria badge referenciada.
            $relative = str_replace('\\', '/', substr(
                $file->getPathname(),
                strlen($storage->base()) + 1,
            ));

            if (! isset($referenced[$relative]) && $file->getMTime() < $cutoff) {
                $expired[] = $file->getPathname();
            }
        }

        if ($isDryRun) {
            $this->info('Dry run: '.count($expired).' arquivo(s) de badges pessoais seriam removidos.');

            return;
        }

        foreach ($expired as $path) {
            File::delete($path);
        }

        $this->info('Poda pessoal concluida: '.count($expired).' arquivo(s) removidos.');
    }
}
