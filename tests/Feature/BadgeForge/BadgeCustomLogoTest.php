<?php

use App\Models\BadgeCustomLogo;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
| Logos personalizados pela conta (ADR-078).
|
| A conta envia um SVG, o sanitizador e a autoridade e so o resultado
| normalizado e gravado. O limite Free trava na action, dentro de transacao;
| recurso de outra conta responde 404 em toda a superficie autenticada.
*/

function badgeLogoUpload(string $svg, string $filename = 'logo.svg'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($filename, $svg);
}

function badgeLogoSvg(): string
{
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 2L2 22h20z"/></svg>';
}

function badgeForgePaidAccount(User $user): void
{
    $plan = Plan::query()->where('slug', 'pro-monthly')->sole();

    Subscription::factory()->for($user)->for($plan)->create([
        'current_period_start' => now()->subWeek(),
        'current_period_end' => now()->addWeeks(3),
    ]);
}

it('protege as rotas de gestao', function () {
    $logo = BadgeCustomLogo::factory()->create();
    $unverified = User::factory()->unverified()->create();
    $suspended = User::factory()->create(['suspended_at' => now()]);

    $this->post(route('badgeforge.logos.store'))->assertRedirect(route('login'));
    $this->get(route('badgeforge.logos.show', ['badgeCustomLogo' => $logo->id]))->assertRedirect(route('login'));

    $this->actingAs($unverified)
        ->post(route('badgeforge.logos.store'))
        ->assertRedirect(route('verification.notice'));

    $this->actingAs($suspended)
        ->get(route('dashboard.badgeforge'))
        ->assertRedirect(route('login'));

    $this->actingAs($suspended)
        ->post(route('badgeforge.logos.store'))
        ->assertRedirect(route('login'));
});

it('grava o logo normalizado e descarta o arquivo', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('dashboard.badgeforge'))
        ->post(route('badgeforge.logos.store'), [
            'name' => 'My mark',
            'logo' => badgeLogoUpload(badgeLogoSvg()),
        ])
        ->assertRedirect(route('dashboard.badgeforge'))
        ->assertSessionHasNoErrors();

    $logo = BadgeCustomLogo::query()->sole();

    expect($logo->user_id)->toBe($user->id)
        ->and($logo->name)->toBe('My mark')
        ->and($logo->view_box)->toBe('0 0 24 24')
        ->and($logo->path_count)->toBe(1)
        ->and($logo->paths)->toBe([['d' => 'M12 2L2 22h20z']])
        ->and($logo->size_bytes)->toBeGreaterThan(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('recusa nome repetido na mesma conta mas aceita em outra', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    BadgeCustomLogo::factory()->for($owner)->create(['name' => 'dup']);

    $this->actingAs($owner)
        ->post(route('badgeforge.logos.store'), [
            'name' => 'dup',
            'logo' => badgeLogoUpload(badgeLogoSvg()),
        ])
        ->assertSessionHasErrors(['name']);

    $this->actingAs($other)
        ->post(route('badgeforge.logos.store'), [
            'name' => 'dup',
            'logo' => badgeLogoUpload(badgeLogoSvg()),
        ])
        ->assertSessionHasNoErrors();

    expect(BadgeCustomLogo::query()->count())->toBe(2);
});

it('devolve 422 traduzido para SVG fora da allowlist', function (string $svg, string $key) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('badgeforge.logos.store'), [
            'name' => 'bad',
            'logo' => badgeLogoUpload($svg),
        ])
        ->assertSessionHasErrors(['logo' => __('validation.custom_logo.'.$key)]);

    expect(BadgeCustomLogo::query()->count())->toBe(0);
})->with([
    'script' => [
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"><script>alert(1)</script></svg>',
        'unsupported_element',
    ],
    'onload' => [
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"><path d="M0 0h1v1H0z" onload="x()"/></svg>',
        'unsupported_attribute',
    ],
    'sem viewBox' => [
        '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0h1v1H0z"/></svg>',
        'invalid_viewbox',
    ],
    'sem path' => [
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"></svg>',
        'missing_paths',
    ],
]);

it('limita o plano Free a dois logos', function () {
    $user = User::factory()->create();

    BadgeCustomLogo::factory()->count(2)->for($user)->sequence(
        static fn ($sequence): array => ['name' => 'logo-'.$sequence->index],
    )->create();

    $this->actingAs($user)
        ->post(route('badgeforge.logos.store'), [
            'name' => 'third',
            'logo' => badgeLogoUpload(badgeLogoSvg()),
        ])
        ->assertSessionHasErrors(['logo' => __('validation.custom_logo.limit_reached')]);

    expect($user->badgeCustomLogos()->count())->toBe(2);
});

it('nao limita o plano pago', function () {
    $user = User::factory()->create();

    badgeForgePaidAccount($user);

    BadgeCustomLogo::factory()->count(3)->for($user)->sequence(
        static fn ($sequence): array => ['name' => 'logo-'.$sequence->index],
    )->create();

    $this->actingAs($user)
        ->post(route('badgeforge.logos.store'), [
            'name' => 'fourth',
            'logo' => badgeLogoUpload(badgeLogoSvg()),
        ])
        ->assertSessionHasNoErrors();

    expect($user->badgeCustomLogos()->count())->toBe(4);
});

it('entrega os paths apenas ao dono', function () {
    $logo = BadgeCustomLogo::factory()->multipath()->create();
    $other = User::factory()->create();

    $this->actingAs($logo->user)
        ->get(route('badgeforge.logos.show', ['badgeCustomLogo' => $logo->id]))
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertExactJson([
            'viewBox' => '0 0 24 24',
            'paths' => [
                ['d' => 'M2 2h20v20H2z', 'fillRule' => 'evenodd', 'clipRule' => null],
                ['d' => 'M7 7h10v10H7z', 'fillRule' => 'evenodd', 'clipRule' => 'evenodd'],
            ],
        ]);

    $this->actingAs($other)
        ->get(route('badgeforge.logos.show', ['badgeCustomLogo' => $logo->id]))
        ->assertNotFound();
});

it('responde 404 para logo de outra conta na escrita', function () {
    $logo = BadgeCustomLogo::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($other)
        ->patch(route('badgeforge.logos.update', ['badgeCustomLogo' => $logo->id]), ['name' => 'stolen'])
        ->assertNotFound();

    $this->actingAs($other)
        ->delete(route('badgeforge.logos.destroy', ['badgeCustomLogo' => $logo->id]))
        ->assertNotFound();

    expect($logo->fresh()?->name)->not->toBe('stolen');
});

it('renomeia e exclui o proprio logo', function () {
    $logo = BadgeCustomLogo::factory()->create(['name' => 'old']);

    $this->actingAs($logo->user)
        ->from(route('dashboard.badgeforge'))
        ->patch(route('badgeforge.logos.update', ['badgeCustomLogo' => $logo->id]), ['name' => 'new'])
        ->assertRedirect(route('dashboard.badgeforge'))
        ->assertSessionHasNoErrors();

    expect($logo->fresh()?->name)->toBe('new');

    $this->actingAs($logo->user)
        ->from(route('dashboard.badgeforge'))
        ->delete(route('badgeforge.logos.destroy', ['badgeCustomLogo' => $logo->id]))
        ->assertRedirect(route('dashboard.badgeforge'));

    expect(BadgeCustomLogo::query()->whereKey($logo->id)->exists())->toBeFalse();
});

it('nao permite renomear para um nome ja usado na conta', function () {
    $user = User::factory()->create();
    $first = BadgeCustomLogo::factory()->for($user)->create(['name' => 'first']);
    BadgeCustomLogo::factory()->for($user)->create(['name' => 'second']);

    $this->actingAs($user)
        ->patch(route('badgeforge.logos.update', ['badgeCustomLogo' => $first->id]), ['name' => 'second'])
        ->assertSessionHasErrors(['name']);
});
