<?php

use App\Models\ColorPalette;
use App\Models\LayoutSystem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
| Pagina publica e duplicacao por HTTP (F4, ADR-172/ADR-174).
|
| Publico so resolve sistema publico, ativo e com slug; privado, arquivado e
| inexistente tem o mesmo 404. O credito da origem nunca expoe paleta privada
| e a FK da origem nao sai nas props.
*/

it('mostra o sistema publico sem expor a fk da origem', function () {
    $system = LayoutSystem::factory()->public()->create([
        'name' => 'Sistema Publico',
        'slug' => 'sistema-publico',
    ]);

    $this->get(route('colors.systems.show', ['locale' => 'en', 'slug' => 'sistema-publico']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('colors/PublicLayoutSystem')
            ->where('system.id', $system->id)
            ->where('system.name', 'Sistema Publico')
            ->where('system.slug', 'sistema-publico')
            ->where('system.tokens_count', 66)
            ->missing('system.source_palette_id')
            ->missing('system.state.snapshot.seedText'));
});

it('responde 404 para privado, arquivado e inexistente', function () {
    LayoutSystem::factory()->create(['slug' => 'privado']);
    LayoutSystem::factory()->archived()->create(['slug' => 'arquivado']);

    $this->get(route('colors.systems.show', ['locale' => 'en', 'slug' => 'privado']))
        ->assertNotFound();
    $this->get(route('colors.systems.show', ['locale' => 'en', 'slug' => 'arquivado']))
        ->assertNotFound();
    $this->get(route('colors.systems.show', ['locale' => 'en', 'slug' => 'inexistente']))
        ->assertNotFound();
});

it('exibe credito publico quando a origem era publica', function () {
    $author = User::factory()->create(['name' => 'Autora', 'username' => 'autora']);
    $palette = ColorPalette::factory()->public()->for($author)->create([
        'name' => 'Oceano',
        'slug' => 'oceano',
    ]);

    $system = LayoutSystem::factory()->public()->for($author)->create([
        'source_palette_id' => $palette->id,
        'source' => [
            'palette_name' => 'Oceano',
            'palette_slug' => 'oceano',
            'palette_public' => true,
            'author_name' => 'Autora',
            'author_username' => $author->username,
            'captured_at' => now()->toIso8601String(),
        ],
    ]);

    $this->get(route('colors.systems.show', ['locale' => 'pt-br', 'slug' => $system->slug]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('system.credit.palette_name', 'Oceano')
            ->where('system.credit.palette_url', route('colors.palettes.show', [
                'locale' => 'pt-br',
                'slug' => 'oceano',
            ]))
            ->where('system.author.name', 'Autora'));
});

it('omite credito quando a origem privada nunca foi publica', function () {
    $system = LayoutSystem::factory()->public()->create([
        'source' => [
            'palette_name' => 'Rascunho',
            'palette_slug' => null,
            'palette_public' => false,
            'author_name' => 'Alguem',
            'author_username' => 'alguem',
            'captured_at' => now()->toIso8601String(),
        ],
    ]);

    $this->get(route('colors.systems.show', ['locale' => 'en', 'slug' => $system->slug]))
        ->assertInertia(fn (Assert $page) => $page->where('system.credit', null));
});

it('mantem o texto do credito e remove o link quando a paleta sumiu', function () {
    $author = User::factory()->create();
    $palette = ColorPalette::factory()->public()->for($author)->create(['slug' => 'sumindo']);

    $system = LayoutSystem::factory()->public()->for($author)->create([
        'source_palette_id' => $palette->id,
        'source' => [
            'palette_name' => 'Sumindo',
            'palette_slug' => 'sumindo',
            'palette_public' => true,
            'captured_at' => now()->toIso8601String(),
        ],
    ]);

    $palette->delete();

    $this->get(route('colors.systems.show', ['locale' => 'en', 'slug' => $system->slug]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('system.credit.palette_name', 'Sumindo')
            ->where('system.credit.palette_url', null));
});

it('exige conta para duplicar e leva ao editor da copia', function () {
    $system = LayoutSystem::factory()->public()->create();

    $this->get(route('colors.systems.duplicate.create', ['locale' => 'en', 'slug' => $system->slug]))
        ->assertRedirect(route('login'));

    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->post(route('colors.systems.duplicate.store', ['locale' => 'en', 'slug' => $system->slug]));

    $copy = LayoutSystem::query()->whereKeyNot($system->id)->sole();

    $response->assertRedirect(route('dashboard.colors.systems.edit', $copy));

    expect($copy->user_id)->toBe($user->id)
        ->and($copy->visibility->value)->toBe('private')
        ->and($copy->duplicated_from_id)->toBe($system->id)
        ->and($copy->source_palette_id)->toBeNull();
});

it('nao duplica sistema privado pela url publica', function () {
    LayoutSystem::factory()->create(['slug' => 'privado']);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('colors.systems.duplicate.store', ['locale' => 'en', 'slug' => 'privado']))
        ->assertNotFound();
});
