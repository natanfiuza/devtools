<?php

use App\Support\Locales;

/*
| Os 5 idiomas precisam ter exatamente as mesmas chaves em cada namespace.
| Uma chave faltando nao quebra a tela (o vue-i18n cai no fallback), mas
| quebra a promessa de traducao completa - entao falha aqui, na suite.
*/

/** Chaves folha (notacao de ponto) de um array aninhado, ordenadas. */
function leafKeys(array $values, string $prefix = ''): array
{
    $keys = [];

    foreach ($values as $key => $value) {
        $full = $prefix === '' ? (string) $key : $prefix.'.'.$key;

        if (is_array($value)) {
            $keys = array_merge($keys, leafKeys($value, $full));

            continue;
        }

        $keys[] = $full;
    }

    sort($keys);

    return $keys;
}

it('carrega todos os namespaces em todos os idiomas', function () {
    $namespaces = config('locales.namespaces');

    expect($namespaces)->toBeArray()->not->toBeEmpty();

    foreach (Locales::codes() as $code) {
        foreach ($namespaces as $namespace) {
            $path = lang_path($code.'/'.$namespace.'.php');

            expect(file_exists($path))->toBeTrue("Falta lang/{$code}/{$namespace}.php");

            $messages = require $path;

            expect($messages)->toBeArray()->not->toBeEmpty("lang/{$code}/{$namespace}.php esta vazio");
        }
    }
});

it('tem as mesmas chaves nos 5 idiomas', function () {
    $fallback = Locales::fallback();

    foreach (config('locales.namespaces') as $namespace) {
        $reference = leafKeys(require lang_path($fallback.'/'.$namespace.'.php'));

        foreach (Locales::codes() as $code) {
            if ($code === $fallback) {
                continue;
            }

            $keys = leafKeys(require lang_path($code.'/'.$namespace.'.php'));

            expect(array_values(array_diff($reference, $keys)))
                ->toBe([], "Chaves faltando em lang/{$code}/{$namespace}.php");

            expect(array_values(array_diff($keys, $reference)))
                ->toBe([], "Chaves sobrando em lang/{$code}/{$namespace}.php");
        }
    }
});

it('nao tem traducao vazia', function () {
    foreach (Locales::codes() as $code) {
        foreach (config('locales.namespaces') as $namespace) {
            foreach (leafKeys(require lang_path($code.'/'.$namespace.'.php')) as $key) {
                $value = trans($namespace.'.'.$key, [], $code);

                expect(is_string($value) && trim($value) !== '')
                    ->toBeTrue("Traducao vazia: {$code}/{$namespace}.{$key}");
            }
        }
    }
});

it('nomeia todos os idiomas no proprio idioma', function () {
    foreach (Locales::codes() as $code) {
        $names = require lang_path($code.'/locales.php');

        foreach (Locales::codes() as $other) {
            expect($names[$other] ?? null)->toBeString()->not->toBeEmpty();
        }
    }
});
