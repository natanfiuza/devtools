<?php

use App\Models\ColorPalette;
use App\Models\ColorPaletteLike;
use App\Models\User;
use App\Notifications\Colors\PaletteLiked;
use App\Support\Colors\AnonymousIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
| Curtidas de paletas publicas (F6, ADR-135).
|
| O visitante curte por cookie first-party, a conta curte pelo login, o dono
| nao curte a propria paleta e a resposta nunca devolve o identificador. A
| linha e permanente e o aviso ao dono acontece uma unica vez por relacao.
*/

it('deixa o visitante curtir e emite o cookie anonimo', function () {
    $palette = ColorPalette::factory()->public()->create(['slug' => 'publica']);

    $response = $this->postJson(route('api.tools.colors.palettes.like.store', $palette))
        ->assertOk()
        ->assertJsonPath('liked', true)
        ->assertJsonPath('likes_count', 1)
        ->assertJsonMissingPath('anonymous_id');

    $response->assertCookie(AnonymousIdentity::COOKIE);

    $identifier = $response->getCookie(AnonymousIdentity::COOKIE)->getValue();
    $like = ColorPaletteLike::query()->sole();

    expect($identifier)->toMatch('/^[a-f0-9]{64}$/')
        ->and($like->anonymous_id)->toBe($identifier)
        ->and($like->user_id)->toBeNull()
        ->and($like->isActive())->toBeTrue();
});

it('nao deve anexar cookie anonimo a uma curtida recusada', function () {
    $palette = ColorPalette::factory()->create(['slug' => 'privada']);

    $this->postJson(route('api.tools.colors.palettes.like.store', $palette))
        ->assertNotFound()
        ->assertCookieMissing(AnonymousIdentity::COOKIE);
});

it('mantem a mesma linha e a mesma contagem ao curtir duas vezes', function () {
    $palette = ColorPalette::factory()->public()->create(['slug' => 'publica']);

    $first = $this->postJson(route('api.tools.colors.palettes.like.store', $palette))
        ->assertOk();

    $identifier = $first->getCookie(AnonymousIdentity::COOKIE)->getValue();

    $this->withCredentials()
        ->withCookie(AnonymousIdentity::COOKIE, $identifier)
        ->postJson(route('api.tools.colors.palettes.like.store', $palette))
        ->assertOk()
        ->assertJsonPath('liked', true)
        ->assertJsonPath('likes_count', 1);

    expect(ColorPaletteLike::query()->count())->toBe(1);
});

it('descurte, preserva a linha e nao reavisa ao curtir de novo', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $palette = ColorPalette::factory()->for($owner)->public()->create(['slug' => 'publica']);

    $first = $this->postJson(route('api.tools.colors.palettes.like.store', $palette))
        ->assertOk();

    $identifier = $first->getCookie(AnonymousIdentity::COOKIE)->getValue();
    $this->withCredentials()->withCookie(AnonymousIdentity::COOKIE, $identifier);

    $this->deleteJson(route('api.tools.colors.palettes.like.destroy', $palette))
        ->assertOk()
        ->assertJsonPath('liked', false)
        ->assertJsonPath('likes_count', 0);

    $like = ColorPaletteLike::query()->sole();

    expect($like->liked_at)->toBeNull()
        ->and($like->notified_at)->not->toBeNull();

    $this->postJson(route('api.tools.colors.palettes.like.store', $palette))
        ->assertOk()
        ->assertJsonPath('likes_count', 1);

    expect(ColorPaletteLike::query()->count())->toBe(1);

    Notification::assertSentToTimes($owner, PaletteLiked::class, 1);
});

it('conta apenas curtidas ativas', function () {
    $palette = ColorPalette::factory()->public()->create(['slug' => 'publica']);

    ColorPaletteLike::create([
        'color_palette_id' => $palette->id,
        'anonymous_id' => str_repeat('a', 64),
        'liked_at' => now(),
    ]);

    ColorPaletteLike::create([
        'color_palette_id' => $palette->id,
        'anonymous_id' => str_repeat('b', 64),
        'liked_at' => null,
    ]);

    $this->postJson(route('api.tools.colors.palettes.like.store', $palette))
        ->assertOk()
        ->assertJsonPath('likes_count', 2);
});

it('curte pela conta sem emitir cookie', function () {
    $user = User::factory()->create();
    $palette = ColorPalette::factory()->public()->create(['slug' => 'publica']);

    $this->actingAs($user)
        ->postJson(route('api.tools.colors.palettes.like.store', $palette))
        ->assertOk()
        ->assertJsonPath('liked', true)
        ->assertJsonPath('likes_count', 1)
        ->assertCookieMissing(AnonymousIdentity::COOKIE);

    $like = ColorPaletteLike::query()->sole();

    expect($like->user_id)->toBe($user->id)
        ->and($like->anonymous_id)->toBeNull();
});

it('recusa curtir a propria paleta', function () {
    $owner = User::factory()->create();
    $palette = ColorPalette::factory()->for($owner)->public()->create(['slug' => 'minha']);

    $this->actingAs($owner)
        ->postJson(route('api.tools.colors.palettes.like.store', $palette))
        ->assertForbidden()
        ->assertJsonPath('error.code', 'forbidden');

    $this->actingAs($owner)
        ->deleteJson(route('api.tools.colors.palettes.like.destroy', $palette))
        ->assertForbidden();

    expect(ColorPaletteLike::query()->count())->toBe(0);
});

it('responde 404 para paleta privada, arquivada ou desconhecida', function () {
    $palette = ColorPalette::factory()->public()->create(['slug' => 'publica']);

    $this->postJson(route('api.tools.colors.palettes.like.store', ColorPalette::factory()->create(['slug' => 'privada'])))
        ->assertNotFound();

    $archived = ColorPalette::factory()->public()->archived()->create(['slug' => 'arquivada']);

    $this->postJson(route('api.tools.colors.palettes.like.store', $archived))
        ->assertNotFound();

    $this->postJson(route('api.tools.colors.palettes.like.store', Str::ulid()))
        ->assertNotFound();

    expect(ColorPaletteLike::query()->count())->toBe(0)
        ->and($palette->fresh()->isPublic())->toBeTrue();
});

it('avisa o dono uma unica vez, inclusive de curtida anonima', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $palette = ColorPalette::factory()->for($owner)->public()->create(['slug' => 'publica']);

    $first = $this->postJson(route('api.tools.colors.palettes.like.store', $palette))
        ->assertOk();

    $identifier = $first->getCookie(AnonymousIdentity::COOKIE)->getValue();

    $this->withCredentials()
        ->withCookie(AnonymousIdentity::COOKIE, $identifier)
        ->postJson(route('api.tools.colors.palettes.like.store', $palette))
        ->assertOk();

    Notification::assertSentToTimes($owner, PaletteLiked::class, 1);
    Notification::assertSentTo(
        $owner,
        PaletteLiked::class,
        fn (PaletteLiked $notification): bool => $notification->anonymous === true
            && $notification->actorId === null
            && $notification->actorName === null
            && $notification->actorUsername === null
            && $notification->paletteId === $palette->id,
    );
});

it('nao avisa conta suspensa, mas marca a relacao como avisada', function () {
    Notification::fake();

    $owner = User::factory()->create(['suspended_at' => now()]);
    $palette = ColorPalette::factory()->for($owner)->public()->create(['slug' => 'publica']);

    $this->postJson(route('api.tools.colors.palettes.like.store', $palette))
        ->assertOk();

    Notification::assertNothingSent();

    expect(ColorPaletteLike::query()->sole()->notified_at)->not->toBeNull();
});

it('limita por IP anonimo e responde 429 em JSON', function () {
    Cache::flush();
    config(['colors.rates.social_per_minute' => 1]);

    $palette = ColorPalette::factory()->public()->create(['slug' => 'publica']);

    $this->postJson(route('api.tools.colors.palettes.like.store', $palette))->assertOk();

    $this->postJson(route('api.tools.colors.palettes.like.store', $palette))
        ->assertStatus(429)
        ->assertJsonMissingPath('anonymous_id');
});
