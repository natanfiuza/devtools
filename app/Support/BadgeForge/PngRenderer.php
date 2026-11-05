<?php

namespace App\Support\BadgeForge;

use Imagick;

/**
 * Rasterizacao do SVG canonico em PNG (F2, ADR-077).
 *
 * O PNG sai em 1x natural e com fundo transparente (`png32`); badges mais
 * largos que `badgeforge.png.max_width` sao reduzidos proporcionalmente com
 * Lanczos. Sem a extensao `imagick` o servico informa `isSupported() = false`
 * e o controller responde 503 - nada aqui lanca excecao por ambiente.
 *
 * Nao e `final` de proposito: os testes substituem a rasterizacao real por um
 * mock quando nao ha Imagick ou quando querem observar o cache.
 */
class PngRenderer
{
    public function isSupported(): bool
    {
        return extension_loaded('imagick');
    }

    public function render(string $svg): string
    {
        $image = new Imagick;
        $image->setBackgroundColor('transparent');
        $image->readImageBlob($svg);
        $image->setImageFormat('png32');

        $maxWidth = (int) config('badgeforge.png.max_width', 600);

        if ($maxWidth > 0 && $image->getImageWidth() > $maxWidth) {
            $image->resizeImage($maxWidth, 0, Imagick::FILTER_LANCZOS, 1);
        }

        $png = $image->getImageBlob();

        $image->clear();
        $image->destroy();

        return $png;
    }
}
