<?php

use App\Models\BadgeCustomLogo;
use App\Models\BadgePreset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
| Presets da conta (ADR-079).
|
| O preset guarda um snapshot normalizado do formulario do builder: aplicar
| tem de reproduzir o badge que a URL geraria. O logo custom so pode ser do
| proprio dono e um logo orfao nunca e recusado (a leitura marca).
*/

function badgePresetForm(array $overrides = []): array
{
    return array_merge([
        'label' => 'build',
        'message' => 'passing',
        'style' => 'flat',
        'font' => '',
        'color' => '',
        'labelColor' => '',
        'logo' => 'none',
        'logoColor' => '',
        'logoSize' => '',
        'logoPosition' => 'left',
        'link' => '',
        'cacheSeconds' => '',
    ], $overrides);
}

it('protege as rotas de preset', function () {
    $preset = BadgePreset::factory()->create();
    $unverified = User::factory()->unverified()->create();
    $suspended = User::factory()->create(['suspended_at' => now()]);

    $this->post(route('badgeforge.presets.store'))->assertRedirect(route('login'));

    $this->actingAs($unverified)
        ->post(route('badgeforge.presets.store'))
        ->assertRedirect(route('verification.notice'));

    $this->actingAs($suspended)
        ->delete(route('badgeforge.presets.destroy', ['badgePreset' => $preset->id]))
        ->assertRedirect(route('login'));
});

it('grava o snapshot normalizado do formulario', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('dashboard.badgeforge'))
        ->post(route('badgeforge.presets.store'), [
            'name' => 'Release',
            'form' => badgePresetForm([
                'label' => 'build',
                'message' => 'passing',
                'color' => 'rgba(0,126,198,0.6)',
                'labelColor' => 'brightgreen',
                'logoSize' => '100',
                'logoPosition' => 'right',
                'link' => 'https://example.com/x',
                'cacheSeconds' => '999999',
            ]),
        ])
        ->assertRedirect(route('dashboard.badgeforge'))
        ->assertSessionHasNoErrors();

    $preset = BadgePreset::query()->sole();

    expect($preset->user_id)->toBe($user->id)
        ->and($preset->name)->toBe('Release')
        ->and($preset->payload)->toBe([
            'label' => 'build',
            'message' => 'passing',
            'style' => 'flat',
            'font' => null,
            'color' => '#007ec699',
            'labelColor' => '#44cc11',
            'logo' => 'none',
            'logoColor' => '#ffffff',
            'logoSize' => 20,
            'logoPosition' => 'right',
            'link' => 'https://example.com/x',
            'cacheSeconds' => 86400,
        ]);
});

it('aceita logo embutido e cai para none no desconhecido', function (string $logo, string $expected) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('badgeforge.presets.store'), [
            'name' => 'preset',
            'form' => badgePresetForm(['logo' => $logo]),
        ])
        ->assertSessionHasNoErrors();

    expect(BadgePreset::query()->sole()->payload['logo'])->toBe($expected);
})->with([
    'embutido' => ['github', 'github'],
    'embutido em caixa alta' => ['GITHUB', 'github'],
    'desconhecido' => ['nope', 'none'],
    'vazio' => ['', 'none'],
]);

it('aceita o logo custom do proprio dono e limita o cache', function () {
    $user = User::factory()->create();
    $logo = BadgeCustomLogo::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('badgeforge.presets.store'), [
            'name' => 'com logo',
            'form' => badgePresetForm([
                'logo' => 'u_'.strtolower($logo->id),
                'cacheSeconds' => '86400',
            ]),
        ])
        ->assertSessionHasNoErrors();

    $payload = BadgePreset::query()->sole()->payload;

    expect($payload['logo'])->toBe('u_'.$logo->id)
        ->and($payload['cacheSeconds'])->toBe(3600);
});

it('recusa logo custom de outra conta', function () {
    $user = User::factory()->create();
    $logo = BadgeCustomLogo::factory()->create();

    $this->actingAs($user)
        ->post(route('badgeforge.presets.store'), [
            'name' => 'roubo',
            'form' => badgePresetForm(['logo' => 'u_'.$logo->id]),
        ])
        ->assertSessionHasErrors(['form.logo' => __('validation.custom_logo.invalid_logo')]);

    expect(BadgePreset::query()->count())->toBe(0);
});

it('exige nome unico por conta e formulario', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    BadgePreset::factory()->for($user)->create(['name' => 'dup']);

    $this->actingAs($user)
        ->post(route('badgeforge.presets.store'), [
            'name' => 'dup',
            'form' => badgePresetForm(),
        ])
        ->assertSessionHasErrors(['name']);

    $this->actingAs($user)
        ->post(route('badgeforge.presets.store'), ['name' => 'sem-form'])
        ->assertSessionHasErrors(['form']);

    $this->actingAs($other)
        ->post(route('badgeforge.presets.store'), [
            'name' => 'dup',
            'form' => badgePresetForm(),
        ])
        ->assertSessionHasNoErrors();
});

it('nao limita presets do plano Free', function () {
    $user = User::factory()->create();

    foreach (['a', 'b', 'c'] as $name) {
        $this->actingAs($user)
            ->post(route('badgeforge.presets.store'), [
                'name' => $name,
                'form' => badgePresetForm(),
            ])
            ->assertSessionHasNoErrors();
    }

    expect($user->badgePresets()->count())->toBe(3);
});

it('renomeia e exclui o proprio preset', function () {
    $preset = BadgePreset::factory()->create(['name' => 'old']);

    $this->actingAs($preset->user)
        ->from(route('dashboard.badgeforge'))
        ->patch(route('badgeforge.presets.update', ['badgePreset' => $preset->id]), ['name' => 'new'])
        ->assertRedirect(route('dashboard.badgeforge'))
        ->assertSessionHasNoErrors();

    expect($preset->fresh()?->name)->toBe('new');

    $this->actingAs($preset->user)
        ->from(route('dashboard.badgeforge'))
        ->delete(route('badgeforge.presets.destroy', ['badgePreset' => $preset->id]))
        ->assertRedirect(route('dashboard.badgeforge'));

    expect(BadgePreset::query()->whereKey($preset->id)->exists())->toBeFalse();
});

it('responde 404 para preset de outra conta', function () {
    $preset = BadgePreset::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($other)
        ->patch(route('badgeforge.presets.update', ['badgePreset' => $preset->id]), ['name' => 'stolen'])
        ->assertNotFound();

    $this->actingAs($other)
        ->delete(route('badgeforge.presets.destroy', ['badgePreset' => $preset->id]))
        ->assertNotFound();

    expect($preset->fresh()?->name)->not->toBe('stolen')
        ->and(BadgePreset::query()->whereKey($preset->id)->exists())->toBeTrue();
});
