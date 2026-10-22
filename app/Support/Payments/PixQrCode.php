<?php

namespace App\Support\Payments;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * QR do Pix estatico como `data:` URI (F6).
 *
 * O SVG e gerado no servidor e embutido na pagina: sem CDN, sem dependencia
 * npm e sem requisicao extra para desenhar o QR. O `bacon/bacon-qr-code` ja
 * vinha com o Fortify (2FA); a F6 o declara como dependencia direta no
 * composer.json.
 */
final class PixQrCode
{
    private const SIZE = 320;

    private const MARGIN = 2;

    public static function dataUri(string $payload): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(self::SIZE, self::MARGIN),
            new SvgImageBackEnd,
        );

        $svg = (new Writer($renderer))->writeString($payload);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
