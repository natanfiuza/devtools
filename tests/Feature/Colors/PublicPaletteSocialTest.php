<?php

use App\Models\ColorPalette;
use App\Models\ColorPaletteFavorite;
use App\Models\User;
use App\Models\UserFollow;
use App\Support\Colors\AnonymousIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
| Autor, curtida e seguir na pagina publica da paleta (F6, ADR-134/ADR-135).
|
| A byline so aparece para conta visivel; o visitante curte direto e le o
| proprio estado pelo cookie; a conta segue o autor e a estrela da F5 continua
| independente da curtida.
*/

it('mostra autor e curtida para o visitante', function () {
    $owner = User::factory()->create(['username' => 'ana', 'name' => 'Ana']);
    $palette = ColorPalette::factory()->for($owner)->public()->create(['slug' => 'publica']);

    $this->get(route('colors.palettes.show', ['locale' => 'en', 'slug' => 'publica']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('colors/PublicPalette')
            ->where('palette.author.username', 'ana')
            ->where('palette.author.name', 'Ana')
            ->where('palette.likes_count', 0)
            ->where('liked', false)
            ->where('canLike', true)
            ->where('following', false)
            ->where('canFollow', false)
            ->where('favorited', false)
            ->where('canFavorite', false)
        );
});

it('le a curtida anonima pelo cookie emitido no like', function () {
    $owner = User::factory()->create(['username' => 'ana']);
    $palette = ColorPalette::factory()->for($owner)->public()->create(['slug' => 'publica']);

    $likeResponse = $this->postJson(route('api.tools.colors.palettes.like.store', $palette))
        ->assertOk();

    $identifier = $likeResponse->getCookie(AnonymousIdentity::COOKIE)->getValue();

    $this->withCookie(AnonymousIdentity::COOKIE, $identifier)
        ->get(route('colors.palettes.show', ['locale' => 'en', 'slug' => 'publica']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('liked', true)
            ->where('palette.likes_count', 1)
        );
});

it('mostra curtida e seguir para a conta logada sem mexer no favorito', function () {
    $owner = User::factory()->create(['username' => 'ana']);
    $viewer = User::factory()->create();
    $palette = ColorPalette::factory()->for($owner)->public()->create(['slug' => 'publica']);

    ColorPaletteFavorite::create([
        'user_id' => $viewer->id,
        'color_palette_id' => $palette->id,
    ]);

    $this->actingAs($viewer)
        ->postJson(route('api.tools.colors.palettes.like.store', $palette))
        ->assertOk();

    $this->actingAs($viewer)
        ->postJson(route('api.tools.colors.users.follow.store', $owner))
        ->assertOk();

    $this->actingAs($viewer)
        ->get(route('colors.palettes.show', ['locale' => 'en', 'slug' => 'publica']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('palette.author.username', 'ana')
            ->where('liked', true)
            ->where('canLike', true)
            ->where('following', true)
            ->where('canFollow', true)
            ->where('favorited', true)
            ->where('canFavorite', true)
            ->where('palette.likes_count', 1)
        );
});

it('nao oferece curtir nem seguir a propria paleta', function () {
    $owner = User::factory()->create(['username' => 'ana']);
    $palette = ColorPalette::factory()->for($owner)->public()->create(['slug' => 'minha']);

    $this->actingAs($owner)
        ->get(route('colors.palettes.show', ['locale' => 'en', 'slug' => 'minha']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('palette.author.username', 'ana')
            ->where('liked', false)
            ->where('canLike', false)
            ->where('following', false)
            ->where('canFollow', false)
        );
});

it('esconde a byline de autor suspenso ou anonimizado', function () {
    $suspended = User::factory()->create(['username' => 'suspenso', 'suspended_at' => now()]);
    $anonymized = User::factory()->create(['username' => 'anonimo', 'anonymized_at' => now()]);

    ColorPalette::factory()->for($suspended)->public()->create(['slug' => 'suspensa']);
    ColorPalette::factory()->for($anonymized)->public()->create(['slug' => 'anonima']);

    foreach (['suspensa', 'anonima'] as $slug) {
        $this->get(route('colors.palettes.show', ['locale' => 'en', 'slug' => $slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('palette.author', null)
                ->where('canFollow', false)
            );
    }
});

it('nao segue o autor sem conta', function () {
    $owner = User::factory()->create(['username' => 'ana']);
    ColorPalette::factory()->for($owner)->public()->create(['slug' => 'publica']);

    $this->get(route('colors.palettes.show', ['locale' => 'en', 'slug' => 'publica']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('canFollow', false)
            ->where('following', false)
        );

    expect(UserFollow::query()->count())->toBe(0);
});
