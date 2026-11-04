<?php

namespace App\Http\Controllers\BadgeForge;

use App\Http\Controllers\Controller;
use App\Support\BadgeForge\BadgeForgeToolProps;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pagina `/dashboard/badgeforge` (ADR-079).
 *
 * Reusa o mesmo contrato da tool para listar logos e presets, de modo que a
 * pagina de conta e o builder nunca divirjam sobre limites ou metadados.
 */
class BadgeForgeAccountController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('dashboard/BadgeForge', [
            'toolProps' => BadgeForgeToolProps::for($user),
            'toolUrl' => route('tools.show', ['slug' => 'badgeforge']),
            'upgradeUrl' => route('pricing'),
        ]);
    }
}
