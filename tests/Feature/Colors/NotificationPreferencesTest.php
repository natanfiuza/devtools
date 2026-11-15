<?php

use App\Models\ColorNotificationPreference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
| Sino e preferencias de e-mail (F7, ADR-142).
|
| A pagina e da conta: visitante vai para o login, notificacao de outra conta
| responde 404. O contador do sino vem nas props compartilhadas, so para conta
| logada. O payload exibido nao carrega o corpo do comentario nem e-mail.
*/

function notificationFor(User $user, array $data = []): string
{
    $id = (string) Str::uuid();

    $user->notifications()->create([
        'id' => $id,
        'type' => 'App\\Notifications\\Colors\\PaletteLiked',
        'data' => [
            'kind' => 'palette_liked',
            'palette_id' => 'pal-1',
            'palette_name' => 'Oceano',
            'palette_slug' => 'oceano',
            'actor_name' => 'Ana',
            'actor_username' => 'ana',
            'anonymous' => false,
            ...$data,
        ],
    ]);

    return $id;
}

it('exige conta para ver o sino e as preferencias', function () {
    $this->get(route('dashboard.notifications.index'))
        ->assertRedirect(route('login'));

    $this->patch(route('dashboard.notifications.preferences'), [
        'email_on_like' => true,
        'email_on_follow' => true,
        'email_on_comment' => true,
        'email_on_reply' => true,
    ])->assertRedirect(route('login'));
});

it('entrega a lista paginada e os defaults das preferencias', function () {
    $user = User::factory()->create();
    notificationFor($user);

    $this->actingAs($user)
        ->get(route('dashboard.notifications.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/Notifications')
            ->has('notifications.data', 1)
            ->where('notifications.data.0.kind', 'palette_liked')
            ->where('notifications.data.0.read', false)
            ->where('notifications.data.0.data.palette_name', 'Oceano')
            ->where('notifications.data.0.data.actor_name', 'Ana')
            ->where('preferences.email_on_like', false)
            ->where('preferences.email_on_follow', false)
            ->where('preferences.email_on_comment', true)
            ->where('preferences.email_on_reply', true));
});

it('nunca devolve o corpo do comentario nem dados fora da lista', function () {
    $user = User::factory()->create();
    notificationFor($user, [
        'comment_id' => 'cmt-1',
        'body' => 'segredo de quem comentou',
        'email' => 'ana@exemplo.com',
    ]);

    $response = $this->actingAs($user)
        ->get(route('dashboard.notifications.index'))
        ->assertOk();

    $response->assertDontSee('segredo de quem comentou');
    $response->assertDontSee('ana@exemplo.com');
});

it('compartilha o contador do sino apenas para a conta logada', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    notificationFor($user);
    notificationFor($user);
    notificationFor($other);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('notifications.unread', 2));
});

it('nao compartilha o sino para visitante', function () {
    $this->get(route('home', ['locale' => 'en']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('notifications', null));
});

it('cria a preferencia na primeira alteracao e reaproveita a linha', function () {
    $user = User::factory()->create();

    $payload = [
        'email_on_like' => true,
        'email_on_follow' => true,
        'email_on_comment' => false,
        'email_on_reply' => false,
    ];

    $this->actingAs($user)
        ->from(route('dashboard.notifications.index'))
        ->patch(route('dashboard.notifications.preferences'), $payload)
        ->assertRedirect(route('dashboard.notifications.index'));

    $this->actingAs($user)
        ->patch(route('dashboard.notifications.preferences'), [
            ...$payload,
            'email_on_comment' => true,
        ])
        ->assertRedirect();

    $preference = ColorNotificationPreference::query()->sole();

    expect($preference->user_id)->toBe($user->id)
        ->and($preference->email_on_like)->toBeTrue()
        ->and($preference->email_on_comment)->toBeTrue()
        ->and($preference->email_on_reply)->toBeFalse();
});

it('valida os quatro toggles', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('dashboard.notifications.preferences'), [
            'email_on_like' => 'sim',
        ])
        ->assertSessionHasErrors([
            'email_on_like',
            'email_on_follow',
            'email_on_comment',
            'email_on_reply',
        ]);

    expect(ColorNotificationPreference::query()->count())->toBe(0);
});

it('marca uma notificacao como lida sem tocar nas outras', function () {
    $user = User::factory()->create();
    $first = notificationFor($user);
    notificationFor($user);

    $this->actingAs($user)
        ->post(route('dashboard.notifications.read', $first))
        ->assertRedirect();

    expect($user->notifications()->find($first)?->read_at)->not->toBeNull()
        ->and($user->unreadNotifications()->count())->toBe(1);
});

it('nao deixa marcar notificacao de outra conta', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $foreign = notificationFor($other);

    $this->actingAs($user)
        ->post(route('dashboard.notifications.read', $foreign))
        ->assertNotFound();

    expect($other->unreadNotifications()->count())->toBe(1);
});

it('marca todas as proprias notificacoes como lidas', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    notificationFor($user);
    notificationFor($user);
    notificationFor($other);

    $this->actingAs($user)
        ->post(route('dashboard.notifications.read-all'))
        ->assertRedirect();

    expect($user->unreadNotifications()->count())->toBe(0)
        ->and($other->unreadNotifications()->count())->toBe(1);
});
