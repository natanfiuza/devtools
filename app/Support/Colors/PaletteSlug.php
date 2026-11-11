<?php

namespace App\Support\Colors;

use App\Models\ColorPalette;
use Illuminate\Support\Str;

/**
 * Slugs publicos de paleta (F4, ADR-124).
 *
 * O slug nasce do nome na primeira publicacao, nunca muda ao renomear e e
 * unico global porque a mesma URL serve os cinco idiomas. Reservados e
 * colisoes recebem sufixo; a unique index e a garantia final contra corrida.
 */
final class PaletteSlug
{
    public function base(string $name): string
    {
        $base = trim(Str::slug($name, '-'), '-');

        if ($base === '') {
            $base = 'palette';
        }

        $base = $this->fit($base);

        if (in_array($base, $this->reserved(), true)) {
            $base = $this->fit($base, '-palette');
        }

        return $base;
    }

    /**
     * Slugs livres a partir da base, tentando `-2` a `-5` e, no limite, um
     * sufixo aleatorio curto para nunca devolver um valor ja tomado.
     */
    public function unique(string $base): string
    {
        $candidate = $base;

        for ($attempt = 2; $attempt <= 5; $attempt++) {
            if (! $this->taken($candidate)) {
                return $candidate;
            }

            $candidate = $this->fit($base, '-'.$attempt);
        }

        if (! $this->taken($candidate)) {
            return $candidate;
        }

        return $this->fit($base, '-'.Str::lower(Str::random(4)));
    }

    /**
     * Reivindica um slug ja conhecido (restauracao) se ainda estiver livre;
     * caso contrario gera outro a partir do nome atual.
     */
    public function claimOrGenerate(?string $preferred, string $name): string
    {
        if ($preferred !== null && $preferred !== '' && ! $this->taken($preferred)) {
            return $preferred;
        }

        return $this->unique($this->base($name));
    }

    public function taken(string $slug): bool
    {
        return ColorPalette::query()->where('slug', $slug)->exists();
    }

    /**
     * @return list<string>
     */
    private function reserved(): array
    {
        /** @var list<string> $reserved */
        $reserved = config('colors.slug.reserved', []);

        return $reserved;
    }

    private function fit(string $value, string $suffix = ''): string
    {
        $max = max(1, (int) config('colors.slug.max_length', 60));
        $room = max(1, $max - strlen($suffix));

        return rtrim(substr($value, 0, $room), '-').$suffix;
    }
}
