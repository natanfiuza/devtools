<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

it('renders the login screen', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
});

it('authenticates users using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

it('does not authenticate users with an invalid password', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

it('logs users out', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    // A home agora e prefixada por idioma; o logout volta para a raiz, que
    // resolve o idioma do visitante (ver tests/Feature/LocaleRoutingTest.php).
    $response->assertRedirect(route('root'));

    $this->assertGuest();
});

it('rate limits login attempts', function () {
    $user = User::factory()->create();

    RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertTooManyRequests();
});
