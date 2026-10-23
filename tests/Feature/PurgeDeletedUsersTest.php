<?php

use App\Models\User;
use App\Notifications\AccountPurgedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

test('expurga contas em soft delete ha mais de 30 dias enviando email com exportacao json antes de anonimizar', function () {
    Notification::fake();

    $user = User::factory()->create([
        'name' => 'Usuario Teste',
        'username' => 'usuarioteste',
        'email' => 'teste@exemplo.com',
        'locale' => 'pt_BR',
        'deleted_at' => now()->subDays(31),
    ]);

    $this->artisan('accounts:purge-deleted', ['--days' => 30])
        ->expectsOutputToContain('Expurgando 1 conta(s)...')
        ->expectsOutputToContain('1 conta(s) expurgada(s) com sucesso.')
        ->assertSuccessful();

    // Notificacao enviada para o usuario
    Notification::assertSentTo($user, AccountPurgedNotification::class, function (AccountPurgedNotification $notification) use ($user) {
        $mail = $notification->toMail($user);

        expect($mail->rawAttachments)->toHaveCount(1);
        $attachment = $mail->rawAttachments[0];
        $json = json_decode($notification->jsonData, true);

        expect($notification->fileName)->toContain('account-export-usuarioteste-')
            ->and($notification->fileName)->toEndWith('.json')
            ->and($attachment['name'])->toBe($notification->fileName)
            ->and($attachment['options']['mime'])->toBe('application/json')
            ->and($json)->toBeArray()
            ->and($json['account']['email'])->toBe('teste@exemplo.com')
            ->and($json['account']['username'])->toBe('usuarioteste');

        return true;
    });

    $user->refresh();

    // Usuario devidamente anonimizado apos o envio da notificacao
    expect($user->anonymized_at)->not->toBeNull()
        ->and($user->name)->toBe('')
        ->and($user->username)->toBeNull()
        ->and($user->email)->toContain('@removido.invalid');
});

test('nao expurga nem notifica contas com menos de 30 dias de soft delete', function () {
    Notification::fake();

    $user = User::factory()->create([
        'name' => 'Usuario Recente',
        'email' => 'recente@exemplo.com',
        'deleted_at' => now()->subDays(29),
    ]);

    $this->artisan('accounts:purge-deleted', ['--days' => 30])
        ->expectsOutputToContain('Nenhuma conta elegivel para expurgo.')
        ->assertSuccessful();

    Notification::assertNothingSent();

    $user->refresh();
    expect($user->anonymized_at)->toBeNull()
        ->and($user->email)->toBe('recente@exemplo.com');
});

test('se o envio da notificacao falhar a conta nao e anonimizada', function () {
    $user = User::factory()->create([
        'name' => 'Usuario Falha',
        'email' => 'falha@exemplo.com',
        'deleted_at' => now()->subDays(35),
    ]);

    // Mock Notification facade para lancar excecao no send
    Notification::shouldReceive('send')
        ->once()
        ->andThrow(new RuntimeException('Falha no servico de e-mail'));

    $this->artisan('accounts:purge-deleted', ['--days' => 30])
        ->expectsOutputToContain('Erro ao expurgar usuario ID '.$user->getKey())
        ->expectsOutputToContain('0 conta(s) expurgada(s) com sucesso.')
        ->assertSuccessful();

    $user->refresh();

    // A conta continua preservada com seus dados para nova tentativa futura
    expect($user->anonymized_at)->toBeNull()
        ->and($user->email)->toBe('falha@exemplo.com')
        ->and($user->name)->toBe('Usuario Falha');
});
