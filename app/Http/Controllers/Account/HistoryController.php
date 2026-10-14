<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Historico de uso da conta.
 *
 * A tela existe desde agora para a navegacao da area da conta ficar completa,
 * mas o que ela vai listar - uma linha por uso de ferramenta - so passa a
 * existir quando as ferramentas existirem (F5). Ate la ela e o estado vazio,
 * dito no idioma de quem esta lendo: nenhuma lista vazia sem explicacao.
 */
class HistoryController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('dashboard/History');
    }
}
