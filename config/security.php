<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Content Security Policy
    |--------------------------------------------------------------------------
    |
    | `enforce` bloqueia o que nao estiver na politica; `report-only` so
    | registra no console do navegador; `off` nao emite o header. O default de
    | producao e `enforce`; no local o Vite injeta scripts inline e a politica
    | atrapalharia o HMR, por isso o default fora de producao e `off`.
    |
    | Se um deploy quebrar por causa da CSP, a volta e `CSP_MODE=report-only`
    | no .env, sem deploy de codigo. Ver docs/RUNBOOK.md.
    |
    */

    'csp' => [
        'mode' => env('CSP_MODE', env('APP_ENV') === 'production' ? 'enforce' : 'off'),
    ],

    /*
    |--------------------------------------------------------------------------
    | HSTS
    |--------------------------------------------------------------------------
    |
    | So e emitido em requisicao HTTPS. `max_age` em segundos; zero desliga.
    | `include_subdomains` cobre os subdominios do dominio do site.
    |
    */

    'hsts' => [
        'max_age' => (int) env('HSTS_MAX_AGE', 31536000),
        'include_subdomains' => (bool) env('HSTS_INCLUDE_SUBDOMAINS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Proxies confiaveis
    |--------------------------------------------------------------------------
    |
    | Lista separada por virgula de IPs/CIDRs do proxy reverso (nginx no
    | proprio VPS: 127.0.0.1). Vazio significa que o Laravel ignora
    | X-Forwarded-*: IP, esquema e host vem da conexao direta.
    |
    */

    'trusted_proxies' => array_values(array_filter(
        array_map(trim(...), explode(',', (string) env('TRUSTED_PROXIES', ''))),
        fn (string $proxy): bool => $proxy !== '',
    )),

    /*
    |--------------------------------------------------------------------------
    | HTTPS obrigatorio
    |--------------------------------------------------------------------------
    |
    | Forca `https` em toda URL gerada (canonical, OG, sitemap, robots, links
    | de e-mail). Em producao o default e ligado; no local, desligado.
    |
    */

    'force_https' => (bool) env('FORCE_HTTPS', env('APP_ENV') === 'production'),

];
