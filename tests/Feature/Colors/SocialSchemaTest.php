<?php

use App\Models\ColorPalette;
use App\Models\ColorPaletteLike;
use App\Models\User;
use App\Models\UserFollow;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
| Base social da F6 (ADR-135 a ADR-137).
|
| A linha e unica por ator: a conta nao curte duas vezes a mesma paleta e o
| mesmo identificador anonimo nao cria duas linhas. Identificadores anonimos
| diferentes convivem na mesma paleta. A tabela de notificacoes e o canal
| database do Laravel.
*/

it('impede curtida duplicada da mesma conta na mesma paleta', function () {
    $user = User::factory()->create();
    $palette = ColorPalette::factory()->public()->create();

    ColorPaletteLike::create([
        'color_palette_id' => $palette->id,
        'user_id' => $user->id,
        'liked_at' => now(),
    ]);

    expect(fn () => ColorPaletteLike::create([
        'color_palette_id' => $palette->id,
        'user_id' => $user->id,
        'liked_at' => now(),
    ]))->toThrow(UniqueConstraintViolationException::class);
});

it('impede curtida duplicada do mesmo anonimo e aceita identificadores distintos', function () {
    $palette = ColorPalette::factory()->public()->create();
    $anonymous = str_repeat('a', 64);

    ColorPaletteLike::create([
        'color_palette_id' => $palette->id,
        'anonymous_id' => $anonymous,
        'liked_at' => now(),
    ]);

    ColorPaletteLike::create([
        'color_palette_id' => $palette->id,
        'anonymous_id' => str_repeat('b', 64),
        'liked_at' => now(),
    ]);

    expect(ColorPaletteLike::query()->where('color_palette_id', $palette->id)->count())->toBe(2);

    expect(fn () => ColorPaletteLike::create([
        'color_palette_id' => $palette->id,
        'anonymous_id' => $anonymous,
        'liked_at' => now(),
    ]))->toThrow(UniqueConstraintViolationException::class);
});

it('impede seguir a mesma conta duas vezes', function () {
    $follower = User::factory()->create();
    $followed = User::factory()->create();

    UserFollow::create([
        'follower_id' => $follower->id,
        'followed_id' => $followed->id,
        'followed_at' => now(),
    ]);

    expect(fn () => UserFollow::create([
        'follower_id' => $follower->id,
        'followed_id' => $followed->id,
        'followed_at' => now(),
    ]))->toThrow(UniqueConstraintViolationException::class);
});

it('grava uma notificacao no canal database do Laravel', function () {
    $user = User::factory()->create();

    $id = $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\Colors\\PaletteLiked',
        'data' => ['kind' => 'palette_liked', 'anonymous' => true],
    ]);

    expect($id->exists)->toBeTrue()
        ->and($user->notifications()->count())->toBe(1);
});
