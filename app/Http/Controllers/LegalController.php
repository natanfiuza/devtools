<?php

namespace App\Http\Controllers;

use App\Support\Legal;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Paginas legais: Politica de Privacidade e Termos de Uso.
 *
 * Sao conteudo proprio, entao ficam indexaveis e com hreflang normal (ao
 * contrario das telas de acesso, ver App\Support\Seo). O texto vem de
 * lang/{locale}/legal.php; contato e data de revisao vem de config/legal.php.
 *
 * Um controller (e nao Route::inertia) porque as props dependem do idioma da
 * requisicao: dentro de `defaults` da rota elas seriam fixadas no boot, e uma
 * closure ali quebraria o `route:cache` do deploy.
 */
class LegalController extends Controller
{
    public function privacy(): Response
    {
        return Inertia::render('Privacy', $this->props());
    }

    public function terms(): Response
    {
        return Inertia::render('Terms', $this->props());
    }

    /**
     * @return array{contactEmail: string|null, updatedLabel: string}
     */
    private function props(): array
    {
        return [
            'contactEmail' => Legal::contactEmail(),
            'updatedLabel' => Legal::updatedLabel(),
        ];
    }
}
