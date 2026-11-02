<?php

use App\Support\BadgeForge\BadgeRequest;
use Tests\TestCase;

uses(TestCase::class);

/*
| Normalizacao do pedido: texto, estilo, cores, logo, limites e link.
| Nenhuma entrada do usuario pode lancar excecao.
*/

function badgeRequest(): BadgeRequest
{
    return app(BadgeRequest::class);
}

it('decodifica underscores e mantem hifen literal', function () {
    $spec = badgeRequest()->spec('hello__world', 'hello_world', []);

    expect($spec?->label)->toBe('hello_world')
        ->and($spec?->message)->toBe('hello world')
        ->and(badgeRequest()->spec('ci-cd', 'infra', [])?->label)->toBe('ci-cd');
});

it('decodifica a barra que chega dupla-codificada', function () {
    // O router ja decodificou o path; aqui chega `%2F` (de `%252F` na URL).
    expect(badgeRequest()->spec('ci%2Fcd', 'deploy', [])?->label)->toBe('ci/cd')
        ->and(badgeRequest()->spec('a%2fb', 'ok', [])?->label)->toBe('a/b')
        ->and(badgeRequest()->spec('100%', 'ok', [])?->label)->toBe('100%');
});

it('remove caracteres de controle e recusa segmento vazio', function () {
    expect(badgeRequest()->spec("a\x01b", 'ok', [])?->label)->toBe('a b')
        ->and(badgeRequest()->spec('_', 'ok', []))->toBeNull()
        ->and(badgeRequest()->spec('   ', 'ok', []))->toBeNull();
});

it('trunca em 100 caracteres com reticencias', function () {
    $spec = badgeRequest()->spec(str_repeat('x', 101), str_repeat('y', 100), []);

    expect(mb_strlen($spec->label))->toBe(100)
        ->and($spec->label)->toEndWith('…')
        ->and(mb_strlen($spec->message))->toBe(100);
});

it('aplica fallback de estilo e cores', function () {
    $spec = badgeRequest()->spec('a', 'b', ['style' => 'nope', 'color' => 'nope', 'labelColor' => 'nope']);

    expect($spec?->style->id)->toBe('flat')
        ->and($spec?->messageColor->hex)->toBe('#9f9f9f')
        ->and($spec?->labelColor->hex)->toBe('#555555')
        ->and($spec?->logoColor->hex)->toBe('#ffffff');
});

it('resolve cores nomeadas e funcionais', function () {
    $spec = badgeRequest()->spec('a', 'b', [
        'color' => 'rgba(0,126,198,0.6)',
        'labelColor' => 'brightgreen',
    ]);

    expect($spec?->messageColor->hex)->toBe('#007ec6')
        ->and($spec?->messageColor->alpha)->toBe(0.6)
        ->and($spec?->labelColor->hex)->toBe('#44cc11');
});

it('forca as cores do estilo social', function () {
    $spec = badgeRequest()->spec('a', 'b', ['style' => 'social', 'color' => '#ff0000', 'labelColor' => '#00ff00']);

    expect($spec?->messageColor->hex)->toBe('#007ec6')
        ->and($spec?->labelColor->hex)->toBe('#555555');
});

it('aplica caixa alta no for-the-badge', function () {
    $spec = badgeRequest()->spec('build', 'passing', ['style' => 'for-the-badge']);

    expect($spec?->label)->toBe('BUILD')
        ->and($spec?->message)->toBe('PASSING');
});

it('resolve logo conhecido e ignora desconhecido', function () {
    expect(badgeRequest()->spec('a', 'b', ['logo' => 'github'])?->logo?->slug)->toBe('github')
        ->and(badgeRequest()->spec('a', 'b', ['logo' => '../github'])?->logo)->toBeNull()
        ->and(badgeRequest()->spec('a', 'b', ['logo' => 'nope'])?->logo)->toBeNull();
});

it('resolve a fonte empacotada e ignora fonte desconhecida', function () {
    $spec = badgeRequest()->spec('a', 'b', ['font' => 'space-grotesk']);

    expect($spec?->font?->slug)->toBe('space-grotesk')
        ->and($spec?->font?->name)->toBe('Space Grotesk')
        ->and($spec?->font?->stack)->toBe('Space Grotesk,DejaVu Sans,sans-serif')
        ->and(badgeRequest()->spec('a', 'b', ['font' => 'SPACE-GROTESK'])?->font?->slug)->toBe('space-grotesk')
        ->and(badgeRequest()->spec('a', 'b', ['font' => 'nope'])?->font)->toBeNull()
        ->and(badgeRequest()->spec('a', 'b', [])?->font)->toBeNull()
        ->and(badgeRequest()->spec('a', 'b', ['font' => ['space-grotesk']])?->font)->toBeNull();
});

it('faz clamp do logoSize e usa o default do estilo', function () {
    expect(badgeRequest()->spec('a', 'b', ['logoSize' => '4'])?->logoSize)->toBe(8)
        ->and(badgeRequest()->spec('a', 'b', ['logoSize' => '100'])?->logoSize)->toBe(20)
        ->and(badgeRequest()->spec('a', 'b', ['logoSize' => 'abc'])?->logoSize)->toBe(10)
        ->and(badgeRequest()->spec('a', 'b', ['style' => 'for-the-badge'])?->logoSize)->toBe(14);
});

it('normaliza logoPosition e link', function () {
    $valid = badgeRequest()->spec('a', 'b', ['logoPosition' => 'right', 'link' => 'https://example.com/x?a=1&b=2']);

    expect($valid?->logoPosition)->toBe('right')
        ->and($valid?->link)->toBe('https://example.com/x?a=1&b=2')
        ->and(badgeRequest()->spec('a', 'b', ['logoPosition' => 'top'])?->logoPosition)->toBe('left')
        ->and(badgeRequest()->spec('a', 'b', ['link' => 'javascript:alert(1)'])?->link)->toBeNull()
        ->and(badgeRequest()->spec('a', 'b', ['link' => 'ftp://example.com'])?->link)->toBeNull();
});

it('faz clamp do cacheSeconds', function () {
    expect(badgeRequest()->spec('a', 'b', [])?->cacheSeconds)->toBe(86400)
        ->and(badgeRequest()->spec('a', 'b', ['cacheSeconds' => '60'])?->cacheSeconds)->toBe(300)
        ->and(badgeRequest()->spec('a', 'b', ['cacheSeconds' => '999999'])?->cacheSeconds)->toBe(86400)
        ->and(badgeRequest()->spec('a', 'b', ['cacheSeconds' => 'abc'])?->cacheSeconds)->toBe(86400);
});

it('ignora valores de query que nao sejam string', function () {
    $spec = badgeRequest()->spec('a', 'b', ['style' => ['flat'], 'logo' => ['github'], 'color' => ['#fff']]);

    expect($spec?->style->id)->toBe('flat')
        ->and($spec?->logo)->toBeNull();
});
