<?php

use App\Enums\NewsletterSource;
use App\Mail\NewsletterConfirmation;
use App\Models\NewsletterSubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
| Newsletter com double opt-in (ADR-024).
|
| O POST do rodape inscreve e manda o e-mail; a inscricao so vale depois do POST
| da pagina do link. O GET dessa pagina nao confirma nada - e o que impede o
| scanner do provedor de e-mail (ou o prefetch do navegador) de inscrever
| alguem sozinho.
*/

/**
 * @param  array<string, mixed>  $estado
 */
function inscrito(string $email, array $estado = []): NewsletterSubscriber
{
    $subscriber = new NewsletterSubscriber([
        'email' => $email,
        'token' => NewsletterSubscriber::newToken(),
        'locale' => 'en',
        'source' => NewsletterSource::Footer->value,
    ]);

    $subscriber->forceFill($estado)->save();

    return $subscriber;
}

it('inscreve pelo rodape e manda o e-mail de confirmacao', function () {
    Mail::fake();

    $this->from('/pt-br')
        ->post('/pt-br/newsletter', ['email' => ' Leitor@Exemplo.com '])
        ->assertRedirect('/pt-br');

    $subscriber = NewsletterSubscriber::query()->sole();

    expect($subscriber->email)->toBe('leitor@exemplo.com')
        ->and($subscriber->locale)->toBe('pt_BR')
        ->and($subscriber->source)->toBe(NewsletterSource::Footer->value)
        ->and($subscriber->token)->toHaveLength(64)
        ->and($subscriber->isConfirmed())->toBeFalse();

    Mail::assertQueued(NewsletterConfirmation::class, fn (NewsletterConfirmation $mail) => $mail->hasTo('leitor@exemplo.com')
        && $mail->locale === 'pt_BR');

    // A resposta sai como toast, nunca inline: so aparece na proxima pagina.
    $this->get('/pt-br')->assertInertia(fn (Assert $page) => $page
        ->hasFlash('toast.type', 'success')
    );
});

it('valida o e-mail', function () {
    Mail::fake();

    $this->from('/pt-br')
        ->post('/pt-br/newsletter', ['email' => 'nao-e-email'])
        ->assertSessionHasErrors('email');

    Mail::assertNothingQueued();
    expect(NewsletterSubscriber::query()->count())->toBe(0);
});

it('nao manda e-mail novo para quem ja confirmou', function () {
    Mail::fake();

    inscrito('leitor@exemplo.com', ['confirmed_at' => now()]);

    $this->from('/pt-br')
        ->post('/pt-br/newsletter', ['email' => 'leitor@exemplo.com'])
        ->assertRedirect('/pt-br');

    Mail::assertNothingQueued();

    $this->get('/pt-br')->assertInertia(fn (Assert $page) => $page
        ->hasFlash('toast.type', 'info')
    );
});

it('reenvia o e-mail e troca o token de quem esta pendente', function () {
    Mail::fake();

    $subscriber = inscrito('leitor@exemplo.com');
    $antes = $subscriber->token;

    $this->from('/pt-br')->post('/pt-br/newsletter', ['email' => 'leitor@exemplo.com']);

    expect($subscriber->refresh()->token)->not->toBe($antes);
    Mail::assertQueued(NewsletterConfirmation::class);
});

it('aceita de volta quem se descadastrou', function () {
    Mail::fake();

    $subscriber = inscrito('leitor@exemplo.com', [
        'confirmed_at' => now(),
        'unsubscribed_at' => now(),
    ]);

    $this->from('/pt-br')->post('/pt-br/newsletter', ['email' => 'leitor@exemplo.com']);

    expect($subscriber->refresh()->unsubscribed_at)->toBeNull()
        ->and($subscriber->isConfirmed())->toBeFalse();

    Mail::assertQueued(NewsletterConfirmation::class);
});

it('limita as inscricoes por minuto', function () {
    Mail::fake();

    foreach (range(1, 10) as $indice) {
        $this->post('/pt-br/newsletter', ['email' => "leitor{$indice}@exemplo.com"])
            ->assertRedirect();
    }

    $this->post('/pt-br/newsletter', ['email' => 'excedente@exemplo.com'])
        ->assertTooManyRequests();
});

it('mostra a pagina de confirmacao sem confirmar ninguem', function () {
    $subscriber = inscrito('leitor@exemplo.com');

    $this->get('/pt-br/newsletter/confirm/'.$subscriber->token)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('newsletter/Confirm')
            ->where('token', $subscriber->token)
            ->where('state', 'pending')
        );

    expect($subscriber->refresh()->isConfirmed())->toBeFalse();
});

it('mostra o estado de cada token de confirmacao', function () {
    $confirmado = inscrito('confirmado@exemplo.com', ['confirmed_at' => now()]);

    $this->get('/pt-br/newsletter/confirm/token-que-nao-existe')
        ->assertInertia(fn (Assert $page) => $page->where('state', 'invalid'));

    $this->get('/pt-br/newsletter/confirm/'.$confirmado->token)
        ->assertInertia(fn (Assert $page) => $page->where('state', 'confirmed'));
});

it('confirma a inscricao pelo post do link', function () {
    $subscriber = inscrito('leitor@exemplo.com');

    $this->post('/pt-br/newsletter/confirm/'.$subscriber->token)
        ->assertRedirect(route('newsletter.confirm', [
            'locale' => 'pt-br',
            'token' => $subscriber->token,
        ]));

    expect($subscriber->refresh()->isConfirmed())->toBeTrue();
});

it('volta para a home com aviso quando o token de confirmacao nao existe', function () {
    $this->post('/pt-br/newsletter/confirm/token-que-nao-existe')
        ->assertRedirect('/pt-br');

    $this->get('/pt-br')->assertInertia(fn (Assert $page) => $page
        ->hasFlash('toast.type', 'error')
    );
});

it('mostra a pagina de descadastro sem descadastrar ninguem', function () {
    $subscriber = inscrito('leitor@exemplo.com', ['confirmed_at' => now()]);

    $this->get('/pt-br/newsletter/unsubscribe/'.$subscriber->token)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('newsletter/Unsubscribe')
            ->where('state', 'pending')
        );

    expect($subscriber->refresh()->unsubscribed_at)->toBeNull();
});

it('descadastra pelo post do link', function () {
    $subscriber = inscrito('leitor@exemplo.com', ['confirmed_at' => now()]);

    $this->post('/pt-br/newsletter/unsubscribe/'.$subscriber->token)
        ->assertRedirect(route('newsletter.unsubscribe', [
            'locale' => 'pt-br',
            'token' => $subscriber->token,
        ]));

    $subscriber->refresh();

    expect($subscriber->unsubscribed_at)->not->toBeNull()
        ->and($subscriber->isConfirmed())->toBeFalse();
});

it('mostra o estado de cada token de descadastro', function () {
    $descadastrado = inscrito('leitor@exemplo.com', [
        'confirmed_at' => now(),
        'unsubscribed_at' => now(),
    ]);

    $this->get('/pt-br/newsletter/unsubscribe/token-que-nao-existe')
        ->assertInertia(fn (Assert $page) => $page->where('state', 'invalid'));

    $this->get('/pt-br/newsletter/unsubscribe/'.$descadastrado->token)
        ->assertInertia(fn (Assert $page) => $page->where('state', 'unsubscribed'));
});

it('volta para a home com aviso quando o token de descadastro nao existe', function () {
    $this->post('/pt-br/newsletter/unsubscribe/token-que-nao-existe')
        ->assertRedirect('/pt-br');

    $this->get('/pt-br')->assertInertia(fn (Assert $page) => $page
        ->hasFlash('toast.type', 'error')
    );
});

it('leva o idioma do inscrito para o link e para o e-mail', function () {
    Mail::fake();

    $this->post('/fr/newsletter', ['email' => 'lecteur@exemple.com']);

    Mail::assertQueued(NewsletterConfirmation::class, function (NewsletterConfirmation $mail) {
        $url = $mail->content()->with['confirmUrl'] ?? '';

        return $mail->locale === 'fr'
            && is_string($url)
            && str_contains($url, '/fr/newsletter/confirm/');
    });
});

it('inscreve pelo registro quando o checkbox vem marcado', function () {
    Mail::fake();

    $this->post('/pt-br/register', [
        'name' => 'Leitora',
        'email' => 'leitora@exemplo.com',
        'password' => 'senha-bem-longa-de-teste',
        'password_confirmation' => 'senha-bem-longa-de-teste',
        'newsletter' => '1',
    ])->assertRedirect(route('dashboard'));

    $subscriber = NewsletterSubscriber::query()->sole();

    expect($subscriber->email)->toBe('leitora@exemplo.com')
        ->and($subscriber->source)->toBe(NewsletterSource::Register->value)
        ->and($subscriber->locale)->toBe('pt_BR')
        ->and($subscriber->isConfirmed())->toBeFalse();

    Mail::assertQueued(NewsletterConfirmation::class);
});

it('nao inscreve pelo registro quando o checkbox vem desmarcado', function () {
    Mail::fake();

    $this->post('/pt-br/register', [
        'name' => 'Leitora',
        'email' => 'leitora@exemplo.com',
        'password' => 'senha-bem-longa-de-teste',
        'password_confirmation' => 'senha-bem-longa-de-teste',
    ]);

    expect(NewsletterSubscriber::query()->count())->toBe(0);

    Mail::assertNothingQueued();
});
