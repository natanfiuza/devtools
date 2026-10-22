<?php

/*
| Cookie de sessao (ADR-052).
|
| Em producao o cookie so pode viajar por HTTPS. O default vem de APP_ENV no
| proprio config/session.php; a variavel SESSION_SECURE_COOKIE existe para o
| caso de um ambiente que ainda nao tem TLS (ou de um teste local), e o valor
| explicito sempre vence. O teste recarrega o arquivo de config com o ambiente
| ajustado, porque o config ja carregado no boot nao muda.
*/

/**
 * Recarrega config/session.php com variaveis de ambiente temporarias.
 *
 * @param  array<string, string|null>  $environment
 * @return array<string, mixed>
 */
function sessionConfigUnder(array $environment): array
{
    $savedServer = [];
    $savedEnv = [];

    foreach ($environment as $key => $value) {
        $savedServer[$key] = $_SERVER[$key] ?? null;
        $savedEnv[$key] = $_ENV[$key] ?? null;

        if ($value === null) {
            unset($_SERVER[$key], $_ENV[$key]);
        } else {
            $_SERVER[$key] = $value;
            $_ENV[$key] = $value;
        }
    }

    try {
        /** @var array<string, mixed> $config */
        $config = require config_path('session.php');

        return $config;
    } finally {
        foreach ($savedServer as $key => $value) {
            if ($value === null) {
                unset($_SERVER[$key]);
            } else {
                $_SERVER[$key] = $value;
            }
        }

        foreach ($savedEnv as $key => $value) {
            if ($value === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $value;
            }
        }
    }
}

it('marca o cookie como seguro por padrao em producao', function () {
    expect(sessionConfigUnder(['APP_ENV' => 'production', 'SESSION_SECURE_COOKIE' => null])['secure'])
        ->toBeTrue();
});

it('nao marca o cookie como seguro fora de producao', function () {
    expect(sessionConfigUnder(['APP_ENV' => 'local', 'SESSION_SECURE_COOKIE' => null])['secure'])
        ->toBeFalse();
});

it('deixa o valor explicito vencer o default do ambiente', function () {
    expect(sessionConfigUnder(['APP_ENV' => 'production', 'SESSION_SECURE_COOKIE' => 'false'])['secure'])
        ->toBeFalse()
        ->and(sessionConfigUnder(['APP_ENV' => 'local', 'SESSION_SECURE_COOKIE' => 'true'])['secure'])
        ->toBeTrue();
});

it('mantem http_only e same_site lax', function () {
    // Strict quebraria o retorno do Mercado Pago (redirect de topo vindo de
    // outro site); lax cobre o caso sem expor o cookie a subrequisicoes.
    $config = sessionConfigUnder(['APP_ENV' => 'production', 'SESSION_SECURE_COOKIE' => null]);

    expect($config['http_only'])->toBeTrue()
        ->and($config['same_site'])->toBe('lax');
});
