<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/**
 * Home publica: hero, grid de ferramentas e destaque do AI SQL Builder.
 *
 * O hero, os chips e o grid sao montados no cliente a partir do registro de
 * tools (`resources/js/tools`), que tambem alimenta a busca e o sitemap. Os
 * planos ficam em pagina propria (`PricingController`, `/{locale}/pricing`).
 */
class HomeController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Home');
    }
}
