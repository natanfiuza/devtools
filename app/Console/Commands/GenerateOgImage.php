<?php

namespace App\Console\Commands;

use App\Support\Tools;
use GdImage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Desenha as imagens de compartilhamento (og:image): a padrao do site e uma por
 * tool que funciona.
 *
 * Comando de uso unico: os PNGs saem em public/ e vao comitados, entao em
 * producao ele nunca roda - o VPS nao precisa de GD nem de rede. E por isso que
 * as fontes sao baixadas aqui, na primeira execucao, para storage/app/og-fonts
 * (fora do git), em vez de entrarem no repositorio.
 *
 * O desenho segue o DESIGN - tipografico, sem decoracao. A imagem padrao traz o
 * wordmark e os nomes das tools registradas; a de cada tool troca a lista pelo
 * nome dela e pelos termos do proprio manifest (`keywords`), que sao o que o
 * App\Support\Tools entrega. Tudo sai do registro: mexeu nele, roda o comando
 * de novo. Nao ha frase em idioma nenhum - o site tem cinco idiomas e a imagem
 * e uma so.
 *
 * O canvas e quadrado (1200x1200): o WhatsApp exibe o preview numa area
 * quadrada e corta imagens horizontais. Nas redes que ainda cortam 1.91:1 as
 * bordas somem, entao o texto fica na faixa central (CROP_BAND_HEIGHT).
 *
 * Cores do tema escuro do DESIGN.md (--bg, --fg, --brand, --fg-muted).
 */
class GenerateOgImage extends Command
{
    protected $signature = 'seo:og-image';

    protected $description = 'Gera a imagem padrao e a de cada tool que funciona em public/';

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

    /** Na imagem da tool, o wordmark e coadjuvante: entra pequeno, no alto. */
    private const TOOL_WORDMARK_SIZE = 44;

    /** Piso do nome da tool: abaixo disto o texto fica menor que a lista. */
    private const TOOL_NAME_MIN_SIZE = 56;

    /** Altura da faixa central que o corte 1.91:1 das redes preserva. */
    private const CROP_BAND_HEIGHT = 628;

    /** Baseline do texto principal, acima do centro para a lista caber abaixo. */
    private const MAIN_BASELINE_OFFSET = 48;

    /** Distancia entre o baseline do texto principal e a primeira linha. */
    private const LINES_OFFSET = 96;

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

        $default = public_path((string) config('seo.image.file'));

        if (! $this->write($this->drawDefault($width, $height, $mono, $sans), $default)) {
            return self::FAILURE;
        }

        // Uma imagem por tool que funciona: e a imagem do card da pagina dela.
        // Tool que nao funciona nao tem pagina e nao precisa de card.
        foreach (Tools::available() as $tool) {
            $path = public_path($this->toolsDir().'/'.$tool['slug'].'.png');

            File::ensureDirectoryExists(dirname($path));

            if (! $this->write($this->drawTool($tool['slug'], $tool['keywords'], $width, $height, $mono, $sans), $path)) {
                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }

    /**
     * O desenho da imagem padrao: o wordmark e a lista de tools abaixo.
     *
     * @param  int<1, max>  $width
     * @param  int<1, max>  $height
     */
    private function drawDefault(int $width, int $height, string $mono, string $sans): GdImage
    {
        $image = $this->background($width, $height);

        // Wordmark `devtools_`, igual ao AppWordmark: o cursor na cor de marca.
        $baseline = $this->mainBaseline($height);
        $this->wordmark($image, $mono, self::WORDMARK_SIZE, self::MARGIN, $baseline);

        // Abaixo, o que o site tem: os nomes das tools, vindos do registro.
        $this->lines($image, $this->toolNames(), $sans, $baseline + self::LINES_OFFSET, $width);

        return $image;
    }

    /**
     * O desenho da pagina de uma tool: o wordmark pequeno no alto e o nome da
     * ferramenta no lugar do wordmark grande.
     *
     * A linha de baixo traz os termos do proprio manifest (`keywords`) em vez
     * dos nomes das outras tools - o card fala daquela ferramenta, e continua
     * sem frase em idioma nenhum.
     *
     * @param  list<string>  $keywords
     * @param  int<1, max>  $width
     * @param  int<1, max>  $height
     */
    private function drawTool(string $slug, array $keywords, int $width, int $height, string $mono, string $sans): GdImage
    {
        $image = $this->background($width, $height);

        $limit = $width - 2 * self::MARGIN;

        // O wordmark pequeno fica no alto da faixa central: nas redes que
        // cortam 1.91:1 ele nao desaparece junto com a borda de cima.
        $bandTop = (int) (($height - min($height, self::CROP_BAND_HEIGHT)) / 2);
        $this->wordmark($image, $mono, self::TOOL_WORDMARK_SIZE, self::MARGIN, $bandTop + self::TOOL_WORDMARK_SIZE + 24);

        $name = __("tools.{$slug}.name");
        $size = self::WORDMARK_SIZE;

        // Nome longo encolhe em vez de vazar: a imagem tem largura fixa e o
        // texto nunca chega na margem direita.
        while ($size > self::TOOL_NAME_MIN_SIZE && $this->textWidth($name, $sans, $size) > $limit) {
            $size -= 8;
        }

        $baseline = $this->mainBaseline($height);
        $this->text($image, $name, $sans, $size, self::MARGIN, $baseline, self::FOREGROUND);

        $this->lines($image, $keywords, $sans, $baseline + self::LINES_OFFSET, $width);

        return $image;
    }

    /**
     * Baseline do texto principal: um pouco acima do centro, para a lista de
     * termos caber abaixo sem sair da faixa central.
     */
    private function mainBaseline(int $height): int
    {
        return (int) ($height / 2) - self::MAIN_BASELINE_OFFSET;
    }

    /**
     * Fundo do desenho: o mesmo `--bg` do tema escuro, nas duas imagens.
     *
     * @param  int<1, max>  $width
     * @param  int<1, max>  $height
     */
    private function background(int $width, int $height): GdImage
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

        return $image;
    }

    /**
     * O wordmark `devtools_`, com o cursor na cor de marca (AppWordmark).
     */
    private function wordmark(GdImage $image, string $font, int $size, int $x, int $y): void
    {
        $this->text($image, 'devtools', $font, $size, $x, $y, self::FOREGROUND);

        $cursor = $x + $this->textWidth('devtools', $font, $size);
        $this->text($image, '_', $font, $size, $cursor, $y, self::BRAND);
    }

    /**
     * A lista de termos do desenho, quebrada na largura da imagem.
     *
     * @param  list<string>  $terms
     * @param  int<1, max>  $width
     */
    private function lines(GdImage $image, array $terms, string $font, int $y, int $width): void
    {
        $lines = $this->wrap(
            implode('  ·  ', $terms),
            $font,
            self::NAMES_SIZE,
            $width - 2 * self::MARGIN,
        );

        foreach ($lines as $line) {
            $this->text($image, $line, $font, self::NAMES_SIZE, self::MARGIN, $y, self::MUTED);
            $y += self::NAMES_LINE_HEIGHT;
        }
    }

    /**
     * Escreve o PNG, dizendo onde ele foi parar.
     */
    private function write(GdImage $image, string $path): bool
    {
        if (! imagepng($image, $path, 9)) {
            $this->error("Nao foi possivel escrever {$path}.");

            return false;
        }

        $this->info('Imagem escrita em '.$path.' ('.imagesx($image).'x'.imagesy($image).').');

        return true;
    }

    /** Diretorio das imagens por tool, dentro de public/. */
    private function toolsDir(): string
    {
        return trim((string) config('seo.image.tools_dir'), '/');
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
