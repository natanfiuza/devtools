<?php

use App\Models\User;
use App\Rules\ValidTurnstile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Fortify\Features;

uses(RefreshDatabase::class);

/*
| Turnstile no cadastro (ADR-052).
|
| Com as chaves configuradas, nenhum cadastro passa sem um token valido na
| Cloudflare. A verificacao falha fechada: token ausente, resposta negativa,
| erro do servico ou rede fora caem na mesma mensagem generica. Sem chave, o
| recurso esta desligado de proposito e o cadastro segue como antes - o widget
| nem aparece na tela.
*/

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());

    Http::preventStrayRequests();

    config([
        'services.turnstile.site_key' => 'site-key',
        'services.turnstile.secret_key' => 'secret-key',
        'services.turnstile.verify_url' => 'https://turnstile.test/siteverify',
        'services.turnstile.timeout' => 5,
    ]);
});

/** @param array<string, mixed> $overrides */
function turnstilePayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ], $overrides);
}

it('aceita o cadastro com um token valido e confere o segredo no siteverify', function () {
    Http::fake(['https://turnstile.test/siteverify' => Http::response(['success' => true])]);

    $this->post(route('register.store'), turnstilePayload(['turnstile' => 'token-abc']));

    $this->assertAuthenticated();

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://turnstile.test/siteverify'
        && $request['secret'] === 'secret-key'
        && $request['response'] === 'token-abc'
        && $request['remoteip'] === '127.0.0.1');
});

it('recusa o cadastro sem token, sem chamar o siteverify', function () {
    $response = $this->post(route('register.store'), turnstilePayload());

    $response->assertSessionHasErrors(['turnstile' => __('auth.turnstile.failed')]);

    $this->assertGuest();
    expect(User::query()->count())->toBe(0);

    Http::assertNothingSent();
});

it('recusa um token que a Cloudflare reprova', function () {
    Http::fake([
        'https://turnstile.test/siteverify' => Http::response([
            'success' => false,
            'error-codes' => ['invalid-input-response'],
        ]),
    ]);

    $response = $this->post(route('register.store'), turnstilePayload(['turnstile' => 'invalido']));

    $response->assertSessionHasErrors(['turnstile' => __('auth.turnstile.failed')]);

    $this->assertGuest();
});

it('falha fechado quando o siteverify responde erro', function () {
    Http::fake(['https://turnstile.test/siteverify' => Http::response('upstream', 500)]);

    $response = $this->post(route('register.store'), turnstilePayload(['turnstile' => 'token-abc']));

    $response->assertSessionHasErrors(['turnstile' => __('auth.turnstile.failed')]);

    $this->assertGuest();
});

it('falha fechado quando o siteverify nao responde', function () {
    Http::fake(fn () => throw new ConnectionException('timeout'));

    $response = $this->post(route('register.store'), turnstilePayload(['turnstile' => 'token-abc']));

    $response->assertSessionHasErrors(['turnstile' => __('auth.turnstile.failed')]);

    $this->assertGuest();
});

it('deixa o cadastro passar sem token quando o Turnstile esta desligado', function () {
    config(['services.turnstile.secret_key' => null]);

    expect(ValidTurnstile::enabled())->toBeFalse();

    $this->post(route('register.store'), turnstilePayload());

    $this->assertAuthenticated();

    Http::assertNothingSent();
});
