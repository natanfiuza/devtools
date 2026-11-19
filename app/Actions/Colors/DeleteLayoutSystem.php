<?php

namespace App\Actions\Colors;

use App\Models\LayoutSystem;
use App\Support\Colors\LayoutSystemSlug;
use Illuminate\Support\Facades\DB;

/**
 * Exclui o sistema de layout definitivamente (F4, ADR-164/ADR-171).
 *
 * A confirmacao pelo nome fica na request. Os slugs ativo e arquivado vao para
 * a tabela de aposentados antes da exclusao: a URL antiga responde 404 para
 * sempre e um novo sistema com o mesmo nome nasce com slug diferente.
 */
final class DeleteLayoutSystem
{
    public function __construct(private readonly LayoutSystemSlug $slug) {}

    public function __invoke(LayoutSystem $system): void
    {
        DB::transaction(function () use ($system): void {
            $this->slug->retire($system->slug);
            $this->slug->retire($system->archived_slug);

            $system->delete();
        });
    }
}
