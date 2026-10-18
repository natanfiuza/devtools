<?php

use App\Http\Controllers\ProfilePhotoController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

afterEach(function () {
    $path = ProfilePhotoController::storagePath();
    if (File::isDirectory($path)) {
        foreach (File::files($path) as $file) {
            File::delete($file->getPathname());
        }
    }
});

it('permite o usuario enviar uma foto de perfil e salva com uuid', function () {
    $user = User::factory()->create();

    $file = UploadedFile::fake()->image('avatar.jpg', 400, 400);

    $response = $this->actingAs($user)
        ->post('/settings/profile/photo', [
            'photo' => $file,
        ]);

    $response->assertSessionHasNoErrors();
    $user->refresh();

    expect($user->photo_uuid)->not->toBeNull()
        ->and($user->avatar)->toContain('/users/profile/photo/'.$user->photo_uuid);

    $filePath = ProfilePhotoController::storagePath($user->photo_uuid);
    expect(File::exists($filePath))->toBeTrue();
});

it('serve publicamente a foto de perfil pela rota publica sem autenticacao', function () {
    $user = User::factory()->create();

    $file = UploadedFile::fake()->image('avatar.png', 200, 200);

    $this->actingAs($user)
        ->post('/settings/profile/photo', [
            'photo' => $file,
        ]);

    $user->refresh();

    // Acesso sem login (guest)
    $this->app['auth']->logout();

    $response = $this->get('/users/profile/photo/'.$user->photo_uuid);

    $response->assertOk()
        ->assertHeader('Content-Type', 'image/png');
});

it('retorna 404 se a foto com o uuid nao existir', function () {
    $response = $this->get('/users/profile/photo/00000000-0000-0000-0000-000000000000');
    $response->assertNotFound();
});

it('permite ao usuario remover a foto de perfil e apaga do disco', function () {
    $user = User::factory()->create();

    $file = UploadedFile::fake()->image('avatar.jpg', 300, 300);

    $this->actingAs($user)
        ->post('/settings/profile/photo', [
            'photo' => $file,
        ]);

    $user->refresh();
    $photoUuid = $user->photo_uuid;
    $filePath = ProfilePhotoController::storagePath($photoUuid);

    expect(File::exists($filePath))->toBeTrue();

    $response = $this->actingAs($user)
        ->delete('/settings/profile/photo');

    $response->assertSessionHasNoErrors();
    $user->refresh();

    expect($user->photo_uuid)->toBeNull();
    expect(File::exists($filePath))->toBeFalse();
});

it('recusa arquivo que nao seja imagem valida', function () {
    $user = User::factory()->create();

    $file = UploadedFile::fake()->create('documento.pdf', 500, 'application/pdf');

    $response = $this->actingAs($user)
        ->post('/settings/profile/photo', [
            'photo' => $file,
        ]);

    $response->assertSessionHasErrors('photo');
});
