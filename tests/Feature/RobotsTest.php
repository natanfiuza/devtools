<?php

/*
| robots.txt (docs/SPEC.md secao 7).
|
| O arquivo e servido pela aplicacao porque a linha do Sitemap muda de ambiente
| para ambiente - e porque o `robots.txt` estatico de public/ vencia a rota,
| tanto no nginx quanto no `artisan serve` (o teste abaixo guarda isso).
*/

it('serve o robots.txt pela aplicacao', function () {
    $response = $this->get('/robots.txt')->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/plain');

    expect($response->getContent())
        ->toStartWith("User-agent: *\n")
        ->toContain("Disallow: /admin\n")
        ->toContain("Disallow: /dashboard\n")
        ->toContain("Disallow: /settings\n")
        ->toContain("Disallow: /api\n")
        ->toContain('Sitemap: '.url('/sitemap.xml'))
        ->toEndWith("\n");
});

it('nao tem o robots.txt estatico em public/', function () {
    // Se ele voltar, a aplicacao perde o controle do arquivo e o teste acima
    // passa a testar o que ninguem ve.
    expect(is_file(public_path('robots.txt')))->toBeFalse();
});
