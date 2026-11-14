<?php

use App\Models\ColorPalette;
use App\Models\ColorPaletteLike;
use App\Models\User;
use App\Models\UserFollow;
use App\Support\Seo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
| Perfil publico por username (F6, ADR-134).
|
| Mostra so paletas publicas e ativas, com curtidas ativas e contadores; nao
| lista seguidores, nao expoe e-mail e responde 404 para conta invisivel. A
| pagina e indexavel, tem metas proprias e fica fora do sitemap.
*/

it('mostra apenas as paletas publicas e ativas do perfil', function () {
    $user = User::factory()->create(['name' => 'Ana', 'username' => 'ana']);
    $public = ColorPalette::factory()->for($user)->public()->create(['slug' => 'publica']);

    ColorPalette::factory()->for($user)->create(['slug' => 'privada']);
    ColorPalette::factory()->for($user)->public()->archived()->create(['slug' => 'arquivada']);

    ColorPaletteLike::create([
        'color_palette_id' => $public->id,
        'anonymous_id' => str_repeat('a', 64),
        'liked_at' => now(),
    ]);

    ColorPaletteLike::create([
        'color_palette_id' => $public->id,
        'anonymous_id' => str_repeat('b', 64),
        'liked_at' => null,
    ]);

    $this->get('/en/u/ana')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('profile.name', 'Ana')
            ->where('profile.username', 'ana')
            ->where('profile.followers_count', 0)
            ->where('profile.following_count', 0)
            ->missing('profile.email')
            ->where('following', false)
            ->where('canFollow', false)
            ->where('isOwnProfile', false)
            ->has('palettes.data', 1)
            ->where('palettes.data.0.id', $public->id)
            ->where('palettes.data.0.likes_count', 1)
        );
});

it('responde 404 para conta inexistente, suspensa, anonimizada ou username invalido', function () {
    User::factory()->create(['username' => 'suspensa', 'suspended_at' => now()]);
    User::factory()->create(['username' => 'anonima', 'anonymized_at' => now()]);

    $this->get('/en/u/ninguem')->assertNotFound();
    $this->get('/en/u/suspensa')->assertNotFound();
    $this->get('/en/u/anonima')->assertNotFound();
    $this->get('/en/u/A')->assertNotFound();
});

it('mostra o estado de seguir para quem tem conta', function () {
    $profile = User::factory()->create(['username' => 'ana']);
    $viewer = User::factory()->create();

    UserFollow::create([
        'follower_id' => $viewer->id,
        'followed_id' => $profile->id,
        'followed_at' => now(),
    ]);

    $this->actingAs($viewer)
        ->get('/en/u/ana')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('following', true)
            ->where('canFollow', true)
            ->where('isOwnProfile', false)
            ->where('profile.followers_count', 1)
        );
});

it('marca o proprio perfil e nao oferece seguir', function () {
    $owner = User::factory()->create(['username' => 'ana']);

    $this->actingAs($owner)
        ->get('/en/u/ana')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('following', false)
            ->where('canFollow', false)
            ->where('isOwnProfile', true)
        );
});

it('pagina as paletas com o limite configurado', function () {
    config(['colors.social.profile_per_page' => 1]);

    $user = User::factory()->create(['username' => 'ana']);
    ColorPalette::factory()->for($user)->public()->count(2)->create();

    $this->get('/en/u/ana')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('palettes.data', 1)
            ->where('palettes.last_page', 2)
            ->where('palettes.total', 2)
        );
});

it('escreve as metas do perfil e o deixa fora do sitemap', function () {
    User::factory()->create(['name' => 'Ana', 'username' => 'ana']);
    $title = (string) trans('colors.profile.meta.title', ['name' => 'Ana']);
    $description = (string) trans('colors.profile.meta.description', [
        'name' => 'Ana',
        'app' => (string) trans('app.brand'),
    ]);

    $html = $this->get('/en/u/ana')->assertOk()->getContent();

    expect($html)
        ->toContain('<title>'.e(Seo::documentTitle($title)).'</title>')
        ->toContain('<meta name="description" content="'.e($description).'">')
        ->not->toContain('<meta name="robots" content="noindex');

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertDontSee('/u/ana');
});
