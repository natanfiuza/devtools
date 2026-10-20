<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\ToolProps;
use App\Support\Tools;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pagina de uma ferramenta do site: `/{locale}/tools/{slug}` (docs/SPEC.md
 * secao 4).
 *
 * A pagina e **generica** - um shell com dois paineis onde o componente da
 * propria tool se encaixa (a F3 acrescenta tools sem tocar aqui). O servidor
 * so responde o que ele sabe: qual tool e os limites configurados em
 * `config/tools.php`.
 *
 * Os limites vao **todos**, e nao so os da tool da vez: a pagina nao sabe qual
 * ferramenta esta montando (ela resolve o componente pelo slug), e cada tool
 * usa os que lhe dizem respeito. Sao quatro numeros, e o contrato continua
 * crescendo com a configuracao - e nao com o numero de tools (ADR-029).
 *
 * Slug desconhecido, tool `soon` e tool `hidden` dao o **mesmo 404**: so
 * `available` tem pagina (ADR-007). Um 200 de "em breve" criaria uma segunda
 * representacao do status e um link morto no indice.
 */
class ToolController extends Controller
{
    public function __invoke(Request $request): Response
    {
        // O slug e lido pelo nome, e nao por um parametro do metodo: o Laravel
        // preenche parametro escalar **por posicao**, e a rota e
        // `{locale}/tools/{slug}` - um `string $slug` na assinatura receberia o
        // idioma. Pelo nome, a assinatura nao depende da ordem da URL.
        $slug = $request->route('slug');

        $tool = is_string($slug) ? Tools::find($slug) : null;

        abort_if($tool === null || $tool['status'] !== 'available', 404);

        $user = $request->user();

        return Inertia::render('tools/Show', [
            'slug' => $tool['slug'],
            'maxInputBytes' => (int) config('tools.limits.input_bytes'),
            'virtualizeFrom' => (int) config('tools.limits.virtualize_from'),
            'regexTimeoutMs' => (int) config('tools.limits.regex_timeout_ms'),
            'regexMaxMatches' => (int) config('tools.limits.regex_max_matches'),
            // O pacote da tool da vez (ADR-043). Tools de navegador recebem um
            // objeto vazio; o AI SQL Builder recebe estado, catalogo e limites.
            'toolProps' => ToolProps::for(
                $tool['slug'],
                $user instanceof User ? $user : null,
            ),
        ]);
    }
}
