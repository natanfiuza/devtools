<?php

namespace App\Support\Colors;

use App\Models\LayoutSystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Slugs publicos de sistema de layout (F4, ADR-171).
 *
 * Nasce do nome na primeira publicacao, nunca muda ao renomear e e unico
 * global porque a mesma URL serve os cinco idiomas. Arquivar reserva o valor
 * em `archived_slug` (unico) e excluir grava em `layout_system_retired_slugs`:
 * um slug nunca e reemitido, entao o link antigo responde 404 para sempre.
 */
final class LayoutSystemSlug
{
    public function base(string $name): string
    {
        $base = trim(Str::slug($name, '-'), '-');

        if ($base === '') {
            $base = 'layout-system';
        }

        $base = $this->fit($base);

        if (in_array($base, $this->reserved(), true)) {
            $base = $this->fit($base, '-system');
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
    public function claimOrGenerate(?string $preferred, string $name, ?string $exceptId = null): string
    {
        if ($preferred !== null && $preferred !== '' && ! $this->taken($preferred, $exceptId)) {
            return $preferred;
        }

        return $this->unique($this->base($name));
    }

    /**
     * Um slug esta tomado se algum sistema ativo o usa, se esta arquivado em
     * outro sistema ou se ja foi aposentado pela exclusao.
     *
     * `$exceptId` ignora o proprio sistema: na restauracao, o valor ainda mora
     * em `archived_slug` da mesma linha que esta sendo reivindicada.
     */
    public function taken(string $slug, ?string $exceptId = null): bool
    {
        $active = LayoutSystem::query()->where('slug', $slug);

        if ($exceptId !== null) {
            $active->whereKeyNot($exceptId);
        }

        if ($active->exists()) {
            return true;
        }

        $archived = LayoutSystem::query()->where('archived_slug', $slug);

        if ($exceptId !== null) {
            $archived->whereKeyNot($exceptId);
        }

        if ($archived->exists()) {
            return true;
        }

        // A tabela ainda pode nao existir na janela entre o deploy do codigo e
        // o `migrate`: nesse intervalo nada esta aposentado.
        if (! Schema::hasTable('layout_system_retired_slugs')) {
            return false;
        }

        return DB::table('layout_system_retired_slugs')->where('slug', $slug)->exists();
    }

    /**
     * Aposenta o slug para sempre (exclusao). Idempotente.
     */
    public function retire(?string $slug): void
    {
        if ($slug === null || $slug === '') {
            return;
        }

        if (! Schema::hasTable('layout_system_retired_slugs')) {
            return;
        }

        DB::table('layout_system_retired_slugs')->insertOrIgnore([
            'slug' => $slug,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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
