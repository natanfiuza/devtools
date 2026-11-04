<?php

use App\Models\BadgeProviderDefinition;
use App\Support\BadgeForge\Providers\ProviderPathParser;
use Tests\TestCase;

uses(TestCase::class);

function providerDefinition(array $overrides = []): BadgeProviderDefinition
{
    return new BadgeProviderDefinition(array_merge([
        'provider_id' => 1,
        'slug' => 'stars',
        'name' => 'GitHub stars',
        'label' => 'stars',
        'client_key' => 'github',
        'extractor_key' => 'stars',
        'path_pattern' => '{owner}/{repo}',
        'required_params' => [
            ['name' => 'owner', 'pattern' => '[A-Za-z0-9](?:[A-Za-z0-9-]{0,37}[A-Za-z0-9])?', 'allowSlash' => false, 'placeholderKey' => 'owner'],
            ['name' => 'repo', 'pattern' => '[A-Za-z0-9._-]{1,100}', 'allowSlash' => false, 'placeholderKey' => 'repo'],
        ],
        'optional_params' => null,
        'url_template' => 'https://api.github.com/repos/{owner}/{repo}',
        'response_type' => 'json',
        'parser_class' => null,
        'supports_private' => true,
        'example_path' => 'natanfiuza/devtools',
        'sort' => 0,
        'enabled' => true,
    ], $overrides));
}

function pathParser(): ProviderPathParser
{
    return new ProviderPathParser;
}

it('devolve os params quando o path casa com a definicao', function () {
    expect(pathParser()->parse(providerDefinition(), 'natanfiuza/devtools'))
        ->toBe(['owner' => 'natanfiuza', 'repo' => 'devtools']);
});

it('recusa contagem de segmentos diferente', function () {
    $definition = providerDefinition();

    expect(pathParser()->parse($definition, ''))->toBeNull()
        ->and(pathParser()->parse($definition, 'natanfiuza'))->toBeNull()
        ->and(pathParser()->parse($definition, 'natanfiuza/devtools/extra'))->toBeNull()
        ->and(pathParser()->parse($definition, '/natanfiuza/devtools'))->toBeNull()
        ->and(pathParser()->parse($definition, 'natanfiuza/devtools/'))->toBeNull();
});

it('recusa segmentos de traversal e caracteres de controle', function () {
    $definition = providerDefinition();

    expect(pathParser()->parse($definition, '../devtools'))->toBeNull()
        ->and(pathParser()->parse($definition, 'natanfiuza/..'))->toBeNull()
        ->and(pathParser()->parse($definition, 'natanfiuza/.'))->toBeNull()
        ->and(pathParser()->parse($definition, "natanfiuza/dev\x01tools"))->toBeNull()
        ->and(pathParser()->parse($definition, 'natanfiuza/dev\\tools'))->toBeNull();
});

it('recusa param fora do pattern ou acima do limite', function () {
    $definition = providerDefinition();
    $longOwner = str_repeat('a', 201);

    expect(pathParser()->parse($definition, 'natan fiuza/devtools'))->toBeNull()
        ->and(pathParser()->parse($definition, '-owner/repo'))->toBeNull()
        ->and(pathParser()->parse($definition, $longOwner.'/repo'))->toBeNull();
});

it('aceita um unico param com allowSlash, como pacote npm com escopo', function () {
    $definition = providerDefinition([
        'slug' => 'v',
        'label' => 'npm',
        'client_key' => 'npm',
        'extractor_key' => 'version',
        'path_pattern' => '{package}',
        'required_params' => [
            ['name' => 'package', 'pattern' => '[A-Za-z0-9@][A-Za-z0-9@._/-]{0,213}', 'allowSlash' => true, 'placeholderKey' => 'package'],
        ],
        'url_template' => 'https://registry.npmjs.org/{package}/latest',
        'example_path' => 'vue',
    ]);

    expect(pathParser()->parse($definition, 'vue'))->toBe(['package' => 'vue'])
        ->and(pathParser()->parse($definition, '@scope/pkg'))->toBe(['package' => '@scope/pkg'])
        ->and(pathParser()->parse($definition, '@scope/..'))->toBeNull()
        ->and(pathParser()->parse($definition, '@scope/'))->toBeNull();
});

it('trata JSON de params invalido como definicao indisponivel', function () {
    $broken = providerDefinition(['required_params' => 'nope']);
    $twoSlashes = providerDefinition([
        'required_params' => [
            ['name' => 'a', 'pattern' => '.+', 'allowSlash' => true, 'placeholderKey' => 'a'],
            ['name' => 'b', 'pattern' => '.+', 'allowSlash' => true, 'placeholderKey' => 'b'],
        ],
    ]);

    expect(pathParser()->parse($broken, 'owner/repo'))->toBeNull()
        ->and(pathParser()->parse($twoSlashes, 'a/b'))->toBeNull();
});
