<?php

use App\Actions\Colors\ClaimAnonymousLikes;
use App\Models\ColorPalette;
use App\Models\ColorPaletteLike;
use App\Models\User;
use App\Support\Colors\AnonymousIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
| Reivindicacao das curtidas anonimas no login (F6, ADR-135).
|
| A conta herda as linhas ativas do cookie, as conflitantes viram uma so e o
| aviso ja dado e preservado. Linha inativa, de paleta da propria conta ou com
| identificador invalido nao sobrevive. A operacao e idempotente.
*/

function colorsAnonymousId(): string
{
    return str_repeat('a', 64);
}

it('transfere a curtida anonima ativa para a conta', function () {
    $user = User::factory()->create();
    $palette = ColorPalette::factory()->public()->create(['slug' => 'publica']);
    $likedAt = now()->subDay()->startOfSecond();

    ColorPaletteLike::create([
        'color_palette_id' => $palette->id,
        'anonymous_id' => colorsAnonymousId(),
        'liked_at' => $likedAt,
        'notified_at' => now(),
    ]);

    $claimed = (new ClaimAnonymousLikes)($user, colorsAnonymousId());

    $like = ColorPaletteLike::query()->sole();

    expect($claimed)->toBe(1)
        ->and($like->user_id)->toBe($user->id)
        ->and($like->anonymous_id)->toBeNull()
        ->and($like->isActive())->toBeTrue()
        ->and($like->liked_at?->equalTo($likedAt))->toBeTrue();

    expect((new ClaimAnonymousLikes)($user, colorsAnonymousId()))->toBe(0);
});

it('mescla com a curtida ativa da conta e preserva o primeiro aviso', function () {
    $user = User::factory()->create();
    $palette = ColorPalette::factory()->public()->create(['slug' => 'publica']);
    $firstNotice = now()->subDays(2)->startOfSecond();

    ColorPaletteLike::create([
        'color_palette_id' => $palette->id,
        'user_id' => $user->id,
        'liked_at' => now(),
        'notified_at' => $firstNotice,
    ]);

    ColorPaletteLike::create([
        'color_palette_id' => $palette->id,
        'anonymous_id' => colorsAnonymousId(),
        'liked_at' => now(),
        'notified_at' => now(),
    ]);

    (new ClaimAnonymousLikes)($user, colorsAnonymousId());

    $like = ColorPaletteLike::query()->sole();

    expect($like->user_id)->toBe($user->id)
        ->and($like->anonymous_id)->toBeNull()
        ->and($like->notified_at?->equalTo($firstNotice))->toBeTrue();
});

it('reativa a linha inativa da conta com o estado anonimo', function () {
    $user = User::factory()->create();
    $palette = ColorPalette::factory()->public()->create(['slug' => 'publica']);
    $likedAt = now()->subHour()->startOfSecond();

    ColorPaletteLike::create([
        'color_palette_id' => $palette->id,
        'user_id' => $user->id,
        'liked_at' => null,
        'notified_at' => null,
    ]);

    ColorPaletteLike::create([
        'color_palette_id' => $palette->id,
        'anonymous_id' => colorsAnonymousId(),
        'liked_at' => $likedAt,
        'notified_at' => now(),
    ]);

    (new ClaimAnonymousLikes)($user, colorsAnonymousId());

    $like = ColorPaletteLike::query()->sole();

    expect($like->user_id)->toBe($user->id)
        ->and($like->anonymous_id)->toBeNull()
        ->and($like->isActive())->toBeTrue()
        ->and($like->liked_at?->equalTo($likedAt))->toBeTrue()
        ->and($like->notified_at)->not->toBeNull();
});

it('descarta linhas inativas, da propria conta e identificador invalido', function () {
    $user = User::factory()->create();
    $ownPalette = ColorPalette::factory()->for($user)->public()->create(['slug' => 'minha']);
    $otherPalette = ColorPalette::factory()->public()->create(['slug' => 'outra']);

    ColorPaletteLike::create([
        'color_palette_id' => $otherPalette->id,
        'anonymous_id' => colorsAnonymousId(),
        'liked_at' => null,
    ]);

    ColorPaletteLike::create([
        'color_palette_id' => $ownPalette->id,
        'anonymous_id' => colorsAnonymousId(),
        'liked_at' => now(),
    ]);

    $claimed = (new ClaimAnonymousLikes)($user, colorsAnonymousId());

    expect($claimed)->toBe(0)
        ->and(ColorPaletteLike::query()->count())->toBe(0)
        ->and((new ClaimAnonymousLikes)($user, 'x'))->toBe(0);
});

it('reivindica as curtidas anonimas no login e limpa o cookie', function () {
    $user = User::factory()->create(['username' => 'ana']);
    $palette = ColorPalette::factory()->public()->create(['slug' => 'publica']);

    $likeResponse = $this->postJson(route('api.tools.colors.palettes.like.store', $palette))
        ->assertOk();

    $identifier = $likeResponse->getCookie(AnonymousIdentity::COOKIE)->getValue();

    $this->withCookie(AnonymousIdentity::COOKIE, $identifier)
        ->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])
        ->assertRedirect()
        ->assertCookieExpired(AnonymousIdentity::COOKIE);

    $this->assertAuthenticatedAs($user);

    $like = ColorPaletteLike::query()->sole();

    expect($like->user_id)->toBe($user->id)
        ->and($like->anonymous_id)->toBeNull()
        ->and($like->isActive())->toBeTrue();
});

it('deixa o login seguir sem curtidas anonimas', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect();

    $this->assertAuthenticatedAs($user);
});
