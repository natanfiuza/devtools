<?php

use App\Support\BadgeForge\Custom\CustomProviderUrlTemplate;
use Tests\TestCase;

uses(TestCase::class);

it('extrai placeholders na ordem e valida o conjunto', function () {
    $template = CustomProviderUrlTemplate::from('https://api.example.com/repos/{owner}/{repo}/stats.json');

    expect($template)->not->toBeNull()
        ->and($template?->placeholders)->toBe(['owner', 'repo'])
        ->and($template?->matchesPathParams(['owner', 'repo']))->toBeTrue()
        ->and($template?->matchesPathParams(['repo', 'owner']))->toBeFalse()
        ->and($template?->matchesPathParams(['owner']))->toBeFalse();
});

it('renderiza cada valor com urlencode sem tocar no resto do template', function () {
    $template = CustomProviderUrlTemplate::from('https://api.example.com/{owner}/{repo}?x=1');

    expect($template?->render(['owner' => 'a b', 'repo' => 'dev/tools']))
        ->toBe('https://api.example.com/a%20b/dev%2Ftools?x=1');

    expect($template?->render(['owner' => 'ok']))->toBeNull();
});

it('recusa template sem placeholder, com host variavel ou chave duplicada', function (string $raw) {
    expect(CustomProviderUrlTemplate::from($raw))->toBeNull();
})->with([
    'sem placeholder' => ['https://api.example.com/stats.json'],
    'host variavel' => ['https://{tenant}.example.com/stats.json'],
    'http' => ['http://api.example.com/{owner}'],
    'chave duplicada' => ['https://api.example.com/{owner}/{owner}'],
    'chave invalida' => ['https://api.example.com/{own-er}'],
    'chave vazia' => ['https://api.example.com/{}'],
    'chave nao fechada' => ['https://api.example.com/{owner'],
    'controle' => ["https://api.example.com/{owner}\x01"],
]);

it('recusa mais placeholders que o limite configurado', function () {
    config()->set('badgeforge.custom_providers.max_path_params', 2);

    expect(CustomProviderUrlTemplate::from('https://api.example.com/{a}/{b}'))->not->toBeNull()
        ->and(CustomProviderUrlTemplate::from('https://api.example.com/{a}/{b}/{c}'))->toBeNull();
});
