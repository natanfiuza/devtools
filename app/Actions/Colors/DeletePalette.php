<?php

namespace App\Actions\Colors;

use App\Models\ColorPalette;
use App\Support\Colors\PaletteOgStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Exclui a paleta definitivamente (F4, ADR-119/ADR-123).
 *
 * A confirmacao pelo nome e feita na request. A linha e o arquivo OG saem;
 * falha ao apagar o arquivo nao desfaz a exclusao (a imagem orfa nao e
 * servida porque a paleta nao existe mais) e e registrada apenas pelo id.
 */
final class DeletePalette
{
    public function __construct(private readonly PaletteOgStorage $og) {}

    public function __invoke(ColorPalette $palette): void
    {
        $id = $palette->id;

        DB::transaction(static function () use ($palette): void {
            $palette->delete();
        });

        try {
            $this->og->delete($id);
        } catch (Throwable) {
            Log::warning('Falha ao excluir a imagem OG de uma paleta.', ['palette' => $id]);
        }
    }
}
