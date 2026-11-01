<?php

use App\Support\BadgeForge\Color;
use Tests\TestCase;

uses(TestCase::class);

/*
| Parser de cor da F1: hex 3/4/6/8, rgb(a), hsl(a), nomes CSS, aliases de
| badge e transparent. Entrada invalida devolve null - o fallback e decisao
| de quem chama.
*/

it('normaliza hex de 3, 4, 6 e 8 digitos', function () {
    expect(Color::parse('#4c1')?->hex)->toBe('#44cc11')
        ->and(Color::parse('4c1')?->hex)->toBe('#44cc11')
        ->and(Color::parse('#4c18')?->alpha)->toBe(0.533)
        ->and(Color::parse('#007EC6')?->hex)->toBe('#007ec6')
        ->and(Color::parse('#007ec6cc')?->alpha)->toBe(0.8);
});

it('normaliza rgb e rgba', function () {
    $rgb = Color::parse('rgb(0, 126, 198)');
    $rgba = Color::parse('rgba(0,126,198,0.6)');

    expect($rgb?->hex)->toBe('#007ec6')
        ->and($rgb?->alpha)->toBe(1.0)
        ->and($rgba?->hex)->toBe('#007ec6')
        ->and($rgba?->alpha)->toBe(0.6);
});

it('normaliza hsl e hsla', function () {
    $hsl = Color::parse('hsl(210, 100%, 39%)');
    $hsla = Color::parse('hsla(210,100%,20%,.8)');

    expect($hsl?->hex)->toBe('#0063c7')
        ->and($hsla?->hex)->toBe('#003366')
        ->and($hsla?->alpha)->toBe(0.8);
});

it('resolve nomes css e aliases de badge com precedencia do alias', function () {
    expect(Color::parse('rebeccapurple')?->hex)->toBe('#663399')
        ->and(Color::parse('blueviolet')?->hex)->toBe('#8a2be2')
        ->and(Color::parse('brightgreen')?->hex)->toBe('#44cc11')
        // "blue" e "green" existem no CSS, mas o alias de badge vence.
        ->and(Color::parse('blue')?->hex)->toBe('#007ec6')
        ->and(Color::parse('green')?->hex)->toBe('#97ca00')
        ->and(Color::parse('lightgrey')?->hex)->toBe('#9f9f9f');
});

it('aceita transparent e ignora caixa e espacos', function () {
    expect(Color::parse(' transparent ')?->alpha)->toBe(0.0)
        ->and(Color::parse('  BLUE  ')?->hex)->toBe('#007ec6');
});

it('devolve null para entrada invalida', function () {
    expect(Color::parse(null))->toBeNull()
        ->and(Color::parse(''))->toBeNull()
        ->and(Color::parse('not-a-color'))->toBeNull()
        ->and(Color::parse('rgb(300,0,0)'))->toBeNull()
        ->and(Color::parse('hsl(10,200%,50%)'))->toBeNull()
        ->and(Color::parse('url(#x)'))->toBeNull()
        ->and(Color::parse('javascript:alert(1)'))->toBeNull()
        ->and(Color::parse(['#fff']))->toBeNull();
});
