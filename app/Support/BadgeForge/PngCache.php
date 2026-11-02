<?php

namespace App\Support\BadgeForge;

use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

/**
 * Cache em disco do PNG, endereçado pelo sha256 do SVG (F2, ADR-077).
 *
 * Dois SVGs iguais produzem o mesmo arquivo e a mesma resposta; a escrita e
 * atomica (tmp + rename) para uma requisicao concorrente nunca ler um PNG
 * pela metade. O arquivo e apagado por `badgeforge:prune` quando fica
 * `prune_after_hours` sem ser reutilizado (mtime).
 */
final class PngCache
{
    public function __construct(
        private readonly PngRenderer $renderer,
    ) {}

    public function png(string $svg): string
    {
        $path = self::path($svg);

        if (File::isFile($path)) {
            $cached = File::get($path);

            if ($cached !== '') {
                return $cached;
            }
        }

        $png = $this->renderer->render($svg);

        $this->store($path, $png);

        return $png;
    }

    /** Diretorio do cache, relativo a `storage_path()`. */
    public static function directory(): string
    {
        return storage_path((string) config(
            'badgeforge.png.cache_path',
            'app/data/tools/badgeforge/static',
        ));
    }

    /** Caminho content-addressed do PNG de um SVG. */
    public static function path(string $svg): string
    {
        return self::directory().'/'.hash('sha256', $svg).'.png';
    }

    private function store(string $path, string $png): void
    {
        $directory = dirname($path);

        File::ensureDirectoryExists($directory);

        $tmp = $path.'.'.bin2hex(random_bytes(8)).'.tmp';

        try {
            if (File::put($tmp, $png) === false) {
                throw new RuntimeException('Nao foi possivel gravar o PNG do BadgeForge.');
            }

            // Se outra requisicao publicou o mesmo hash primeiro, o rename
            // pode falhar (Windows nao sobrescreve); o conteudo e identico.
            if (! @rename($tmp, $path)) {
                File::delete($tmp);
            }
        } catch (Throwable $exception) {
            File::delete($tmp);

            throw $exception;
        }
    }
}
