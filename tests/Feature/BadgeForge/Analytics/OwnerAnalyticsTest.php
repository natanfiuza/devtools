<?php

use App\Enums\BadgeViewSubject;
use App\Models\BadgeAsset;
use App\Models\BadgeCustomProvider;
use App\Models\BadgeEndpoint;
use App\Models\BadgeView;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Support\BadgeForge\Analytics\OwnerAnalytics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
| Analytics do dono (F7, ADR-086).
|
| O dono le o bruto: Free fica com 30 dias, totais e contagem por imagem;
| pago escolhe 7/30/90 e ganha serie, quebras e CSV. Janela invalida nunca
| vira erro; unico e distinto por periodo (serie: por dia).
*/

function ownerAnalyticsAccount(bool $paid = false, string $email = 'owner-analytics@example.com'): User
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

/**
 * @param  array<string, mixed>  $overrides
 */
function ownerAnalyticsView(
    User $user,
    BadgeViewSubject $type,
    string $subjectId,
    array $overrides = [],
): BadgeView {
    return BadgeView::factory()->create(array_merge([
        'user_id' => $user->getKey(),
        'subject_type' => $type,
        'subject_id' => $subjectId,
        'ip_hash' => str_pad((string) fake()->unique()->numberBetween(1, 999999), 64, '0'),
        'viewed_at' => now(),
    ], $overrides));
}

/**
 * @return array<string, mixed>
 */
function ownerAnalyticsFor(User $user, mixed $window = null): array
{
    return app(OwnerAnalytics::class)->for($user, $window);
}

it('free fica na janela de 30 dias e recebe so totais e contagem por imagem', function () {
    $user = ownerAnalyticsAccount();
    $asset = BadgeAsset::factory()->for($user)->create(['name' => 'readme']);

    ownerAnalyticsView($user, BadgeViewSubject::Personal, (string) $asset->getKey(), [
        'ip_hash' => str_repeat('a', 64),
    ]);
    ownerAnalyticsView($user, BadgeViewSubject::Personal, (string) $asset->getKey(), [
        'ip_hash' => str_repeat('b', 64),
    ]);
    ownerAnalyticsView($user, BadgeViewSubject::Personal, (string) $asset->getKey(), [
        'ip_hash' => str_repeat('c', 64),
        'viewed_at' => now()->subDays(40),
    ]);

    $analytics = ownerAnalyticsFor($user, 7);

    expect($analytics['isPaid'])->toBeFalse()
        ->and($analytics['window'])->toBe(30)
        ->and($analytics['windows'])->toBe([])
        ->and($analytics['freeWindowDays'])->toBe(30)
        ->and($analytics['totals'])->toBe(['views' => 2, 'uniques' => 2])
        ->and($analytics['subjects'])->toHaveCount(1)
        ->and($analytics['subjects'][0]['type'])->toBe('personal')
        ->and($analytics['subjects'][0]['id'])->toBe((string) $asset->getKey())
        ->and($analytics['subjects'][0]['name'])->toBe('readme')
        ->and($analytics['subjects'][0]['views'])->toBe(2)
        ->and($analytics['subjects'][0]['uniques'])->toBe(2)
        ->and($analytics['days'])->toBe([])
        ->and($analytics['referers'])->toBe([])
        ->and($analytics['countries'])->toBe([])
        ->and($analytics['formats'])->toBe([])
        ->and($analytics['cache'])->toBeNull()
        ->and($analytics['exportUrl'])->toBeNull();
});

it('pago respeita 7/30/90 e cai no padrao em janela invalida', function () {
    $user = ownerAnalyticsAccount(paid: true);
    $asset = BadgeAsset::factory()->for($user)->create();

    ownerAnalyticsView($user, BadgeViewSubject::Personal, (string) $asset->getKey(), [
        'viewed_at' => now()->subDays(5),
    ]);
    ownerAnalyticsView($user, BadgeViewSubject::Personal, (string) $asset->getKey(), [
        'viewed_at' => now()->subDays(60),
    ]);

    expect(ownerAnalyticsFor($user, 7)['totals']['views'])->toBe(1)
        ->and(ownerAnalyticsFor($user, 30)['totals']['views'])->toBe(1)
        ->and(ownerAnalyticsFor($user, 90)['totals']['views'])->toBe(2)
        ->and(ownerAnalyticsFor($user, 'abc')['window'])->toBe(30)
        ->and(ownerAnalyticsFor($user, 999)['window'])->toBe(30)
        ->and(ownerAnalyticsFor($user, null)['window'])->toBe(30)
        ->and(ownerAnalyticsFor($user, 7)['windows'])->toBe([7, 30, 90])
        ->and(ownerAnalyticsFor($user, 7)['isPaid'])->toBeTrue()
        ->and(ownerAnalyticsFor($user, 7)['exportUrl'])
        ->toBe(route('badgeforge.analytics.export', ['window' => 7]));
});

it('a serie diaria e zero-filled e os unicos sao contados por dia', function () {
    $user = ownerAnalyticsAccount(paid: true);
    $asset = BadgeAsset::factory()->for($user)->create();
    $id = (string) $asset->getKey();
    $sharedIp = str_repeat('d', 64);

    ownerAnalyticsView($user, BadgeViewSubject::Personal, $id, ['ip_hash' => $sharedIp]);
    ownerAnalyticsView($user, BadgeViewSubject::Personal, $id, [
        'ip_hash' => str_repeat('e', 64),
    ]);
    ownerAnalyticsView($user, BadgeViewSubject::Personal, $id, [
        'ip_hash' => $sharedIp,
        'viewed_at' => now()->subDays(2),
    ]);

    $days = ownerAnalyticsFor($user, 7)['days'];
    $byDate = collect($days)->keyBy('date');

    expect($days)->toHaveCount(7)
        ->and($byDate[now()->toDateString()])->toBe([
            'date' => now()->toDateString(),
            'views' => 2,
            'uniques' => 2,
        ])
        ->and($byDate[now()->subDay()->toDateString()])->toBe([
            'date' => now()->subDay()->toDateString(),
            'views' => 0,
            'uniques' => 0,
        ])
        ->and($byDate[now()->subDays(2)->toDateString()])->toBe([
            'date' => now()->subDays(2)->toDateString(),
            'views' => 1,
            'uniques' => 1,
        ]);
});

it('referrers, paises e formatos vem ordenados e limitados pelo topo', function () {
    config(['badgeforge.analytics.top_limit' => 3]);

    $user = ownerAnalyticsAccount(paid: true);
    $asset = BadgeAsset::factory()->for($user)->create();
    $id = (string) $asset->getKey();

    foreach ([5, 4, 3, 2, 1] as $index => $count) {
        for ($i = 0; $i < $count; $i++) {
            ownerAnalyticsView($user, BadgeViewSubject::Personal, $id, [
                'referer_domain' => 'site'.$index.'.test',
                'country' => ['BR', 'US', 'DE', 'FR', 'ES'][$index],
            ]);
        }
    }

    $analytics = ownerAnalyticsFor($user, 30);

    expect($analytics['referers'])->toHaveCount(3)
        ->and($analytics['referers'][0])->toBe(['domain' => 'site0.test', 'views' => 5])
        ->and($analytics['referers'][1])->toBe(['domain' => 'site1.test', 'views' => 4])
        ->and($analytics['countries'])->toHaveCount(3)
        ->and($analytics['countries'][0])->toBe(['country' => 'BR', 'views' => 5])
        ->and($analytics['formats'])->toBe([['format' => 'svg', 'views' => 15]]);
});

it('a taxa de cache ignora acessos que nao informam cache_hit', function () {
    $user = ownerAnalyticsAccount(paid: true);
    $endpoint = BadgeEndpoint::factory()->for($user)->create();
    $asset = BadgeAsset::factory()->for($user)->create();

    foreach ([true, true, false] as $hit) {
        ownerAnalyticsView($user, BadgeViewSubject::Endpoint, (string) $endpoint->getKey(), [
            'cache_hit' => $hit,
        ]);
    }

    for ($i = 0; $i < 5; $i++) {
        ownerAnalyticsView($user, BadgeViewSubject::Personal, (string) $asset->getKey(), [
            'cache_hit' => null,
        ]);
    }

    expect(ownerAnalyticsFor($user, 30)['cache'])->toBe([
        'hits' => 2,
        'total' => 3,
        'rate' => 0.6667,
    ]);
});

it('so conta as visualizacoes do dono e resolve o nome de cada sujeito', function () {
    $user = ownerAnalyticsAccount(paid: true, email: 'owner@example.com');
    $other = ownerAnalyticsAccount(email: 'other@example.com');

    $custom = BadgeCustomProvider::factory()->for($user)->create(['name' => 'my-api']);
    $endpoint = BadgeEndpoint::factory()->for($user)->create(['name' => 'my-endpoint']);
    $asset = BadgeAsset::factory()->for($user)->create(['name' => 'my-badge']);

    ownerAnalyticsView($user, BadgeViewSubject::Custom, (string) $custom->getKey(), [
        'definition' => 'stars',
    ]);
    ownerAnalyticsView($user, BadgeViewSubject::Endpoint, (string) $endpoint->getKey());
    ownerAnalyticsView($user, BadgeViewSubject::Personal, (string) $asset->getKey());
    ownerAnalyticsView($other, BadgeViewSubject::Personal, (string) $asset->getKey());

    $subjects = collect(ownerAnalyticsFor($user, 30)['subjects'])
        ->keyBy(fn (array $subject): string => $subject['type']);

    expect($subjects)->toHaveCount(3)
        ->and($subjects['custom']['name'])->toBe('my-api')
        ->and($subjects['endpoint']['name'])->toBe('my-endpoint')
        ->and($subjects['personal']['name'])->toBe('my-badge')
        ->and(ownerAnalyticsFor($user, 30)['totals']['views'])->toBe(3);
});

it('sujeito excluido mantem a contagem com nome vazio', function () {
    $user = ownerAnalyticsAccount();
    $orphan = (string) Str::ulid();

    ownerAnalyticsView($user, BadgeViewSubject::Custom, $orphan, ['definition' => 'stars']);

    expect(ownerAnalyticsFor($user, 30)['subjects'][0])->toMatchArray([
        'type' => 'custom',
        'id' => $orphan,
        'name' => '',
        'views' => 1,
        'uniques' => 1,
    ]);
});
