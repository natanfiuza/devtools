<?php

use App\Models\User;
use App\Support\Appearance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
| O tema e resolvido no servidor (docs/adr/ADR-021): o <html> ja sai com a
| classe e com data-appearance, entao nao existe flash de tema errado. Sem
| preferencia salva, o tema e "system" e um script curto decide antes da
| primeira pintura.
*/

it('usa o sistema quando nao ha preferencia', function () {
    $response = $this->get('/en')->assertOk();

    expect($response->getContent())
        ->toContain('data-appearance="system"')
        ->toContain("matchMedia('(prefers-color-scheme: dark)')")
        ->not->toMatch('/<html\b[^>]*\bclass="dark"/');

    $response->assertInertia(fn (Assert $page) => $page->where('appearance', Appearance::SYSTEM));
});

it('aplica o tema escuro sem script inline', function () {
    $response = $this->withUnencryptedCookie('appearance', Appearance::DARK)
        ->get('/en')
        ->assertOk();

    $content = $response->getContent();

    expect($content)
        ->toContain('data-appearance="dark"')
        ->toMatch('/<html\b[^>]*\bclass="dark"/')
        // Com preferencia salva quem decide e o servidor: nada de script.
        ->not->toContain("matchMedia('(prefers-color-scheme: dark)')");

    $response->assertInertia(fn (Assert $page) => $page->where('appearance', Appearance::DARK));
});

it('aplica o tema claro', function () {
    $response = $this->withUnencryptedCookie('appearance', Appearance::LIGHT)
        ->get('/en')
        ->assertOk();

    expect($response->getContent())
        ->toContain('data-appearance="light"')
        ->not->toMatch('/<html\b[^>]*\bclass="dark"/');
});

it('le a preferencia da conta quando nao ha cookie', function () {
    $user = User::factory()->create(['appearance' => Appearance::DARK]);

    $this->actingAs($user)
        ->get('/en')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('appearance', Appearance::DARK));
});

it('da prioridade ao cookie sobre a conta', function () {
    $user = User::factory()->create(['appearance' => Appearance::DARK]);

    $this->actingAs($user)
        ->withUnencryptedCookie('appearance', Appearance::LIGHT)
        ->get('/en')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('appearance', Appearance::LIGHT));
});

it('ignora um valor desconhecido', function () {
    $this->withUnencryptedCookie('appearance', 'neon')
        ->get('/en')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('appearance', Appearance::SYSTEM));
});
