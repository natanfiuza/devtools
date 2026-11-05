<?php

use App\Support\BadgeForge\CustomLogoSvgParser;
use App\Support\BadgeForge\InvalidCustomLogoException;
use App\Support\BadgeForge\ParsedCustomLogo;
use Tests\TestCase;

uses(TestCase::class);

/*
| Sanitizador de SVG dos logos personalizados (ADR-078).
|
| O contrato e fechado: so `<svg>`, `<g>` e `<path>` com viewBox valido e
| paths seguros. Tudo que poderia carregar codigo, referencia externa ou
| alterar a geometria fora do esperado vira um codigo estavel de erro.
*/

function parseCustomLogo(string $svg, ?int $size = null): ParsedCustomLogo
{
    return app(CustomLogoSvgParser::class)->parse($svg, $size ?? strlen($svg));
}

function parseCustomLogoError(string $svg, ?int $size = null): string
{
    try {
        parseCustomLogo($svg, $size);
    } catch (InvalidCustomLogoException $exception) {
        return $exception->reason;
    }

    throw new RuntimeException('Esperava InvalidCustomLogoException.');
}

function customSvg(string $inner, string $viewBox = '0 0 24 24'): string
{
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="'.$viewBox.'">'.$inner.'</svg>';
}

it('le um logo multi-path preservando a ordem e as regras', function () {
    $parsed = parseCustomLogo(<<<'SVG'
        <?xml version="1.0" encoding="UTF-8"?>
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0,0 24,24" width="48" height="48" fill="#fff">
            <!-- comentario ignorado -->
            <g fill-rule="evenodd">
                <path d="M0 0h24v24H0z"/>
                <path d="M4 4h8v8H4z" clip-rule="evenodd"/>
            </g>
            <path d=" M20 20l2 2 " fill-rule="nonzero"/>
        </svg>
        SVG);

    expect($parsed->viewBox)->toBe('0 0 24 24')
        ->and($parsed->paths)->toHaveCount(3)
        ->and($parsed->paths[0]->d)->toBe('M0 0h24v24H0z')
        ->and($parsed->paths[0]->fillRule)->toBe('evenodd')
        ->and($parsed->paths[0]->clipRule)->toBeNull()
        ->and($parsed->paths[1]->d)->toBe('M4 4h8v8H4z')
        ->and($parsed->paths[1]->fillRule)->toBe('evenodd')
        ->and($parsed->paths[1]->clipRule)->toBe('evenodd')
        ->and($parsed->paths[2]->d)->toBe('M20 20l2 2')
        ->and($parsed->paths[2]->fillRule)->toBe('nonzero')
        ->and($parsed->paths[2]->clipRule)->toBeNull()
        ->and($parsed->sizeBytes)->toBeGreaterThan(0);
});

it('aceita documento sem namespace explicito e sem declaracao XML', function () {
    $parsed = parseCustomLogo('<svg viewBox="0 0 10 10"><path d="M0 0h10v10H0z"/></svg>');

    expect($parsed->paths)->toHaveCount(1);
});

it('normaliza o viewBox', function (string $raw, ?string $expected) {
    expect(CustomLogoSvgParser::normalizeViewBox($raw))->toBe($expected);
})->with([
    'virgulas' => ['0,0,24,24', '0 0 24 24'],
    'espacos extras' => ['  0   0  24  24  ', '0 0 24 24'],
    'decimais' => ['0 0 24.5 10.25', '0 0 24.5 10.25'],
    'notacao cientifica' => ['0 0 1e2 1e2', '0 0 1e2 1e2'],
    'negativo' => ['-10 -10 20 20', '-10 -10 20 20'],
    'tres numeros' => ['0 0 24', null],
    'cinco numeros' => ['0 0 24 24 1', null],
    'largura zero' => ['0 0 0 24', null],
    'altura negativa' => ['0 0 24 -24', null],
    'texto' => ['0 0 a b', null],
    'vazio' => ['', null],
]);

it('limita a extensao do viewBox pela configuracao', function () {
    config(['badgeforge.custom_logos.max_viewbox_extent' => 100]);

    expect(CustomLogoSvgParser::normalizeViewBox('0 0 100 100'))->toBe('0 0 100 100')
        ->and(CustomLogoSvgParser::normalizeViewBox('0 0 101 100'))->toBeNull()
        ->and(parseCustomLogoError(customSvg('<path d="M0 0h1v1H0z"/>', '0 0 500 500')))
        ->toBe('invalid_viewbox');
});

it('recusa o arquivo acima do limite de bytes', function () {
    $max = (int) config('badgeforge.custom_logos.max_bytes');

    expect(parseCustomLogoError(customSvg('<path d="M0 0h1v1H0z"/>'), $max + 1))
        ->toBe('too_large');
});

it('recusa conteudo que nao seja UTF-8', function () {
    expect(parseCustomLogoError("<svg viewBox=\"0 0 1 1\"><path d=\"\xFF\"/></svg>"))
        ->toBe('invalid_encoding');
});

it('recusa DOCTYPE e entidades', function (string $svg) {
    expect(parseCustomLogoError($svg))->toBe('doctype_not_allowed');
})->with([
    'doctype' => ['<!DOCTYPE svg><svg viewBox="0 0 1 1"><path d="M0 0h1v1H0z"/></svg>'],
    'entidade interna' => ['<!ENTITY x "y"><svg viewBox="0 0 1 1"><path d="M0 0h1v1H0z"/></svg>'],
]);

it('recusa processing instructions que nao sejam a declaracao XML', function () {
    expect(parseCustomLogoError('<?php echo 1; ?><svg viewBox="0 0 1 1"><path d="M0 0h1v1H0z"/></svg>'))
        ->toBe('processing_instruction_not_allowed')
        ->and(parseCustomLogoError(customSvg('<path d="M0 0h1v1H0z"/>').'<?xml-stylesheet href="x.css"?>'))
        ->toBe('processing_instruction_not_allowed');
});

it('recusa XML malformado e raiz que nao seja svg', function (string $svg, string $code) {
    expect(parseCustomLogoError($svg))->toBe($code);
})->with([
    'xml quebrado' => ['<svg viewBox="0 0 1 1"><path d="M0 0h1v1H0z">', 'malformed'],
    'raiz html' => ['<html><body/></html>', 'invalid_root'],
    'raiz vazia' => ['<?xml version="1.0"?>', 'malformed'],
    'namespace errado' => ['<svg xmlns="http://example.com" viewBox="0 0 1 1"><path d="M0 0h1v1H0z"/></svg>', 'invalid_root'],
]);

it('exige viewBox valido', function () {
    expect(parseCustomLogoError('<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0h1v1H0z"/></svg>'))
        ->toBe('invalid_viewbox')
        ->and(parseCustomLogoError('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24"><path d="M0 0h1v1H0z"/></svg>'))
        ->toBe('invalid_viewbox');
});

it('recusa elementos fora da allowlist', function (string $element) {
    expect(parseCustomLogoError(customSvg($element)))->toBe('unsupported_element');
})->with([
    'script' => ['<script>alert(1)</script>'],
    'style' => ['<style>path{fill:red}</style>'],
    'image' => ['<image href="https://example.com/x.png"/>'],
    'use' => ['<use href="#x"/>'],
    'foreignObject' => ['<foreignObject><div/></foreignObject>'],
    'defs' => ['<defs><path d="M0 0h1v1H0z"/></defs>'],
    'text' => ['<text>oi</text>'],
    'animate' => ['<animate attributeName="x"/>'],
]);

it('recusa texto fora de espaco em branco', function () {
    expect(parseCustomLogoError(customSvg('texto solto <path d="M0 0h1v1H0z"/>')))
        ->toBe('text_not_allowed')
        ->and(parseCustomLogoError(customSvg('<path d="M0 0h1v1H0z">conteudo</path>')))
        ->toBe('text_not_allowed');
});

it('recusa atributos perigosos ou desconhecidos', function (string $path) {
    expect(parseCustomLogoError(customSvg($path)))->toBe('unsupported_attribute');
})->with([
    'onload' => ['<path d="M0 0h1v1H0z" onload="alert(1)"/>'],
    'onclick' => ['<path d="M0 0h1v1H0z" onclick="alert(1)"/>'],
    'style' => ['<path d="M0 0h1v1H0z" style="fill:red"/>'],
    'transform' => ['<path d="M0 0h1v1H0z" transform="scale(2)"/>'],
    'opacity' => ['<path d="M0 0h1v1H0z" opacity="0.5"/>'],
    'href na raiz do grupo' => ['<g href="#x"><path d="M0 0h1v1H0z"/></g>'],
    'atributo estranho' => ['<path d="M0 0h1v1H0z" data-x="1"/>'],
    'fill-rule invalido' => ['<path d="M0 0h1v1H0z" fill-rule="inherit"/>'],
]);

it('recusa url() em qualquer atributo', function () {
    expect(parseCustomLogoError('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="url(#x)"><path d="M0 0h1v1H0z"/></svg>'))
        ->toBe('unsafe_value');
});

it('recusa paths sem d, com comando inicial invalido ou charset estranho', function (string $d) {
    expect(parseCustomLogoError(customSvg('<path d="'.$d.'"/>')))->toBe('invalid_path');
})->with([
    'vazio' => [''],
    'sem moveto' => ['L0 0h1v1H0z'],
    'com letra proibida' => ['M0 0h1v1x'],
    'com cerquilha' => ['M0 0h1v1H0z#'],
    'com chaves' => ['M0 0h1v1H0z{}'],
]);

it('exige pelo menos um path', function () {
    expect(parseCustomLogoError(customSvg('<g><g/></g>')))->toBe('missing_paths');
});

it('limita a profundidade de grupos', function () {
    config(['badgeforge.custom_logos.max_document_depth' => 3]);

    expect(parseCustomLogoError(customSvg('<g><g><g><path d="M0 0h1v1H0z"/></g></g></g>')))
        ->toBe('too_deep');
});

it('limita o tamanho de cada path e o total', function () {
    config([
        'badgeforge.custom_logos.max_path_length' => 20,
        'badgeforge.custom_logos.max_total_path_length' => 30,
        'badgeforge.custom_logos.max_paths' => 10,
    ]);

    expect(parseCustomLogoError(customSvg('<path d="M0 0h1v1H0z12345678901"/>')))
        ->toBe('path_too_long');
});

it('limita o total de paths', function () {
    config(['badgeforge.custom_logos.max_paths' => 2]);

    $paths = '<path d="M0 0h1v1H0z"/><path d="M2 2h1v1H2z"/><path d="M4 4h1v1H4z"/>';

    expect(parseCustomLogoError(customSvg($paths)))->toBe('too_many_paths');
});

it('limita a soma dos paths', function () {
    config([
        'badgeforge.custom_logos.max_total_path_length' => 30,
        'badgeforge.custom_logos.max_path_length' => 100,
    ]);

    $d = 'M0 0h1v1H0z';
    $paths = '<path d="'.$d.'"/><path d="'.$d.'"/><path d="'.$d.'"/>';

    expect(parseCustomLogoError(customSvg($paths)))->toBe('total_path_too_long');
});

it('valida o charset e o inicio de um path isolado', function () {
    expect(CustomLogoSvgParser::isSafePathData('M0 0h1v1H0z'))->toBeTrue()
        ->and(CustomLogoSvgParser::isSafePathData('m0 0h1v1H0z'))->toBeTrue()
        ->and(CustomLogoSvgParser::isSafePathData(''))->toBeFalse()
        ->and(CustomLogoSvgParser::isSafePathData('L0 0h1'))->toBeFalse()
        ->and(CustomLogoSvgParser::isSafePathData('M0 0<script>'))->toBeFalse();
});
