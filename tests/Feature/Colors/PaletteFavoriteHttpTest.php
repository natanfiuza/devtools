<?php

use App\Models\ColorPalette;
use App\Models\ColorPaletteFavorite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
| Favoritos de paletas publicas (F5, ADR-128).
|
| O favorito e um vinculo para a origem: nada e copiado, a propria paleta nao
| pode ser favoritada e a listagem so mostra paletas que continuam publicas e
| ativas. Remover e idempotente, como no resto da biblioteca.
*/

it('redireciona visitante ao login nas rotas de favorito', function () {
    $palette = ColorPalette::factory()->public()->create(['slug' => 'publica']);

    $this->postJson(route('api.tools.colors.palettes.favorite.store', $palette))
        ->assertUnauthorized();

    $this->deleteJson(route('api.tools.colors.palettes.favorite.destroy', $palette))
        ->assertUnauthorized();
});

it('favorita e desfavorita de forma idempotente sem copiar a paleta', function () {
    $owner = User::factory()->create();
    $palette = ColorPalette::factory()->for($owner)->public()->create(['slug' => 'publica']);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.tools.colors.palettes.favorite.store', $palette))
        ->assertOk()
        ->assertJsonPath('favorited', true);

    $this->actingAs($user)
        ->postJson(route('api.tools.colors.palettes.favorite.store', $palette))
        ->assertOk()
        ->assertJsonPath('favorited', true);

    expect(ColorPaletteFavorite::query()->forUser($user->id)->count())->toBe(1)
        ->and(ColorPalette::query()->forUser($user->id)->count())->toBe(0)
        ->and($palette->fresh()->duplications_count)->toBe(0);

    $this->actingAs($user)
        ->deleteJson(route('api.tools.colors.palettes.favorite.destroy', $palette))
        ->assertOk()
        ->assertJsonPath('favorited', false);

    $this->actingAs($user)
        ->deleteJson(route('api.tools.colors.palettes.favorite.destroy', $palette))
        ->assertOk()
        ->assertJsonPath('favorited', false);

    expect(ColorPaletteFavorite::query()->forUser($user->id)->count())->toBe(0);
});

it('recusa favoritar a propria paleta', function () {
    $user = User::factory()->create();
    $palette = ColorPalette::factory()->for($user)->public()->create(['slug' => 'minha']);

    $this->actingAs($user)
        ->postJson(route('api.tools.colors.palettes.favorite.store', $palette))
        ->assertForbidden()
        ->assertJsonPath('error.code', 'forbidden');

    expect(ColorPaletteFavorite::query()->forUser($user->id)->count())->toBe(0);
});

it('responde 404 para paleta privada, arquivada ou desconhecida', function () {
    $user = User::factory()->create();
    $private = ColorPalette::factory()->create(['slug' => 'privada']);
    $archived = ColorPalette::factory()->public()->archived()->create(['slug' => 'arquivada']);

    $this->actingAs($user)
        ->postJson(route('api.tools.colors.palettes.favorite.store', $private))
        ->assertNotFound();

    $this->actingAs($user)
        ->postJson(route('api.tools.colors.palettes.favorite.store', $archived))
        ->assertNotFound();

    $this->actingAs($user)
        ->postJson('/api/tools/colors/palettes/'.Str::ulid().'/favorite')
        ->assertNotFound();

    expect(ColorPaletteFavorite::query()->count())->toBe(0);
});

it('lista as favoritas com a aba da conta, sem as que deixaram de ser publicas', function () {
    $owner = User::factory()->create();
    $user = User::factory()->create();

    $public = ColorPalette::factory()->for($owner)->public()->create([
        'name' => 'Publica',
        'slug' => 'publica',
    ]);
    $private = ColorPalette::factory()->for($owner)->public()->create(['slug' => 'privada']);
    $archived = ColorPalette::factory()->for($owner)->public()->archived()->create(['slug' => 'arquivada']);

    $older = ColorPaletteFavorite::create([
        'user_id' => $user->id,
        'color_palette_id' => $public->id,
    ]);
    ColorPaletteFavorite::query()->whereKey($older->id)->update(['created_at' => now()->subMinute()]);
    $older->refresh();

    $private->update(['visibility' => 'private']);
    ColorPaletteFavorite::create(['user_id' => $user->id, 'color_palette_id' => $private->id]);
    ColorPaletteFavorite::create(['user_id' => $user->id, 'color_palette_id' => $archived->id]);

    $newer = ColorPalette::factory()->for($owner)->public()->create([
        'name' => 'Outra',
        'slug' => 'outra',
    ]);
    ColorPaletteFavorite::create([
        'user_id' => $user->id,
        'color_palette_id' => $newer->id,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.colors.palettes.index', ['tab' => 'favorites']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/colors/PalettesIndex')
            ->where('tab', 'favorites')
            ->has('favorites.data', 2)
            ->where('favorites.data.0.id', $newer->id)
            ->where('favorites.data.0.slug', 'outra')
            ->where('favorites.data.1.id', $public->id)
            ->where('favorites.data.1.slug', 'publica')
            ->where('favorites.data.1.favorited_at', $older->created_at?->toIso8601String())
            ->where('favorites.total', 2)
            ->has('favorites.data.0.favorited_at')
            ->has('favorites.data.0.preview'));

    // Paleta que voltou a ser publica reaparece sem perder o vinculo.
    $private->update(['visibility' => 'public']);

    $this->actingAs($user)
        ->get(route('dashboard.colors.palettes.index', ['tab' => 'favorites']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('favorites.data', 3)
            ->where('favorites.total', 3));
});

it('devolve a aba minha e as favoritas como nula por padrao', function () {
    $user = User::factory()->create();
    ColorPalette::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('dashboard.colors.palettes.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('tab', 'mine')
            ->where('favorites', null)
            ->has('palettes.data', 1));
});

it('nao mostra favoritos de outra conta nem permite remove-los', function () {
    $owner = User::factory()->create();
    $user = User::factory()->create();
    $palette = ColorPalette::factory()->for($owner)->public()->create(['slug' => 'publica']);

    ColorPaletteFavorite::create(['user_id' => $owner->id, 'color_palette_id' => $palette->id]);

    $this->actingAs($user)
        ->deleteJson(route('api.tools.colors.palettes.favorite.destroy', $palette))
        ->assertOk()
        ->assertJsonPath('favorited', false);

    expect(ColorPaletteFavorite::query()->forUser($owner->id)->count())->toBe(1);
});

it('expoe favorited e canFavorite na pagina publica', function () {
    $owner = User::factory()->create();
    $palette = ColorPalette::factory()->for($owner)->public()->create(['slug' => 'publica']);
    $url = route('colors.palettes.show', ['locale' => 'en', 'slug' => 'publica']);

    $this->get($url)
        ->assertInertia(fn (Assert $page) => $page
            ->where('canFavorite', false)
            ->where('favorited', false));

    $this->actingAs($owner)
        ->get($url)
        ->assertInertia(fn (Assert $page) => $page
            ->where('canFavorite', false)
            ->where('favorited', false));

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get($url)
        ->assertInertia(fn (Assert $page) => $page
            ->where('canFavorite', true)
            ->where('favorited', false));

    ColorPaletteFavorite::create(['user_id' => $user->id, 'color_palette_id' => $palette->id]);

    $this->actingAs($user)
        ->get($url)
        ->assertInertia(fn (Assert $page) => $page
            ->where('canFavorite', true)
            ->where('favorited', true));
});

it('aplica o limiter de escrita por conta no favorito', function () {
    config(['colors.rates.library_per_minute' => 1]);

    $owner = User::factory()->create();
    $first = ColorPalette::factory()->for($owner)->public()->create(['slug' => 'primeira']);
    $second = ColorPalette::factory()->for($owner)->public()->create(['slug' => 'segunda']);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.tools.colors.palettes.favorite.store', $first))
        ->assertOk();

    $this->actingAs($user)
        ->postJson(route('api.tools.colors.palettes.favorite.store', $second))
        ->assertTooManyRequests();
});
