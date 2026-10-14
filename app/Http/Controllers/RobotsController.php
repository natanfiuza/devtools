<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * robots.txt servido pela aplicacao (`/robots.txt`).
 *
 * O arquivo estatico saiu de `public/`: arquivo em `public/` vence a rota, no
 * nginx e no `artisan serve`, e o conteudo precisa da URL do sitemap - que
 * muda de ambiente para ambiente.
 *
 * `Disallow` so pede aos robos que nao rastreiem; quem tira as telas de acesso
 * do indice e o `noindex` delas (App\Support\Seo).
 */
class RobotsController extends Controller
{
    /**
     * Prefixos que nao devem ser rastreados: a area autenticada e o painel nao
     * tem conteudo publico, e `/api` esta reservado para as rotas de maquina
     * (o bootstrap ja trata `api/*` como JSON).
     *
     * @var list<string>
     */
    private const DISALLOW = [
        '/admin',
        '/dashboard',
        '/settings',
        '/api',
    ];

    public function __invoke(): Response
    {
        $lines = ['User-agent: *'];

        foreach (self::DISALLOW as $prefix) {
            $lines[] = "Disallow: {$prefix}";
        }

        // Linha em branco antes do Sitemap: e o que separa um grupo de regras
        // do outro no formato.
        $lines[] = '';
        $lines[] = 'Sitemap: '.url('/sitemap.xml');

        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
