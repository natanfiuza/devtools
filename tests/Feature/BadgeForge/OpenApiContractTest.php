<?php

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route as Router;
use Symfony\Component\Yaml\Yaml;

/*
| Contrato da spec OpenAPI do BadgeForge (F8, ADR-071).
|
| A spec e a fonte da verdade da documentacao de maquina; estes testes
| garantem que ela (a) parseia, (b) nao tem `$ref` pendurado e (c) casa nos
| dois sentidos com as rotas registradas - inclusive nos nomes reais dos
| parametros de path.
*/

/**
 * @return array<mixed>
 */
function badgeforgespec(): array
{
    $path = base_path('docs/badgeforge/openapi.yaml');

    expect(File::exists($path))->toBeTrue();

    $document = Yaml::parse(File::get($path));

    if (! is_array($document)) {
        throw new RuntimeException('A spec do BadgeForge nao parseia como objeto YAML.');
    }

    return $document;
}

/**
 * @return list<string>
 */
function badgeforgerefs(mixed $node): array
{
    if (! is_array($node)) {
        return [];
    }

    $refs = [];

    foreach ($node as $key => $value) {
        if ($key === '$ref' && is_string($value)) {
            $refs[] = $value;

            continue;
        }

        $refs = [...$refs, ...badgeforgerefs($value)];
    }

    return $refs;
}

/**
 * @return list<string>
 */
function badgeforgeplaceholders(string $path): array
{
    preg_match_all('/\{([^}]+)\}/', $path, $matches);

    $names = $matches[1];
    sort($names);

    return $names;
}

/**
 * Superficie documentada e registrada que precisa de paridade. Fases futuras
 * acrescentam rotas sob os mesmos prefixos e esta lista cresce junto.
 *
 * @return list<string>
 */
function badgeforgeprefixes(): array
{
    return ['api/badges/', 'api/v1/badgeforge/', 'b/'];
}

/**
 * @param  list<string>  $prefixes
 */
function badgeforgehasprefix(string $value, array $prefixes, string $leading = ''): bool
{
    foreach ($prefixes as $prefix) {
        if (str_starts_with($value, $leading.$prefix)) {
            return true;
        }
    }

    return false;
}

/**
 * Metodos HTTP que a spec documenta num path (chaves de operacao).
 *
 * @return list<string>
 */
function badgeforgedocumentedmethods(mixed $operations): array
{
    if (! is_array($operations)) {
        return [];
    }

    return array_values(array_filter(
        ['get', 'put', 'post', 'delete', 'options', 'head', 'patch', 'trace'],
        static fn (string $method): bool => array_key_exists($method, $operations),
    ));
}

/**
 * @param  array<mixed>  $document
 * @return array<string, mixed>
 */
function badgeforgedocumentedpaths(array $document): array
{
    $paths = $document['paths'] ?? null;

    if (! is_array($paths)) {
        throw new RuntimeException('A spec do BadgeForge nao declara paths.');
    }

    $prefixes = badgeforgeprefixes();

    return array_filter(
        $paths,
        static fn (mixed $operations, mixed $path): bool => is_string($path)
            && badgeforgehasprefix($path, $prefixes, '/'),
        ARRAY_FILTER_USE_BOTH,
    );
}

it('serve a especificação do BadgeForge com cache público e APP_URL resolvido', function () {
    config(['app.url' => 'https://badge.example.test']);

    $response = $this->get(route('openapi.badges'))->assertOk();

    expect($response->headers->get('Content-Type'))
        ->toContain('application/yaml')
        ->and($response->headers->get('Cache-Control'))
        ->toContain('max-age=86400')
        ->and($response->getContent())
        ->toContain('openapi: 3.1.0')
        ->toContain('/api/badges/static/')
        ->not->toContain('%APP_URL%');

    $document = Yaml::parse((string) $response->getContent());

    expect($document['servers'][0]['url'])->toBe('https://badge.example.test');
});

it('documenta versão 3.1.0 e 1.9.0 com paths absolutos', function () {
    $document = badgeforgespec();

    expect($document['openapi'])->toBe('3.1.0')
        ->and($document['info']['version'])->toBe('1.9.0');

    foreach (array_keys(badgeforgedocumentedpaths($document)) as $path) {
        expect($path)->toStartWith('/');
    }
});

it('resolve todos os $ref dentro do documento', function () {
    $document = badgeforgespec();
    $refs = badgeforgerefs($document);

    expect($refs)->not->toBeEmpty();

    foreach ($refs as $ref) {
        expect($ref)->toStartWith('#/');

        $node = $document;

        foreach (explode('/', substr($ref, 2)) as $segment) {
            $segment = str_replace(['~1', '~0'], ['/', '~'], $segment);

            expect($node)->toBeArray();
            expect(array_key_exists($segment, $node))->toBeTrue("$ref nao resolve em $segment");

            $node = $node[$segment];
        }
    }
});

it('mantém paridade bidirecional entre rotas de badge e a spec', function () {
    $document = badgeforgespec();

    $prefixes = badgeforgeprefixes();

    $routes = collect(Router::getRoutes()->getRoutes())
        ->filter(fn (Route $route): bool => badgeforgehasprefix($route->uri(), $prefixes))
        ->values();

    expect($routes)->not->toBeEmpty();

    // Spec -> rota: todo path+method documentado existe e os placeholders sao
    // os nomes reais dos parametros da rota.
    foreach (badgeforgedocumentedpaths($document) as $path => $operations) {
        $uri = ltrim((string) $path, '/');
        $documentedMethods = badgeforgedocumentedmethods($operations);

        expect($documentedMethods)->not->toBeEmpty("$path nao documenta operacao");

        foreach ($documentedMethods as $method) {
            $route = $routes->first(fn (Route $route): bool => $route->uri() === $uri
                && in_array(strtoupper($method), $route->methods(), true));

            expect($route)->not->toBeNull("$method $path nao resolve numa rota registrada");

            if (! $route instanceof Route) {
                continue;
            }

            $expected = $route->parameterNames();
            sort($expected);

            expect(badgeforgeplaceholders($uri))->toBe($expected);
        }
    }

    // Rota -> spec: toda rota da superficie (exceto HEAD/OPTIONS) esta
    // documentada com o mesmo metodo.
    $documented = badgeforgedocumentedpaths($document);

    foreach ($routes as $route) {
        $operations = $documented['/'.$route->uri()] ?? null;

        expect($operations)->toBeArray('/'.$route->uri().' nao esta documentada');

        $documentedMethods = badgeforgedocumentedmethods($operations);

        foreach (array_diff($route->methods(), ['HEAD', 'OPTIONS']) as $method) {
            expect(in_array(strtolower($method), $documentedMethods, true))->toBeTrue(
                $method.' /'.$route->uri().' nao esta documentado',
            );
        }
    }
});
