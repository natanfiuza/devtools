<?php

use App\Models\User;
use App\Models\UserFollow;
use App\Notifications\Colors\UserFollowed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

/*
| Seguir contas (F6, ADR-136).
|
| O vinculo e unico por par e permanente; desfazer so desativa. O alvo precisa
| ser visivel (ativo, nao anonimizado, com username) e o aviso ao seguido
| acontece uma unica vez, no canal database.
*/

it('exige login para seguir', function () {
    $target = User::factory()->create(['username' => 'alvo']);

    $this->postJson(route('api.tools.colors.users.follow.store', $target))
        ->assertUnauthorized();

    $this->deleteJson(route('api.tools.colors.users.follow.destroy', $target))
        ->assertUnauthorized();

    expect(UserFollow::query()->count())->toBe(0);
});

it('segue e deixa de seguir de forma idempotente sem apagar a linha', function () {
    $follower = User::factory()->create();
    $target = User::factory()->create(['username' => 'alvo']);

    $this->actingAs($follower)
        ->postJson(route('api.tools.colors.users.follow.store', $target))
        ->assertOk()
        ->assertJsonPath('following', true)
        ->assertJsonPath('followers_count', 1);

    $this->actingAs($follower)
        ->postJson(route('api.tools.colors.users.follow.store', $target))
        ->assertOk()
        ->assertJsonPath('following', true)
        ->assertJsonPath('followers_count', 1);

    expect(UserFollow::query()->count())->toBe(1);

    $this->actingAs($follower)
        ->deleteJson(route('api.tools.colors.users.follow.destroy', $target))
        ->assertOk()
        ->assertJsonPath('following', false)
        ->assertJsonPath('followers_count', 0);

    $this->actingAs($follower)
        ->deleteJson(route('api.tools.colors.users.follow.destroy', $target))
        ->assertOk()
        ->assertJsonPath('following', false);

    $follow = UserFollow::query()->sole();

    expect($follow->followed_at)->toBeNull()
        ->and($follow->notified_at)->not->toBeNull();
});

it('recusa seguir a propria conta', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.tools.colors.users.follow.store', $user))
        ->assertForbidden()
        ->assertJsonPath('error.code', 'forbidden');

    expect(UserFollow::query()->count())->toBe(0);
});

it('responde 404 para alvo suspenso, anonimizado, sem username ou excluido', function () {
    $follower = User::factory()->create();

    $suspended = User::factory()->create(['username' => 'suspenso', 'suspended_at' => now()]);
    $anonymized = User::factory()->create(['username' => 'anonimo', 'anonymized_at' => now()]);
    $noUsername = User::factory()->create(['username' => null]);
    $deleted = User::factory()->create(['username' => 'excluido']);
    $deleted->delete();

    foreach ([$suspended, $anonymized, $noUsername, $deleted] as $target) {
        $this->actingAs($follower)
            ->postJson(route('api.tools.colors.users.follow.store', $target))
            ->assertNotFound();
    }

    expect(UserFollow::query()->count())->toBe(0);
});

it('conta somente seguidores ativos', function () {
    $target = User::factory()->create(['username' => 'alvo']);
    $follower = User::factory()->create();
    $inactive = User::factory()->create();

    UserFollow::create([
        'follower_id' => $follower->id,
        'followed_id' => $target->id,
        'followed_at' => now(),
    ]);

    UserFollow::create([
        'follower_id' => $inactive->id,
        'followed_id' => $target->id,
        'followed_at' => null,
    ]);

    $newcomer = User::factory()->create();

    $this->actingAs($newcomer)
        ->postJson(route('api.tools.colors.users.follow.store', $target))
        ->assertOk()
        ->assertJsonPath('followers_count', 2);
});

it('avisa o seguido uma unica vez e apenas no canal database', function () {
    Notification::fake();

    $follower = User::factory()->create();
    $target = User::factory()->create(['username' => 'alvo']);

    $this->actingAs($follower)
        ->postJson(route('api.tools.colors.users.follow.store', $target))
        ->assertOk();

    $this->actingAs($follower)
        ->postJson(route('api.tools.colors.users.follow.store', $target))
        ->assertOk();

    Notification::assertSentToTimes($target, UserFollowed::class, 1);

    Notification::assertSentTo(
        $target,
        UserFollowed::class,
        fn (UserFollowed $notification): bool => $notification->followerId === $follower->id
            && $notification->followerName === $follower->name
            && $notification->followerUsername === $follower->username
            && $notification->via($target) === ['database'],
    );
});

it('nao devolve e-mail nem username do alvo na resposta', function () {
    $follower = User::factory()->create();
    $target = User::factory()->create(['username' => 'alvo']);

    $this->actingAs($follower)
        ->postJson(route('api.tools.colors.users.follow.store', $target))
        ->assertOk()
        ->assertJsonStructure(['following', 'followers_count'])
        ->assertJsonMissingPath('email')
        ->assertJsonMissingPath('username');
});
