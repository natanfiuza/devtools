<?php

use App\Support\BadgeForge\PngCache;
use App\Support\BadgeForge\PngRenderer;
use Illuminate\Support\Facades\File;
use Mockery\MockInterface;

/*
| PNG da badge estatica (F2, ADR-077).
|
| O rasterizador real depende da extensao imagick e do fontconfig do
| servidor; os testes de bytes usam o renderer de verdade e os de cache
| substituem a rasterizacao por um mock deterministico.
*/

const PNG_CACHE_DIR = 'framework/testing/badgeforge-png';

beforeEach(function () {
    File::deleteDirectory(storage_path(PNG_CACHE_DIR));

    config(['badgeforge.png.cache_path' => PNG_CACHE_DIR]);
});

afterEach(function () {
    File::deleteDirectory(storage_path(PNG_CACHE_DIR));
});

function skipWithoutImagick(): void
{
    if (! extension_loaded('imagick')) {
        test()->markTestSkipped('Extensao imagick ausente neste ambiente.');
    }
}

function pngDimension(string $png, int $offset): int
{
    $value = unpack('N', substr($png, $offset, 4));

    return $value === false ? 0 : (int) $value[1];
}

function pngWidth(string $png): int
{
    return pngDimension($png, 16);
}

function pngHeight(string $png): int
{
    return pngDimension($png, 20);
}

it('serve o PNG com content-type e assinatura valida', function () {
    skipWithoutImagick();

    $response = $this->get('/api/badges/static/build/passing.png')->assertOk();
    $png = (string) $response->getContent();

    expect($response->headers->get('Content-Type'))->toBe('image/png')
        ->and($response->headers->get('Cache-Control'))->toBe('max-age=86400, public')
        ->and(substr($png, 0, 8))->toBe("\x89PNG\r\n\x1a\n")
        ->and(pngWidth($png))->toBeGreaterThan(0)
        ->and(pngHeight($png))->toBe(20);
});

it('produz os mesmos bytes em requisicoes repetidas e reaproveita o cache', function () {
    skipWithoutImagick();

    $first = (string) $this->get('/api/badges/static/build/passing.png')->getContent();
    $second = (string) $this->get('/api/badges/static/build/passing.png')->getContent();

    expect($second)->toBe($first)
        ->and(File::files(storage_path(PNG_CACHE_DIR)))->toHaveCount(1);
});

it('limita a largura do PNG a 600px mantendo a proporcao', function () {
    skipWithoutImagick();

    $long = str_repeat('x', 100);
    $svg = (string) $this->get('/api/badges/static/'.$long.'/'.$long.'.svg')->getContent();
    $png = (string) $this->get('/api/badges/static/'.$long.'/'.$long.'.png')->getContent();

    preg_match('/<svg[^>]*\bwidth="(\d+)"/', $svg, $match);
    $naturalWidth = (int) ($match[1] ?? 0);

    expect($naturalWidth)->toBeGreaterThan(600)
        ->and(pngWidth($png))->toBe(600)
        ->and(pngHeight($png))->toBe((int) round(20 * 600 / $naturalWidth));
});

it('mantem ETag e 304 iguais aos do SVG', function () {
    skipWithoutImagick();

    $first = $this->get('/api/badges/static/build/passing.png')->assertOk();
    $png = (string) $first->getContent();

    expect($first->headers->get('ETag'))->toBe('"'.hash('sha256', $png).'"');

    $this->withHeaders(['If-None-Match' => (string) $first->headers->get('ETag')])
        ->get('/api/badges/static/build/passing.png')
        ->assertStatus(304)
        ->assertHeaderMissing('Content-Type');
});

it('responde com anexo quando download=1, nos dois formatos', function () {
    skipWithoutImagick();

    $svg = $this->get('/api/badges/static/build/passing.svg?download=1')->assertOk();

    expect($svg->headers->get('Content-Disposition'))
        ->toBe('attachment; filename="build-passing.svg"; filename*=UTF-8\'\'build-passing.svg');

    $png = $this->get('/api/badges/static/build/passing.png?download=1')->assertOk();

    expect($png->headers->get('Content-Disposition'))
        ->toBe('attachment; filename="build-passing.png"; filename*=UTF-8\'\'build-passing.png');
});

it('sanitiza o nome do anexo e preserva o acento no filename*', function () {
    $response = $this->get('/api/badges/static/A%C3%A7%C3%A3o/caf%C3%A9.svg?download=1')->assertOk();
    $name = 'Ação-café';

    expect($response->headers->get('Content-Disposition'))
        ->toBe('attachment; filename="Acao-cafe.svg"; filename*=UTF-8\'\''.rawurlencode($name.'.svg'));
});

it('nao anexa quando download tem outro valor', function () {
    $response = $this->get('/api/badges/static/build/passing.svg?download=true')->assertOk();

    expect($response->headers->has('Content-Disposition'))->toBeFalse();
});

it('grava o PNG no cache endereçado pelo sha256 do SVG', function () {
    skipWithoutImagick();

    $this->get('/api/badges/static/build/passing.png')->assertOk();

    $files = File::files(storage_path(PNG_CACHE_DIR));

    expect($files)->toHaveCount(1)
        ->and($files[0]->getExtension())->toBe('png');
});

it('nao rasteriza de novo quando o cache ja tem o PNG', function () {
    $this->mock(PngRenderer::class, function (MockInterface $mock): void {
        $mock->shouldReceive('isSupported')->andReturnTrue();
        $mock->shouldReceive('render')->once()->andReturn('fake-png-bytes');
    });

    $first = $this->get('/api/badges/static/build/passing.png')->assertOk();
    $second = $this->get('/api/badges/static/build/passing.png')->assertOk();

    expect($first->getContent())->toBe('fake-png-bytes')
        ->and($second->getContent())->toBe('fake-png-bytes')
        ->and(File::files(storage_path(PNG_CACHE_DIR)))->toHaveCount(1);
});

it('responde 503 com image_unavailable quando nao ha Imagick', function () {
    $this->mock(PngRenderer::class, function (MockInterface $mock): void {
        $mock->shouldReceive('isSupported')->andReturnFalse();
        $mock->shouldReceive('render')->never();
    });

    $this->get('/api/badges/static/build/passing.png')
        ->assertStatus(503)
        ->assertJsonPath('error.code', 'image_unavailable');
});

it('serve HEAD do PNG sem corpo e com ETag', function () {
    skipWithoutImagick();

    $response = $this->head('/api/badges/static/build/passing.png')->assertOk();

    expect($response->getContent())->toBe('')
        ->and($response->headers->get('ETag'))->not->toBeNull();
});

it('usa o mesmo SVG do endpoint ao gravar a chave do cache', function () {
    $svg = (string) $this->get('/api/badges/static/build/passing.svg')->getContent();

    expect(PngCache::path($svg))->toBe(
        storage_path(PNG_CACHE_DIR).'/'.hash('sha256', $svg).'.png',
    );
});
