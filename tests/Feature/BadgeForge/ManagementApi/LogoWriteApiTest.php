<?php

use App\Models\BadgeCustomLogo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

/*
| Escrita de logos pela API de gestao (F8, ADR-088).
|
| `badgeforge:write` abre POST/DELETE; `badgeforge:read` nao escreve nada.
| O SVG cru nunca e gravado nem devolvido, e logo de outra conta responde 404.
*/

function writeApiToken(User $user, array $abilities = ['badgeforge:write']): string
{
    return $user->createToken('management-write', $abilities)->plainTextToken;
}

function writeApiLogoUpload(string $svg = ''): UploadedFile
{
    $svg = $svg !== ''
        ? $svg
        : '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 2L2 22h20z"/></svg>';

    return UploadedFile::fake()->createWithContent('logo.svg', $svg);
}

it('envia um logo por multipart e devolve só metadados', function () {
    $user = User::factory()->create();

    $response = $this->withToken(writeApiToken($user))
        ->post(route('api.v1.badgeforge.logos.store'), [
            'name' => 'Marca API',
            'logo' => writeApiLogoUpload(),
        ])
        ->assertCreated();

    $payload = $response->json('data');

    expect($payload)->toHaveKeys(['id', 'slug', 'name', 'viewBox', 'pathCount', 'sizeBytes', 'createdAt'])
        ->and($payload['name'])->toBe('Marca API')
        ->and($payload['viewBox'])->toBe('0 0 24 24')
        ->and($payload['pathCount'])->toBe(1)
        ->and($payload['slug'])->toBe('u_'.$payload['id'])
        ->and((string) $response->getContent())->not->toContain('<svg')
        ->and((string) $response->getContent())->not->toContain('"paths"');

    $logo = BadgeCustomLogo::query()->sole();

    expect((int) $logo->user_id)->toBe($user->id)
        ->and($logo->paths)->toBe([['d' => 'M12 2L2 22h20z']]);
});

it('responde 422 no envelope para SVG fora da allowlist', function () {
    $user = User::factory()->create();

    $this->withToken(writeApiToken($user))
        ->post(route('api.v1.badgeforge.logos.store'), [
            'name' => 'Ruim',
            'logo' => writeApiLogoUpload('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"><script>alert(1)</script></svg>'),
        ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonPath('error.details.errors.logo.0', __('validation.custom_logo.unsupported_element'));

    expect(BadgeCustomLogo::query()->count())->toBe(0);
});

it('limita o plano Free a dois logos também pela API', function () {
    $user = User::factory()->create();

    BadgeCustomLogo::factory()->count(2)->for($user)->sequence(
        static fn ($sequence): array => ['name' => 'logo-'.$sequence->index],
    )->create();

    $this->withToken(writeApiToken($user))
        ->post(route('api.v1.badgeforge.logos.store'), [
            'name' => 'terceiro',
            'logo' => writeApiLogoUpload(),
        ])
        ->assertStatus(422)
        ->assertJsonPath('error.details.errors.logo.0', __('validation.custom_logo.limit_reached'));

    expect($user->badgeCustomLogos()->count())->toBe(2);
});

it('exclui o próprio logo e responde 404 para o de outra conta', function () {
    $user = User::factory()->create();
    $logo = BadgeCustomLogo::factory()->for($user)->create();

    $this->withToken(writeApiToken($user))
        ->delete(route('api.v1.badgeforge.logos.destroy', ['logo' => $logo->id]))
        ->assertOk()
        ->assertExactJson(['data' => ['id' => (string) $logo->id, 'deleted' => true]]);

    expect(BadgeCustomLogo::query()->whereKey($logo->id)->exists())->toBeFalse();

    $foreign = BadgeCustomLogo::factory()->create();

    $this->withToken(writeApiToken($user))
        ->delete(route('api.v1.badgeforge.logos.destroy', ['logo' => $foreign->id]))
        ->assertStatus(404)
        ->assertJsonPath('error.code', 'not_found');

    expect(BadgeCustomLogo::query()->whereKey($foreign->id)->exists())->toBeTrue();
});

it('responde 404 para ULID inexistente', function () {
    $user = User::factory()->create();

    $this->withToken(writeApiToken($user))
        ->delete(route('api.v1.badgeforge.logos.destroy', ['logo' => '01JZZZZZZZZZZZZZZZZZZZZZZZ']))
        ->assertStatus(404)
        ->assertJsonPath('error.code', 'not_found');
});

it('recusa um token só de leitura na escrita', function () {
    $user = User::factory()->create();

    $this->withToken(writeApiToken($user, ['badgeforge:read']))
        ->post(route('api.v1.badgeforge.logos.store'), [
            'name' => 'Sem permissão',
            'logo' => writeApiLogoUpload(),
        ])
        ->assertStatus(403);

    expect(BadgeCustomLogo::query()->count())->toBe(0);
});
