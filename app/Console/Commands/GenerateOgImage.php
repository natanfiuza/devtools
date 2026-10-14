<?php

namespace App\Console\Commands;

use App\Support\Tools;
use GdImage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Desenha a imagem padrao de compartilhamento (og:image).
 *
 * Comando de uso unico: o PNG sai em public/ e vai comitado, entao em producao
 * ele nunca roda - o VPS nao precisa de GD nem de rede. E por isso que as
 * fontes sao baixadas aqui, na primeira execucao, para storage/app/og-fonts
 * (fora do git), em vez de entrarem no repositorio.
 *
 * O desenho segue o DESIGN - tipografico, sem decoracao: o wordmark e os nomes
 * das tools registradas, que saem do proprio registro (App\Support\Tools).
 * Mexeu no registro ou nos nomes das tools, roda o comando de novo. Nao ha
 * frase em idioma nenhum: o site tem cinco idiomas e a imagem e uma so.
 *
 * Cores do tema escuro do DESIGN.md (--bg, --fg, --brand, --fg-muted).
 */
class GenerateOgImage extends Command
{
    protected $signature = 'seo:og-image';

    protected $description = 'Gera a imagem padrao de compartilhamento em public/';

    private const BACKGROUND = [0x0B, 0x0C, 0x0F];

    private const FOREGROUND = [0xE8, 0xE9, 0xEE];

    /** --brand no tema escuro: o ciano do cursor do wordmark. */
    private const BRAND = [0x66, 0xCE, 0xD6];

    private const MUTED = [0x87, 0x97, 0xB2];

    /** Margem esquerda do desenho (o texto nunca encosta na borda). */
    private const MARGIN = 88;

    /** Tamanho do wordmark e dos nomes das tools, em pontos. */
    private const WORDMARK_SIZE = 104;

    private const NAMES_SIZE = 30;

    private const NAMES_LINE_HEIGHT = 52;

    public function handle(): int
    {
        $mono = $this->font('mono');
        $sans = $this->font('sans');

        if ($mono === null || $sans === null) {
            return self::FAILURE;
        }

        $width = (int) config('seo.image.width');
        $height = (int) config('seo.image.height');

        if ($width < 1 || $height < 1) {
            $this->error('A largura e a altura da imagem precisam ser positivas (config/seo.php).');

            return self::FAILURE;
        }

        $image = $this->draw($width, $height, $mono, $sans);
        $path = public_path((string) config('seo.image.file'));

        if (! imagepng($image, $path, 9)) {
            $this->error("Nao foi possivel escrever {$path}.");

            return self::FAILURE;
        }

        $this->info("Imagem escrita em {$path} ({$width}x{$height}).");

        return self::SUCCESS;
    }

    /**
     * O desenho inteiro: fundo, wordmark e a lista de tools.
     *
     * @param  int<1, max>  $width
     * @param  int<1, max>  $height
     */
    private function draw(int $width, int $height, string $mono, string $sans): GdImage
    {
        $image = imagecreatetruecolor($width, $height);

        if ($image === false) {
            throw new RuntimeException('Nao foi possivel criar a imagem.');
        }

        imagefilledrectangle(
            $image,
            0,
            0,
            $width - 1,
            $height - 1,
            $this->color($image, self::BACKGROUND),
        );

        // Wordmark `devtools_`, igual ao AppWordmark: o cursor na cor de marca.
        $baseline = (int) ($height / 2) - 24;
        $this->text($image, 'devtools', $mono, self::WORDMARK_SIZE, self::MARGIN, $baseline, self::FOREGROUND);

        $cursor = self::MARGIN + $this->textWidth('devtools', $mono, self::WORDMARK_SIZE);
        $this->text($image, '_', $mono, self::WORDMARK_SIZE, $cursor, $baseline, self::BRAND);

        // Abaixo, o que o site tem: os nomes das tools, vindos do registro.
        $lines = $this->wrap(
            implode('  ·  ', $this->toolNames()),
            $sans,
            self::NAMES_SIZE,
            $width - 2 * self::MARGIN,
        );

        $y = $baseline + 96;

        foreach ($lines as $line) {
            $this->text($image, $line, $sans, self::NAMES_SIZE, self::MARGIN, $y, self::MUTED);
            $y += self::NAMES_LINE_HEIGHT;
        }

        return $image;
    }

    /**
     * Nomes das tools registradas, na ordem do registro.
     *
     * Os nomes sao iguais em todos os idiomas (sao nomes de produto), entao o
     * idioma do terminal nao muda nada aqui.
     *
     * @return list<string>
     */
    private function toolNames(): array
    {
        $names = [];

        foreach (Tools::all() as $tool) {
            $names[] = __("tools.{$tool['slug']}.name");
        }

        return $names;
    }

    /**
     * Caminho local da fonte, baixando na primeira execucao.
     *
     * A fonte e um detalhe do desenho, nao do site: fica em storage/, fora do
     * git, como as demais coisas que so a maquina precisa.
     */
    private function font(string $family): ?string
    {
        $directory = (string) config('seo.fonts.path');
        $file = (string) config("seo.fonts.{$family}.file");
        $url = (string) config("seo.fonts.{$family}.url");
        $path = rtrim($directory, '/\\').DIRECTORY_SEPARATOR.$file;

        if (is_file($path)) {
            return $path;
        }

        $this->line("Baixando {$file}...");

        $response = Http::timeout(60)->get($url);

        if (! $response->successful() || $response->body() === '') {
            $this->error("Nao foi possivel baixar {$file} de {$url} (HTTP {$response->status()}).");

            return null;
        }

        File::ensureDirectoryExists($directory);
        File::put($path, $response->body());

        return $path;
    }

    /**
     * Escreve uma linha de texto com a linha de base em `$y`.
     *
     * @param  array{int<0, 255>, int<0, 255>, int<0, 255>}  $rgb
     */
    private function text(GdImage $image, string $text, string $font, int $size, int $x, int $y, array $rgb): void
    {
        imagettftext($image, $size, 0, $x, $y, $this->color($image, $rgb), $font, $text);
    }

    /** Largura que o texto ocupa, em pixels. */
    private function textWidth(string $text, string $font, int $size): int
    {
        $box = imagettfbbox($size, 0, $font, $text);

        if ($box === false) {
            throw new RuntimeException('Nao foi possivel medir o texto.');
        }

        return (int) abs($box[2] - $box[0]);
    }

    /**
     * Quebra o texto na largura disponivel, palavra a palavra.
     *
     * @return list<string>
     */
    private function wrap(string $text, string $font, int $size, int $maxWidth): array
    {
        $lines = [];
        $line = '';

        foreach (explode(' ', $text) as $word) {
            $candidate = $line === '' ? $word : $line.' '.$word;

            if ($line !== '' && $this->textWidth($candidate, $font, $size) > $maxWidth) {
                $lines[] = $line;
                $line = $word;

                continue;
            }

            $line = $candidate;
        }

        if ($line !== '') {
            $lines[] = $line;
        }

        return $lines;
    }

    /**
     * Cor do desenho a partir do RGB.
     *
     * @param  array{int<0, 255>, int<0, 255>, int<0, 255>}  $rgb
     */
    private function color(GdImage $image, array $rgb): int
    {
        $color = imagecolorallocate($image, $rgb[0], $rgb[1], $rgb[2]);

        if ($color === false) {
            throw new RuntimeException('Nao foi possivel alocar a cor.');
        }

        return $color;
    }
}
