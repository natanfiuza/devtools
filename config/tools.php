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

];
