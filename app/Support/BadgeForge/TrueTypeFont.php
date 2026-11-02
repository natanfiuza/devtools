<?php

namespace App\Support\BadgeForge;

use RuntimeException;

/**
 * Leitor minimo de TrueType para gerar a tabela de avancos de glifos.
 *
 * Le apenas `head` (unitsPerEm), `hhea`/`hmtx` (largura) e `cmap` formatos 4
 * e 12 (code point -> glifo). O avanco sai normalizado para 1000 unidades por
 * em, que e a unidade do font-metrics.json - Fira Code declara 2000 e por
 * isso precisa da normalizacao.
 *
 * Documento de referencia: OpenType Font File, tabelas head, hhea, hmtx e
 * cmap. Nao e um parser completo: formatos de cmap antigos (0 e 6) e fontes
 * variaveis ficam de fora; as fontes empacotadas usam 4/12.
 */
final class TrueTypeFont
{
    private ?string $data = null;

    /** @var array<string, int> */
    private array $tables = [];

    private ?int $unitsPerEm = null;

    private ?int $numberOfHMetrics = null;

    /** @var array<string, mixed>|null */
    private ?array $cmap = null;

    public function __construct(private readonly string $path) {}

    public function unitsPerEm(): int
    {
        if ($this->unitsPerEm === null) {
            $head = $this->tableOffset('head');
            $this->unitsPerEm = $this->uint16($head + 18);

            if ($this->unitsPerEm <= 0) {
                throw new RuntimeException('unitsPerEm invalido em '.$this->path);
            }
        }

        return $this->unitsPerEm;
    }

    /**
     * Avanco em unidades por em normalizadas para 1000, ou nulo quando o
     * code point nao tem glifo mapeado.
     */
    public function advanceWidth(int $codePoint): ?int
    {
        $glyph = $this->glyphIndex($codePoint);

        if ($glyph <= 0) {
            return null;
        }

        $hmtx = $this->tableOffset('hmtx');
        $index = min($glyph, $this->numberOfHMetrics() - 1);
        $advance = $this->uint16($hmtx + $index * 4);
        $unitsPerEm = $this->unitsPerEm();

        return intdiv($advance * 1000 + intdiv($unitsPerEm, 2), $unitsPerEm);
    }

    private function numberOfHMetrics(): int
    {
        if ($this->numberOfHMetrics === null) {
            $hhea = $this->tableOffset('hhea');
            $this->numberOfHMetrics = $this->uint16($hhea + 34);

            if ($this->numberOfHMetrics <= 0) {
                throw new RuntimeException('numberOfHMetrics invalido em '.$this->path);
            }
        }

        return $this->numberOfHMetrics;
    }

    private function glyphIndex(int $codePoint): int
    {
        $cmap = $this->cmap();

        if ($cmap['format'] === 12) {
            foreach ($cmap['groups'] as $group) {
                if ($codePoint >= $group[0] && $codePoint <= $group[1]) {
                    return $group[2] + ($codePoint - $group[0]);
                }
            }

            return 0;
        }

        foreach ($cmap['segments'] as $segment) {
            if ($codePoint > $segment['end']) {
                continue;
            }

            if ($codePoint < $segment['start']) {
                return 0;
            }

            if ($segment['rangeOffset'] === 0) {
                return ($codePoint + $segment['delta']) & 0xFFFF;
            }

            $address = $segment['rangeOffsetAddress'] + $segment['rangeOffset'] + 2 * ($codePoint - $segment['start']);
            $glyph = $this->uint16($address);

            return $glyph === 0 ? 0 : ($glyph + $segment['delta']) & 0xFFFF;
        }

        return 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function cmap(): array
    {
        if ($this->cmap !== null) {
            return $this->cmap;
        }

        $cmap = $this->tableOffset('cmap');
        $count = $this->uint16($cmap + 2);
        $best = null;
        $bestScore = 0;

        for ($i = 0; $i < $count; $i++) {
            $platform = $this->uint16($cmap + 4 + $i * 8);
            // O offset do encoding record e relativo ao inicio da tabela cmap.
            $offset = $cmap + $this->uint32($cmap + 8 + $i * 8);
            $format = $this->uint16($offset);

            $score = match ($format) {
                12 => 4,
                4 => 3,
                default => 0,
            };

            // Windows Unicode e Unicode sao as plataformas que interessam.
            if ($score > 0 && ($platform === 3 || $platform === 0) && $score > $bestScore) {
                $best = $offset;
                $bestScore = $score;
            }
        }

        if ($best === null) {
            throw new RuntimeException('cmap sem subtable Unicode em '.$this->path);
        }

        $format = $this->uint16($best);

        return $this->cmap = $format === 12
            ? $this->parseFormat12($best)
            : $this->parseFormat4($best);
    }

    /**
     * @return array{format: int, segments: list<array{end: int, start: int, delta: int, rangeOffset: int, rangeOffsetAddress: int}>}
     */
    private function parseFormat4(int $offset): array
    {
        $segCount = intdiv($this->uint16($offset + 6), 2);
        $endBase = $offset + 14;
        $startBase = $endBase + 2 * $segCount + 2;
        $deltaBase = $startBase + 2 * $segCount;
        $rangeBase = $deltaBase + 2 * $segCount;
        $segments = [];

        for ($i = 0; $i < $segCount; $i++) {
            $segments[] = [
                'end' => $this->uint16($endBase + 2 * $i),
                'start' => $this->uint16($startBase + 2 * $i),
                'delta' => $this->int16($deltaBase + 2 * $i),
                'rangeOffset' => $this->uint16($rangeBase + 2 * $i),
                'rangeOffsetAddress' => $rangeBase + 2 * $i,
            ];
        }

        return ['format' => 4, 'segments' => $segments];
    }

    /**
     * @return array{format: int, groups: list<array{0: int, 1: int, 2: int}>}
     */
    private function parseFormat12(int $offset): array
    {
        $count = $this->uint32($offset + 12);
        $groups = [];

        for ($i = 0; $i < $count; $i++) {
            $base = $offset + 16 + $i * 12;

            $groups[] = [
                $this->uint32($base),
                $this->uint32($base + 4),
                $this->uint32($base + 8),
            ];
        }

        return ['format' => 12, 'groups' => $groups];
    }

    private function tableOffset(string $tag): int
    {
        if (isset($this->tables[$tag])) {
            return $this->tables[$tag];
        }

        $data = $this->data();
        $count = $this->uint16(4);

        for ($i = 0; $i < $count; $i++) {
            $record = 12 + $i * 16;

            if (substr($data, $record, 4) === $tag) {
                return $this->tables[$tag] = $this->uint32($record + 8);
            }
        }

        throw new RuntimeException('Tabela '.$tag.' ausente em '.$this->path);
    }

    private function data(): string
    {
        if ($this->data === null) {
            $contents = is_file($this->path) ? file_get_contents($this->path) : false;

            if ($contents === false) {
                throw new RuntimeException('Fonte ilegivel: '.$this->path);
            }

            $this->data = $contents;
        }

        return $this->data;
    }

    private function uint16(int $offset): int
    {
        $value = unpack('n', $this->slice($offset, 2));

        if ($value === false) {
            throw new RuntimeException('unpack falhou em '.$this->path);
        }

        return $value[1];
    }

    private function int16(int $offset): int
    {
        $value = $this->uint16($offset);

        return $value >= 0x8000 ? $value - 0x10000 : $value;
    }

    private function uint32(int $offset): int
    {
        $value = unpack('N', $this->slice($offset, 4));

        if ($value === false) {
            throw new RuntimeException('unpack falhou em '.$this->path);
        }

        return $value[1];
    }

    private function slice(int $offset, int $length): string
    {
        $slice = substr($this->data(), $offset, $length);

        if (strlen($slice) !== $length) {
            throw new RuntimeException('TTF truncado em '.$this->path);
        }

        return $slice;
    }
}
