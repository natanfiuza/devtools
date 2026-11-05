<?php

namespace App\Http\Controllers\BadgeForge;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\BadgeForge\Analytics\OwnerAnalytics;
use App\Support\BadgeForge\BadgeForgeToolProps;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pagina `/dashboard/badgeforge` (ADR-079, F7/ADR-086).
 *
 * Reusa o mesmo contrato da tool para listar logos e presets, de modo que a
 * pagina de conta e o builder nunca divirjam sobre limites ou metadados.
 * A analytics le o bruto do dono; janela invalida cai no padrao e uma
 * migracao ainda nao aplicada devolve a secao indisponivel, nunca 500.
 */
class BadgeForgeAccountController extends Controller
{
    public function __invoke(Request $request, OwnerAnalytics $analytics): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        try {
            $analyticsProps = $analytics->for($user, $request->query('window'));
        } catch (QueryException) {
            $analyticsProps = null;
        }

        return Inertia::render('dashboard/BadgeForge', [
            'toolProps' => BadgeForgeToolProps::for($user),
            'analytics' => $analyticsProps,
            'toolUrl' => route('tools.show', ['slug' => 'badgeforge']),
            'upgradeUrl' => route('pricing'),
        ]);
    }
}
