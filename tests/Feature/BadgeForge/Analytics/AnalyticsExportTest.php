<?php

use App\Enums\BadgeViewSubject;
use App\Models\BadgeAsset;
use App\Models\BadgeCustomProvider;
use App\Models\BadgeEndpoint;
use App\Models\BadgeView;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
| Exportacao CSV (F7, ADR-086).
|
| So pago, so do dono e sem PII: as colunas nao trazem IP, hash de IP nem
| User-Agent. A janela segue a dashboard e valor invalido cai no padrao.
*/

function analyticsExportAccount(bool $paid = false, string $email = 'export@example.com'): User
{
    $user = User::factory()->create(['email' => $email]);

    if ($paid) {
        $plan = Plan::query()->where('slug', 'pro-monthly')->sole();

        Subscription::factory()->for($user)->for($plan)->create([
            'current_period_start' => now()->subWeek(),
            'current_period_end' => now()->addWeeks(3),
        ]);
    }

    return $user->refresh();
}

function analyticsExportUrl(?int $window = null): string
{
    return route('badgeforge.analytics.export', $window === null ? [] : ['window' => $window]);
}

it('convidado e redirecionado e free recebe 403', function () {
    $this->get(analyticsExportUrl())->assertRedirect(route('login'));

    $free = analyticsExportAccount();

    $this->actingAs($free)
        ->get(analyticsExportUrl())
        ->assertForbidden();
});

it('exporta as colunas exatas sem ip nem user-agent e so as linhas do dono', function () {
    $user = analyticsExportAccount(paid: true);
    $other = analyticsExportAccount(email: 'intruder@example.com');

    $custom = BadgeCustomProvider::factory()->for($user)->create(['name' => 'my-api']);
    $endpoint = BadgeEndpoint::factory()->for($user)->create(['name' => 'my-endpoint']);
    $asset = BadgeAsset::factory()->for($user)->create(['name' => 'my-badge']);

    BadgeView::factory()->create([
        'user_id' => $user->getKey(),
        'subject_type' => BadgeViewSubject::Custom,
        'subject_id' => (string) $custom->getKey(),
        'definition' => 'stars',
        'format' => 'svg',
        'style' => 'flat',
        'cache_hit' => true,
        'country' => 'BR',
        'referer_domain' => 'github.com',
        'ip_hash' => str_repeat('a', 64),
        'user_agent' => 'UA-SECRETO-DO-EXPORT',
        'viewed_at' => now(),
    ]);

    BadgeView::factory()->create([
        'user_id' => $user->getKey(),
        'subject_type' => BadgeViewSubject::Personal,
        'subject_id' => (string) $asset->getKey(),
        'definition' => null,
        'format' => 'png',
        'style' => null,
        'cache_hit' => null,
        'country' => null,
        'referer_domain' => null,
        'viewed_at' => now(),
    ]);

    BadgeView::factory()->create([
        'user_id' => $other->getKey(),
        'subject_type' => BadgeViewSubject::Endpoint,
        'subject_id' => (string) $endpoint->getKey(),
        'viewed_at' => now(),
    ]);

    $response = $this->actingAs($user)->get(analyticsExportUrl(30));

    $response->assertOk();

    $lines = array_values(array_filter(explode("\n", trim($response->streamedContent()))));

    expect($lines[0])->toBe('viewed_at,subject_name,subject_type,definition,format,style,cache,country,referer_domain')
        ->and($lines)->toHaveCount(3)
        ->and($response->streamedContent())->not->toContain('UA-SECRETO-DO-EXPORT')
        ->and($response->streamedContent())->not->toContain(str_repeat('a', 64));

    // 1a linha: provider custom com definicao, cache hit e dimensoes completas.
    expect($lines[1])->toContain('my-api,custom,stars,svg,flat,hit,BR,github.com')
        ->and($lines[1])->toStartWith(now()->toDateString());

    // 2a linha: badge pessoal sem definicao/cache/pais, campos vazios.
    expect($lines[2])->toContain('my-badge,personal,,png,,,');
});

it('aplica a janela pedida e cai no padrao em valor invalido', function () {
    $user = analyticsExportAccount(paid: true);
    $asset = BadgeAsset::factory()->for($user)->create(['name' => 'old-badge']);

    BadgeView::factory()->create([
        'user_id' => $user->getKey(),
        'subject_type' => BadgeViewSubject::Personal,
        'subject_id' => (string) $asset->getKey(),
        'viewed_at' => now()->subDays(40),
    ]);

    expect($this->actingAs($user)->get(analyticsExportUrl(30))->streamedContent())
        ->not->toContain('old-badge')
        ->and($this->actingAs($user)->get(analyticsExportUrl(90))->streamedContent())
        ->toContain('old-badge')
        ->and($this->actingAs($user)->get(analyticsExportUrl())->streamedContent())
        ->not->toContain('old-badge');
});
