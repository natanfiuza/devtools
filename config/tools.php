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

        /*
        | Acima disto as listas de uma tool passam a renderizar so a janela
        | visivel (a virtualizacao da arvore do JSON Viewer). E um limiar de
        | desempenho, e nao de negocio - mas mora aqui pelo mesmo motivo dos
        | outros: limite nao mora no codigo (CLAUDE.md). A pagina da tool o
        | entrega ao componente como o teto de entrada.
        */
        'virtualize_from' => (int) env('TOOLS_VIRTUALIZE_FROM', 2000),

        /*
        | O teto de tempo de uma expressao regular, em milissegundos.
        |
        | Uma regex pode ser escrita para nao terminar (o "catastrofico"
        | `(a+)+$`): quem rodasse o texto inteiro com ela travaria a aba. O
        | Regex Tester roda a busca num worker e o encerra ao estourar este
        | tempo - o navegador de quem usa segue inteiro (ADR-031).
        */
        'regex_timeout_ms' => (int) env('TOOLS_REGEX_TIMEOUT_MS', 2000),

        /*
        | Quantos resultados o Regex Tester devolve antes de cortar.
        |
        | O numero e a lista na tela, e nao a busca: um padrao de um caractere
        | num texto longo casa milhares de vezes, e a tela nao fica melhor com
        | todas elas. Ao cortar, a tool avisa.
        */
        'regex_max_matches' => (int) env('TOOLS_REGEX_MAX_MATCHES', 1000),

        /*
        | Limites e politicas da ferramenta Base64 Image (ADR-055).
        */
        'base64_image' => [
            'free_max_bytes' => (int) env('TOOLS_BASE64_IMAGE_FREE_MAX_BYTES', 2 * 1024 * 1024),
            'paid_max_bytes' => (int) env('TOOLS_BASE64_IMAGE_PAID_MAX_BYTES', 25 * 1024 * 1024),
            'download_threshold_bytes' => (int) env('TOOLS_BASE64_IMAGE_DOWNLOAD_THRESHOLD_BYTES', 2 * 1024 * 1024),
            'free_retention_hours' => (int) env('TOOLS_BASE64_IMAGE_FREE_RETENTION_HOURS', 24),
            'paid_retention_days' => (int) env('TOOLS_BASE64_IMAGE_PAID_RETENTION_DAYS', 30),
        ],
    ],

];
