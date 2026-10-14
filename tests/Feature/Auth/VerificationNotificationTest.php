<?php

use App\Models\User;
use App\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;

uses(RefreshDatabase::class);

/*
| App\Notifications\VerifyEmail no lugar da notificacao do framework, para o
| e-mail sair de lang/{locale}/mail.php. Notification::assertSentTo casa pelo
| nome exato da classe, entao o import tem que ser o da notificacao enviada.
*/

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::emailVerification());
});

it('sends the verification notification', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->post(route('verification.send'))
        ->assertRedirect(route('root'));

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('does not send the verification notification if the email is already verified', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('verification.send'))
        ->assertRedirect(route('dashboard', absolute: false));

    Notification::assertNothingSent();
});
