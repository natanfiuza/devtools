<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Account\ExportUserData;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exportacao dos dados da conta (ADR-028).
 *
 * Download direto, sem tela intermediaria: o arquivo e gerado na hora e vai
 * embora na mesma resposta. Nada fica guardado no servidor - uma copia parada
 * aqui seria mais um lugar com dado pessoal para proteger.
 */
class ExportController extends Controller
{
    public function __invoke(Request $request, ExportUserData $export): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $data = $export($user);

        $json = json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );

        $filename = 'devtools-dados-'.$user->getKey().'-'.now()->format('Y-m-d').'.json';

        return response($json === false ? '{}' : $json, 200, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
