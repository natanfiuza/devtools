<?php

namespace App\Support\BadgeForge\Personal;

use App\Models\BadgeAsset;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Arquivos das badges pessoais (F4, ADR-081).
 *
 * O banco guarda caminho **relativo** (`personal/{username}/{ulid}.svg`), nunca
 * absoluto: o prefixo muda entre ambientes. Toda leitura confere o prefixo
 * antes de tocar no disco, e a escrita e atomica (tmp + rename), o mesmo
 * padrao do `PngCache`.
 */
final class BadgeAssetStorage
{
    private const PREFIX = 'personal/';

    public function base(): string
    {
        return storage_path((string) config('badgeforge.personal_badges.path', 'app/data/tools/badgeforge'));
    }

    /**
     * Path relativo do arquivo de uma badge. O username vem do dono no momento
     * da gravacao e pode mudar depois; por isso ele fica congelado no path.
     */
    public function pathFor(BadgeAsset $asset, string $extension): ?string
    {
        $username = $asset->user->username;

        if (! is_string($username) || ! self::validUsername($username)) {
            return null;
        }

        return self::PREFIX.$username.'/'.$asset->getKey().'.'.($extension === 'png' ? 'png' : 'svg');
    }

    public function read(?string $relative): ?string
    {
        if (! $this->isValidRelative($relative)) {
            return null;
        }

        $path = $this->absolute($relative);

        if (! File::isFile($path)) {
            return null;
        }

        $contents = File::get($path);

        return $contents !== '' ? $contents : null;
    }

    public function write(string $relative, string $contents): void
    {
        if (! $this->isValidRelative($relative)) {
            throw new RuntimeException('Path de badge pessoal invalido.');
        }

        $path = $this->absolute($relative);
        $directory = dirname($path);

        File::ensureDirectoryExists($directory);

        $tmp = $path.'.'.bin2hex(random_bytes(8)).'.tmp';

        try {
            if (File::put($tmp, $contents) === false) {
                throw new RuntimeException('Nao foi possivel gravar o arquivo da badge pessoal.');
            }

            if (! @rename($tmp, $path)) {
                File::delete($tmp);

                throw new RuntimeException('Nao foi possivel publicar o arquivo da badge pessoal.');
            }
        } finally {
            if (File::isFile($tmp)) {
                File::delete($tmp);
            }
        }
    }

    public function delete(?string $relative): void
    {
        if ($this->isValidRelative($relative)) {
            File::delete($this->absolute($relative));
        }
    }

    public function isFresh(BadgeAsset $asset): bool
    {
        return $asset->expires_at !== null
            && $asset->expires_at->isFuture()
            && $this->read($asset->svg_path) !== null;
    }

    /**
     * Um arquivo so existe sob `personal/`; qualquer outro caminho vindo do
     * banco (corrompido ou adulterado) e ignorado.
     */
    private function isValidRelative(?string $relative): bool
    {
        if (! is_string($relative) || ! str_starts_with($relative, self::PREFIX)) {
            return false;
        }

        return ! str_contains($relative, '..')
            && ! str_contains($relative, "\0")
            && ! str_contains($relative, '\\');
    }

    private function absolute(string $relative): string
    {
        return $this->base().'/'.$relative;
    }

    private static function validUsername(string $username): bool
    {
        return $username !== '.'
            && $username !== '..'
            && preg_match('/\A[A-Za-z0-9_][A-Za-z0-9._-]{0,39}\z/', $username) === 1;
    }
}
