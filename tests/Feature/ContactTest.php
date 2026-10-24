<?php

use App\Mail\ContactMessageReceived;
use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('renderiza a pagina de contato nos idiomas suportados', function () {
    $this->get('/pt-br/contact')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Contact')
            ->where('name', null)
            ->where('email', null)
        );

    $this->get('/en/contact')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Contact')
        );
});

it('preenche nome e email do usuario autenticado', function () {
    $user = User::factory()->create([
        'name' => 'Natan Fiuza',
        'email' => 'natan@exemplo.com',
    ]);

    $this->actingAs($user)
        ->get('/pt-br/contact')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Contact')
            ->where('name', 'Natan Fiuza')
            ->where('email', 'natan@exemplo.com')
        );
});

it('envia mensagem de contato com sucesso, salva no banco e agenda email na fila', function () {
    Mail::fake();

    config(['contact.email' => 'devtools@natanfiuza.dev.br']);

    $payload = [
        'name' => 'Maria Silva',
        'email' => 'maria@exemplo.com',
        'subject' => 'general',
        'message' => 'Gostaria de tirar uma duvida sobre a ferramenta de JSON.',
    ];

    $this->post('/pt-br/contact', $payload)
        ->assertRedirect('/pt-br/contact')
        ->assertSessionHas('status', 'sent');

    $contact = ContactMessage::query()->sole();

    expect($contact->name)->toBe('Maria Silva')
        ->and($contact->email)->toBe('maria@exemplo.com')
        ->and($contact->subject)->toBe('Dúvida geral')
        ->and($contact->message)->toBe('Gostaria de tirar uma duvida sobre a ferramenta de JSON.')
        ->and($contact->user_id)->toBeNull();

    Mail::assertQueued(ContactMessageReceived::class, function (ContactMessageReceived $mail) use ($contact) {
        return $mail->hasTo('devtools@natanfiuza.dev.br')
            && $mail->contactMessage->id === $contact->id
            && $mail->hasReplyTo('maria@exemplo.com', 'Maria Silva');
    });

    // Confere o toast na resposta
    $this->get('/pt-br/contact')->assertInertia(fn (Assert $page) => $page
        ->hasFlash('toast.type', 'success')
    );
});

it('valida campos obrigatorios do formulario de contato', function () {
    Mail::fake();

    $this->post('/pt-br/contact', [])
        ->assertSessionHasErrors(['name', 'email', 'subject', 'message']);

    expect(ContactMessage::count())->toBe(0);
    Mail::assertNothingQueued();
});

it('descarta envio silenciosamente quando honeypot e preenchido por bot', function () {
    Mail::fake();

    $payload = [
        'name' => 'Spam Bot',
        'email' => 'spambot@spam.com',
        'subject' => 'other',
        'message' => 'Compre agora remedios baratos.',
        'hp_website' => 'https://spam-site.com',
    ];

    $this->post('/pt-br/contact', $payload)
        ->assertRedirect('/pt-br/contact')
        ->assertSessionHas('status', 'sent');

    expect(ContactMessage::count())->toBe(0);
    Mail::assertNothingQueued();
});

it('bloqueia envio com rate limit amigavel apos 5 tentativas', function () {
    RateLimiter::clear('contact_form:127.0.0.1');

    $payload = [
        'name' => 'Usuario Teste',
        'email' => 'teste@exemplo.com',
        'subject' => 'feedback',
        'message' => 'Mensagem de teste.',
    ];

    for ($i = 0; $i < 5; $i++) {
        $this->post('/pt-br/contact', $payload)
            ->assertRedirect('/pt-br/contact');
    }

    // 6ª tentativa deve estourar o rate limit com erro amigavel
    $this->post('/pt-br/contact', $payload)
        ->assertSessionHasErrors(['message']);
});
