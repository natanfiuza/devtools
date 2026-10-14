<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
| "Definir senha": a conta criada por login social nasce sem senha e ganha a
| primeira em /{locale}/user/password/set, dentro do fluxo de confirmar senha
| do Fortify (ADR-023).
*/

function novaSenha(): string
{
    return 'senha-bem-longa-de-teste';
}

function urlDefinirSenha(): string
{
    return route('password.set', ['locale' => 'en']);
}

it('exige autenticacao para definir a senha', function () {
    $this->post(urlDefinirSenha(), [
        'password' => novaSenha(),
        'password_confirmation' => novaSenha(),
    ])->assertRedirect(route('login'));

    $this->assertGuest();
});

it('recusa quem ja tem senha', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(urlDefinirSenha(), [
            'password' => novaSenha(),
            'password_confirmation' => novaSenha(),
        ])
        ->assertForbidden();

    expect(Hash::check('password', $user->refresh()->password))->toBeTrue();
});

it('define a senha da conta criada por login social', function () {
    $user = User::factory()->create(['password' => null]);

    expect($user->hasPassword())->toBeFalse();

    $this->actingAs($user)
        ->post(urlDefinirSenha(), [
            'password' => novaSenha(),
            'password_confirmation' => novaSenha(),
        ])
        ->assertRedirect(route('security.edit'));

    $user->refresh();

    expect($user->hasPassword())->toBeTrue()
        ->and(Hash::check(novaSenha(), $user->password))->toBeTrue()
        // A area sensivel ja esta liberada: a senha acabou de ser definida aqui.
        ->and(session('auth.password_confirmed_at'))->not->toBeNull();
});

it('libera a area sensivel na mesma sessao', function () {
    $user = User::factory()->create(['password' => null]);

    $this->actingAs($user)
        ->post(urlDefinirSenha(), [
            'password' => novaSenha(),
            'password_confirmation' => novaSenha(),
        ]);

    $this->actingAs($user->refresh())
        ->get(route('security.edit'))
        ->assertOk();
});

it('valida a senha nova', function () {
    $user = User::factory()->create(['password' => null]);

    $this->actingAs($user)
        ->post(urlDefinirSenha(), [
            'password' => novaSenha(),
            'password_confirmation' => 'outra-senha-qualquer',
        ])
        ->assertSessionHasErrors('password');

    expect($user->refresh()->hasPassword())->toBeFalse();
});

it('recusa quem ainda nao confirmou o e-mail', function () {
    $user = User::factory()->unverified()->create(['password' => null]);

    $this->actingAs($user)
        ->post(urlDefinirSenha(), [
            'password' => novaSenha(),
            'password_confirmation' => novaSenha(),
        ])
        ->assertRedirect(route('verification.notice'));

    expect($user->refresh()->hasPassword())->toBeFalse();
});

it('troca a tela de confirmar senha pela de definir senha', function () {
    $comSenha = User::factory()->create();
    $semSenha = User::factory()->create(['password' => null]);

    $this->actingAs($comSenha)
        ->get(route('password.confirm'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/ConfirmPassword')
            ->where('hasPassword', true)
        );

    $this->actingAs($semSenha)
        ->get(route('password.confirm'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/ConfirmPassword')
            ->where('hasPassword', false)
        );
});
