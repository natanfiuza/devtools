<?php

/**
 * Gera os icones raster a partir da mesma geometria de favicon.svg
 * (nada de arte binaria solta no repo, nada de dependencia de design).
 *
 * Uso: php resources/design/make-icons.php
 *
 * Saida:
 *   public/favicon.ico        32x32 (PNG dentro de um container ICO)
 *   public/apple-touch-icon.png 180x180
 *
 * O favicon.svg e o wordmark.svg sao escritos a mao; este script cobre o que
 * SVG nao resolve (iOS e navegadores antigos).
 */
const SURFACE = [0x11, 0x13, 0x18];
const FOREGROUND = [0xE8, 0xE9, 0xEE];
const BRAND = [0x66, 0xCE, 0xD6];

function canvas(int $size): GdImage
{
    $image = imagecreatetruecolor($size, $size);

    imagealphablending($image, false);
    imagesavealpha($image, true);

    // Cantos arredondados: o miolo e opaco, o resto e transparente.
    $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
    imagefilledrectangle($image, 0, 0, $size, $size, $transparent);

    $surface = imagecolorallocate($image, ...SURFACE);
    $radius = (int) round($size * 7 / 32);

    imagefilledrectangle($image, $radius, 0, $size - $radius, $size, $surface);
    imagefilledrectangle($image, 0, $radius, $size, $size - $radius, $surface);

    $corner = $radius * 2;
    imagefilledellipse($image, $radius, $radius, $corner, $corner, $surface);
    imagefilledellipse($image, $size - $radius, $radius, $corner, $corner, $surface);
    imagefilledellipse($image, $radius, $size - $radius, $corner, $corner, $surface);
    imagefilledellipse($image, $size - $radius, $size - $radius, $corner, $corner, $surface);

    imagealphablending($image, true);

    return $image;
}

/** Desenha o "d_" do favicon.svg em qualquer tamanho. */
function mark(int $size): GdImage
{
    $image = canvas($size);
    $scale = $size / 32;

    $foreground = imagecolorallocate($image, ...FOREGROUND);
    $brand = imagecolorallocate($image, ...BRAND);

    // Bowl do "d": um anel (elipse cheia de fundo dentro da elipse do traco).
    $outer = (int) round(10.5 * $scale);
    $stroke = (int) round(3 * $scale);
    $cx = (int) round(13.5 * $scale);
    $cy = (int) round(15.5 * $scale);

    imagefilledellipse($image, $cx, $cy, $outer, $outer, $foreground);
    imagefilledellipse($image, $cx, $cy, $outer - $stroke * 2, $outer - $stroke * 2, imagecolorallocate($image, ...SURFACE));

    // Haste do "d".
    imagefilledrectangle(
        $image,
        (int) round(17.5 * $scale),
        (int) round(5.5 * $scale),
        (int) round(20.5 * $scale),
        (int) round(25.5 * $scale),
        $foreground,
    );

    // Cursor "_" em --brand.
    imagefilledrectangle(
        $image,
        (int) round(6.5 * $scale),
        (int) round(25.5 * $scale),
        (int) round(19.5 * $scale),
        (int) round(28.5 * $scale),
        $brand,
    );

    return $image;
}

/** PNG em memoria. */
function png(GdImage $image, int $size): string
{
    ob_start();
    imagepng($image, null, 9);
    $bytes = (string) ob_get_clean();

    imagedestroy($image);

    return $bytes;
}

/** ICO com um PNG dentro (formato aceito desde o Vista). */
function ico(string $png, int $size): string
{
    $header = pack('vvv', 0, 1, 1);
    $entry = pack(
        'CCCCvvVV',
        $size === 256 ? 0 : $size,
        $size === 256 ? 0 : $size,
        0,
        0,
        1,
        32,
        strlen($png),
        6 + 16,
    );

    return $header.$entry.$png;
}

$root = dirname(__DIR__, 2);

file_put_contents($root.'/public/favicon.ico', ico(png(mark(32), 32), 32));
file_put_contents($root.'/public/apple-touch-icon.png', png(mark(180), 180));

echo "public/favicon.ico e public/apple-touch-icon.png gerados.\n";
