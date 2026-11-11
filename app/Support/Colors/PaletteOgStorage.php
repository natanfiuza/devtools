<?php

namespace App\Support\Colors;

use Illuminate\Support\Facades\File;

/**
 * Imagem OG dinamica de uma paleta publica (F4, ADR-125).
 *
 * O PNG e gerado no navegador e enviado; o servidor guarda um arquivo por
 * paleta em `storage/app/data/og_paleta_image/{ulid}.png`. A escrita e
 * atomica (tmp no mesmo diretorio + replace) para nunca servir um arquivo
 * pela metade.
 */
final class PaletteOgStorage
{
    public function path(string $paletteId): string
    {
        return storage_path((string) config('colors.og.path', 'app/data/og_paleta_image').'/'.$paletteId.'.png');
    }

    public function exists(string $paletteId): bool
    {
        return File::isFile($this->path($paletteId));
    }

    public function put(string $paletteId, string $bytes): void
    {
        $path = $this->path($paletteId);

        File::ensureDirectoryExists(dirname($path));
        File::replace($path, $bytes);
    }

    public function delete(string $paletteId): void
    {
        File::delete($this->path($paletteId));
    }
}
