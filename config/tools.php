<?php

/*
|--------------------------------------------------------------------------
| Registro de tools
|--------------------------------------------------------------------------
|
| O registro e o proprio diretorio de modulos: cada tool tem um
| `manifest.json` em resources/js/tools/<slug>/. O TypeScript le os mesmos
| arquivos pelo `import.meta.glob` do Vite (home e busca) e o PHP le por
| aqui (sitemap) - uma fonte, dois leitores (docs/SPEC.md secao 4).
|
| O caminho e configuravel para os testes apontarem para uma fixture.
|
*/

return [

    'path' => resource_path('js/tools'),

    /*
    | Limites das tools do cliente.
    |
    | O limite de entrada existe para proteger o **navegador de quem usa**, nao
    | o servidor: o texto nunca sai da maquina (CLAUDE.md). Passar dele nao
    | bloqueia a digitacao - bloqueia a analise, com o tamanho na tela.
    */
    'limits' => [
        'input_bytes' => (int) env('TOOLS_MAX_INPUT_BYTES', 5 * 1024 * 1024),
    ],

];
