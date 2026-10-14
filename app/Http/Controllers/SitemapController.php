<?php

namespace App\Http\Controllers;

use App\Support\Sitemap;
use Illuminate\Http\Response;

/**
 * Sitemap do site (`/sitemap.xml`).
 *
 * Sem prefixo de idioma: o arquivo e de todo o site, e o idioma de cada URL
 * esta dentro dela. Quem monta a lista e App\Support\Sitemap; aqui so o
 * formato.
 */
class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        return response()
            ->view('sitemap', ['urls' => Sitemap::urls()])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
