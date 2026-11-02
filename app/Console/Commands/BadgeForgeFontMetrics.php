<?php

namespace App\Console\Commands;

use App\Support\BadgeForge\TrueTypeFont;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Gera a tabela de avancos das fontes empacotadas.
 *
 * Le os mesmos code points de `families.sans` (ASCII + Latin-1) dos TTF da
 * config e grava `families.<slug>` (avanco da Regular, normalizado para 1000
 * unidades por em) e `family_bold_percent` (media da Bold sobre a Regular).
 * As familias `sans` e `helvetica` sao preservadas: a fonte delas e a propria
 * tabela aproximada, nao um TTF.
 *
 * O JSON versionado e o contrato dos dois renderers - por isso `--check`
 * compara o arquivo canonicalizado byte a byte e falha quando ha drift.
 */
final class BadgeForgeFontMetrics extends Command
{
    protected $signature = 'badgeforge:font-metrics
        {--check : Falha se o JSON versionado divergir dos TTF}';

    protected $description = 'Gera as metricas das fontes embutidas no font-metrics.json';

    public function handle(): int
    {
        $path = (string) config('badgeforge.font_metrics_path');
        $decoded = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        if (! is_array($decoded) || ! isset($decoded['families']['sans'])) {
            $this->error('font-metrics.json ausente ou sem families.sans.');

            return self::FAILURE;
        }

        $fallback = (int) ($decoded['fallback_units'] ?? 650);
        $codePoints = array_map('intval', array_keys($decoded['families']['sans']));
        sort($codePoints);

        $fontsPath = rtrim((string) config('badgeforge.fonts_path'), '/\\');
        $fonts = (array) config('badgeforge.fonts', []);

        if ($fonts === []) {
            $this->error('Nenhuma fonte configurada em badgeforge.fonts.');

            return self::FAILURE;
        }

        foreach ($fonts as $slug => $font) {
            $regular = new TrueTypeFont($fontsPath.DIRECTORY_SEPARATOR.$font['regular']);
            $bold = new TrueTypeFont($fontsPath.DIRECTORY_SEPARATOR.$font['bold']);

            $advances = [];
            $regularSum = 0;
            $boldSum = 0;

            foreach ($codePoints as $codePoint) {
                $advance = $regular->advanceWidth($codePoint);
                $advances[(string) $codePoint] = $advance ?? $fallback;

                $boldAdvance = $bold->advanceWidth($codePoint);

                if ($advance !== null && $boldAdvance !== null) {
                    $regularSum += $advance;
                    $boldSum += $boldAdvance;
                }
            }

            $decoded['families'][$slug] = $advances;

            $percent = (int) ($decoded['bold_percent'] ?? 100);

            if ($regularSum !== 0) {
                $percent = intdiv($boldSum * 100 + intdiv($regularSum, 2), $regularSum);
            }

            $decoded['family_bold_percent'][$slug] = $percent;
        }

        $encoded = $this->encode($decoded);
        $current = '';

        if (File::exists($path)) {
            $current = (string) File::get($path);
        }

        if ($this->option('check')) {
            if ($encoded !== $current) {
                $this->error('font-metrics.json divergiu dos TTF. Rode badgeforge:font-metrics.');

                return self::FAILURE;
            }

            $this->info('font-metrics.json esta em dia com os TTF.');

            return self::SUCCESS;
        }

        file_put_contents($path, $encoded);

        $this->info('font-metrics.json atualizado: '.implode(', ', array_keys($fonts)).'.');

        return self::SUCCESS;
    }

    /**
     * Serializa no mesmo layout legivel do arquivo atual: familias com 10
     * pares por linha e o resto em JSON indentado.
     *
     * @param  array<string, mixed>  $data
     */
    private function encode(array $data): string
    {
        $lines = [];
        $lines[] = '{';
        $lines[] = '  "units_per_em": '.(int) $data['units_per_em'].',';
        $lines[] = '  "fallback_units": '.(int) $data['fallback_units'].',';
        $lines[] = '  "bold_percent": '.(int) $data['bold_percent'].',';
        $lines[] = '  "zero_width_ranges": '.$this->ranges($data['zero_width_ranges'] ?? []).',';

        $families = $data['families'];
        $familyKeys = array_keys($families);
        $lines[] = '  "families": {';

        foreach ($familyKeys as $position => $key) {
            $lines[] = '    '.$this->string((string) $key).': {';

            $pairs = [];

            foreach ($families[$key] as $codePoint => $advance) {
                $pairs[] = $this->string((string) $codePoint).': '.(int) $advance;
            }

            $chunks = array_chunk($pairs, 10);

            foreach ($chunks as $index => $chunk) {
                $suffix = $index === count($chunks) - 1 ? '' : ',';
                $lines[] = '      '.implode(', ', $chunk).$suffix;
            }

            $lines[] = '    }'.($position === count($familyKeys) - 1 ? '' : ',');
        }

        $lines[] = '  },';

        $lines[] = '  "family_bold_percent": {';
        $bold = $data['family_bold_percent'] ?? [];
        $boldKeys = array_keys($bold);

        foreach ($boldKeys as $position => $key) {
            $lines[] = '    '.$this->string((string) $key).': '.(int) $bold[$key].($position === count($boldKeys) - 1 ? '' : ',');
        }

        $lines[] = '  }';
        $lines[] = '}';

        return implode("\n", $lines)."\n";
    }

    /**
     * @param  list<array{0: int, 1: int}>  $ranges
     */
    private function ranges(array $ranges): string
    {
        $parts = array_map(
            static fn (array $range): string => '['.(int) $range[0].', '.(int) $range[1].']',
            $ranges,
        );

        return '['.implode(', ', $parts).']';
    }

    private function string(string $value): string
    {
        return (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
