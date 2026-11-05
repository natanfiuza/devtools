<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

/*
| `/up` com dependencias reais (F8, ADR-089).
|
| Saudavel responde 200 `up`; banco, cache ou disco do PNG fora responde 503
| `down` com o mapa das sondas. A resposta nunca carrega a excecao original.
*/

const HEALTH_CHECK_DIR = 'framework/testing/badgeforge-health';

beforeEach(function () {
    config(['badgeforge.png.cache_path' => HEALTH_CHECK_DIR]);

    File::deleteDirectory(storage_path(HEALTH_CHECK_DIR));
});

afterEach(function () {
    File::deleteDirectory(storage_path(HEALTH_CHECK_DIR));
});

it('responde 200 com as tres sondas verdes', function () {
    $response = $this->getJson('/up')
        ->assertOk()
        ->assertJson([
            'status' => 'up',
            'checks' => [
                'database' => true,
                'cache' => true,
                'storage' => true,
            ],
        ]);

    expect((string) $response->headers->get('Cache-Control'))->toContain('no-store');
});

it('responde 503 quando o banco esta fora', function () {
    $default = (string) config('database.default');

    config([
        'database.connections.health_broken' => [
            'driver' => 'sqlite',
            'database' => storage_path('nao-existe/health.sqlite'),
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
        'database.default' => 'health_broken',
    ]);

    DB::purge('health_broken');

    try {
        $this->getJson('/up')
            ->assertStatus(503)
            ->assertJsonPath('status', 'down')
            ->assertJsonPath('checks.database', false)
            ->assertJsonPath('checks.cache', true)
            ->assertJsonPath('checks.storage', true);
    } finally {
        config(['database.default' => $default]);
        DB::purge('health_broken');
    }
});

it('responde 503 quando o disco do PNG nao aceita escrita', function () {
    $blocker = storage_path(HEALTH_CHECK_DIR.'-blocker');

    File::put($blocker, 'arquivo no lugar do diretorio');
    config(['badgeforge.png.cache_path' => HEALTH_CHECK_DIR.'-blocker/sub']);

    try {
        $this->getJson('/up')
            ->assertStatus(503)
            ->assertJsonPath('status', 'down')
            ->assertJsonPath('checks.database', true)
            ->assertJsonPath('checks.cache', true)
            ->assertJsonPath('checks.storage', false);
    } finally {
        File::delete($blocker);
    }
});

it('nao devolve a excecao nem caminho interno na falha', function () {
    $blocker = storage_path(HEALTH_CHECK_DIR.'-blocker');

    File::put($blocker, 'x');
    config(['badgeforge.png.cache_path' => HEALTH_CHECK_DIR.'-blocker/sub']);

    try {
        $body = (string) $this->getJson('/up')->assertStatus(503)->getContent();
    } finally {
        File::delete($blocker);
    }

    expect($body)->not->toContain('SQLSTATE')
        ->and($body)->not->toContain(HEALTH_CHECK_DIR)
        ->and($body)->not->toContain(storage_path());
});
