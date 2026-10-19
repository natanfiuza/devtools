<?php

use App\Models\User;
use App\Support\SocialProviders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

uses(RefreshDatabase::class);

/*
| Login social (Google e GitHub), ver ADR-023.
|
| As rotas sao de maquina e ficam fora do prefixo de idioma (ADR-022): o
| callback e cadastrado uma vez no console do provedor, e o idioma do visitante
| viaja na sessao.
*/

/**
 * Chaves de mentira: o controller so fala com o Socialite quando o provedor
 * esta configurado (SocialProviders::isConfigured).
 *
 * @param  array<string, mixed>|Closure  $user  atributos do usuario do provedor,
 *                                              ou uma closure quando o caso e de falha
 */
function fakeProvider(string $provider, array|Closure $user = []): void
{
    config()->set("services.{$provider}.client_id", 'id-de-teste');
    config()->set("services.{$provider}.client_secret", 'segredo-de-teste');

    Socialite::fake($provider, $user instanceof Closure ? $user : SocialiteUser::fake($user));
}

function callbackUrl(string $provider): string
{
    return route('auth.callback', ['provider' => $provider]);
}

it('manda o visitante para o provedor configurado', function () {
    fakeProvider('google');

    $this->get(route('auth.redirect', ['provider' => 'google']))
        ->assertRedirect('https://socialite.fake/google/authorize');
});

it('volta ao login com aviso quando o provedor nao esta configurado', function () {
    config()->set('services.github.client_id', null);
    config()->set('services.github.client_secret', null);

    // O botao continua na tela (decisao do grill): o clique avisa, nao estoura.
    $this->get(route('auth.redirect', ['provider' => 'github']))
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', trans('auth.social.unavailable', [
            'provider' => SocialProviders::label('github'),
        ]));
});

it('avisa no idioma do visitante quando o provedor nao esta configurado', function () {
    config()->set('services.google.client_id', null);

    $this->withHeader('Accept-Language', 'pt-BR,pt;q=0.9')
        ->get(route('auth.redirect', ['provider' => 'google']))
        ->assertRedirect('/pt-br/login')
        ->assertSessionHas('status', trans('auth.social.unavailable', [
            'provider' => SocialProviders::label('google'),
        ], 'pt_BR'));
});

it('nao atende provedor fora da lista', function () {
    $this->get('/auth/facebook/redirect')->assertNotFound();
    $this->get('/auth/facebook/callback')->assertNotFound();
});

it('cria a conta sem senha no primeiro acesso', function () {
    fakeProvider('google', ['email_verified' => true]);

    $this->get(callbackUrl('google'))->assertRedirect(route('dashboard'));

    $user = User::query()->where('email', 'test@example.com')->firstOrFail();

    expect($user->name)->toBe('Test User')
        ->and($user->password)->toBeNull()
        ->and($user->hasPassword())->toBeFalse()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->socialAccounts()->count())->toBe(1);

    $this->assertAuthenticatedAs($user);
});

it('guarda o idioma negociado na conta nova', function () {
    fakeProvider('github');

    $this->withHeader('Accept-Language', 'fr-FR,fr;q=0.9')
        ->get(callbackUrl('github'))
        ->assertRedirect(route('dashboard'));

    expect(User::query()->where('email', 'test@example.com')->firstOrFail()->locale)
        ->toBe('fr');
});

it('entra direto na conta ja vinculada', function () {
    $user = User::factory()->create(['email' => 'outro@example.com']);
    $user->socialAccounts()->create([
        'provider' => 'github',
        'provider_id' => '123456789',
    ]);

    fakeProvider('github');

    $this->get(callbackUrl('github'))->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
    expect($user->socialAccounts()->count())->toBe(1);
});

it('vincula a conta existente quando o provedor confirma o e-mail', function () {
    fakeProvider('google', ['email_verified' => true]);
    $user = User::factory()->unverified()->create(['email' => 'test@example.com']);

    $this->get(callbackUrl('google'))->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
    expect($user->socialAccounts()->count())->toBe(1)
        ->and($user->refresh()->email_verified_at)->not->toBeNull();
});

it('nao vincula a conta existente quando o provedor nao confirma o e-mail', function () {
    fakeProvider('google', ['email_verified' => false]);
    $user = User::factory()->create(['email' => 'test@example.com']);

    $this->get(callbackUrl('google'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', trans('auth.social.email_unverified', [
            'provider' => SocialProviders::label('google'),
        ]));

    $this->assertGuest();
    expect($user->socialAccounts()->count())->toBe(0);
});

it('nao cria conta pelo github quando o provedor nao devolve e-mail', function () {
    fakeProvider('github', ['email' => null]);

    $this->get(callbackUrl('github'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', trans('auth.social.email_missing', [
            'provider' => SocialProviders::label('github'),
        ]));

    $this->assertGuest();
    expect(User::query()->count())->toBe(0);
});

it('nao verifica o e-mail que o provedor nao confirma', function () {
    fakeProvider('google', ['email_verified' => false]);

    $this->get(callbackUrl('google'))->assertRedirect(route('dashboard'));

    expect(User::query()->firstOrFail()->email_verified_at)->toBeNull();
});

it('volta ao login quando o provedor recusa a autorizacao', function () {
    fakeProvider('github', fn () => throw new InvalidArgumentException('acesso negado'));

    $this->get(callbackUrl('github'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', trans('auth.social.failed', [
            'provider' => SocialProviders::label('github'),
        ]));

    $this->assertGuest();
});

it('volta ao cadastro com aviso quando iniciado pelo cadastro e o provedor nao esta configurado', function () {
    config()->set('services.google.client_id', null);
    config()->set('services.google.client_secret', null);

    $this->get(route('auth.redirect', ['provider' => 'google', 'from' => 'register']))
        ->assertRedirect(route('register'))
        ->assertSessionHas('status', trans('auth.social.unavailable', [
            'provider' => SocialProviders::label('google'),
        ]));
});

it('volta ao cadastro quando o provedor recusa a autorizacao originada no cadastro', function () {
    fakeProvider('google', fn () => throw new InvalidArgumentException('acesso negado'));

    $this->withSession(['auth.social.from' => 'register'])
        ->get(callbackUrl('google'))
        ->assertRedirect(route('register'))
        ->assertSessionHas('status', trans('auth.social.failed', [
            'provider' => SocialProviders::label('google'),
        ]));

    $this->assertGuest();
});
