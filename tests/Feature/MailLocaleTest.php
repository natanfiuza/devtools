<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Mime\Email;

uses(RefreshDatabase::class);

/*
| E-mails de conta (reset de senha e confirmacao de endereco) saem no idioma da
| conta, nao no idioma de quem pediu - e o link leva esse idioma na URL, senao a
| pagina aberta pelo e-mail sairia no idioma errado (ADR-022).
|
| O phpunit manda os e-mails para o transporte "array": da para ler a mensagem
| ja renderizada (assunto e corpo), com o link que o usuario vai clicar.
*/

function mensagemEnviada(): Email
{
    $messages = app('mailer')->getSymfonyTransport()->messages();

    expect($messages)->toHaveCount(1);

    return $messages->last()->getOriginalMessage();
}

function corpoEnviado(): string
{
    $message = mensagemEnviada();

    return (string) $message->getHtmlBody().(string) $message->getTextBody();
}

it('manda o reset de senha no idioma da conta', function () {
    $user = User::factory()->create(['locale' => 'fr']);

    $this->post('/en/forgot-password', ['email' => $user->email]);

    $corpo = corpoEnviado();
    $actionText = trans('mail.reset.action', [], 'fr');

    expect(mensagemEnviada()->getSubject())->toBe(trans('mail.reset.subject', [], 'fr'))
        ->and($corpo)->toContain('/fr/reset-password/')
        ->and($corpo)->not->toContain('/en/reset-password/')
        ->and($corpo)->toContain(trans('mail.template.greeting', [], 'fr'))
        ->and($corpo)->toContain(trans('mail.template.salutation', [], 'fr'))
        ->and($corpo)->toContain(trans('mail.template.subcopy', ['actionText' => $actionText], 'fr'))
        ->and($corpo)->toContain('color: #0f7f8a;">_</span>');
});

it('manda a confirmacao de endereco no idioma da conta', function () {
    $user = User::factory()->unverified()->create(['locale' => 'pt_PT']);

    $user->sendEmailVerificationNotification();

    $corpo = corpoEnviado();
    $actionText = trans('mail.verify.action', [], 'pt_PT');

    expect(mensagemEnviada()->getSubject())->toBe(trans('mail.verify.subject', [], 'pt_PT'))
        ->and($corpo)->toContain('/pt-pt/email/verify/'.$user->getKey().'/')
        ->and($corpo)->toContain(trans('mail.template.greeting', [], 'pt_PT'))
        ->and($corpo)->toContain(trans('mail.template.salutation', [], 'pt_PT'))
        ->and($corpo)->toContain(trans('mail.template.subcopy', ['actionText' => $actionText], 'pt_PT'))
        ->and($corpo)->not->toContain("If you're having trouble clicking")
        ->and($corpo)->toContain('color: #0f7f8a;">_</span>');
});

it('usa o fallback do app quando a conta nao tem idioma', function () {
    $user = User::factory()->create(['locale' => null]);

    $this->post('/es/forgot-password', ['email' => $user->email]);

    // Assunto e link no mesmo idioma - o da conta ou, sem ele, o fallback.
    expect(mensagemEnviada()->getSubject())->toBe(trans('mail.reset.subject', [], 'en'))
        ->and(corpoEnviado())->toContain('/en/reset-password/');
});

it('nao manda o reset para um e-mail sem conta', function () {
    $this->post('/en/forgot-password', ['email' => 'ninguem@exemplo.com']);

    expect(app('mailer')->getSymfonyTransport()->messages())->toBeEmpty();
});
