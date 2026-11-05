<?php

use App\Enums\BadgeViewSubject;
use App\Models\BadgeView;
use App\Models\BadgeViewDaily;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/*
| Agregado diario do analytics (F7, ADR-086): o comando reconstroi os dias a
| partir do bruto, de forma idempotente, e nunca mexe num dia cujo bruto ja
| foi podado.
*/

/**
 * @param  array<string, mixed>  $overrides
 */
function analyticsAggregateView(User $user, string $subjectId, array $overrides = []): BadgeView
{
    return BadgeView::factory()->create(array_merge([
        'user_id' => $user->getKey(),
        'subject_type' => BadgeViewSubject::Personal,
        'subject_id' => $subjectId,
        'viewed_at' => now(),
    ], $overrides));
}

/**
 * @return list<array<string, mixed>>
 */
function analyticsAggregateDailyRows(): array
{
    return DB::table('badge_view_dailies')
        ->orderByDesc('country')
        ->orderByDesc('referer_domain')
        ->orderBy('user_id')
        ->get()
        ->map(static fn (object $row): array => (array) $row)
        ->all();
}

it('reconstroi o dia com sentinelas, unicos e hits de cache', function () {
    $user = User::factory()->create();
    $subject = '01JAAAAAAAAAAAAAAAAAAAAAAA';
    $sharedIp = str_repeat('a', 64);

    analyticsAggregateView($user, $subject, [
        'ip_hash' => $sharedIp,
        'referer_domain' => 'github.com',
        'country' => 'BR',
        'format' => 'svg',
        'cache_hit' => true,
    ]);
    analyticsAggregateView($user, $subject, [
        'ip_hash' => $sharedIp,
        'referer_domain' => 'github.com',
        'country' => 'BR',
        'format' => 'svg',
        'cache_hit' => true,
    ]);
    analyticsAggregateView($user, $subject, [
        'ip_hash' => str_repeat('b', 64),
        'referer_domain' => null,
        'country' => null,
        'format' => 'png',
        'cache_hit' => false,
    ]);

    $this->artisan('badgeforge:analytics:aggregate')->assertSuccessful();

    $rows = analyticsAggregateDailyRows();

    expect($rows)->toHaveCount(2)

        // Grupo com IP repetido: duas visualizacoes, um visitante unico.
        ->and($rows[0])->toMatchArray([
            'subject_type' => 'personal',
            'subject_id' => $subject,
            'user_id' => $user->getKey(),
            'day' => now()->toDateString(),
            'definition' => '',
            'country' => 'BR',
            'referer_domain' => 'github.com',
            'format' => 'svg',
            'views' => 2,
            'unique_visitors' => 1,
            'cache_hits' => 2,
        ])

        // Dimensoes ausentes viram '' (nunca NULL) e o miss nao conta hit.
        ->and($rows[1])->toMatchArray([
            'country' => '',
            'referer_domain' => '',
            'format' => 'png',
            'views' => 1,
            'unique_visitors' => 1,
            'cache_hits' => 0,
        ]);
});

it('guarda a definicao das metricas customizadas', function () {
    $user = User::factory()->create();

    analyticsAggregateView($user, '01JBBBBBBBBBBBBBBBBBBBBBBB', [
        'subject_type' => BadgeViewSubject::Custom,
        'definition' => 'stars',
    ]);

    $this->artisan('badgeforge:analytics:aggregate')->assertSuccessful();

    expect(analyticsAggregateDailyRows()[0])->toMatchArray([
        'subject_type' => 'custom',
        'definition' => 'stars',
        'views' => 1,
    ]);
});

it('e idempotente: rodar de novo nao duplica nem desalinha', function () {
    $user = User::factory()->create();

    analyticsAggregateView($user, '01JCCCCCCCCCCCCCCCCCCCCCCC');
    analyticsAggregateView($user, '01JCCCCCCCCCCCCCCCCCCCCCCC');

    $this->artisan('badgeforge:analytics:aggregate')->assertSuccessful();
    $first = analyticsAggregateDailyRows();

    $this->artisan('badgeforge:analytics:aggregate')->assertSuccessful();
    $second = analyticsAggregateDailyRows();

    expect($second)->toHaveCount(1)
        ->and($second[0]['views'])->toBe(2)
        ->and($second[0]['id'])->not->toBe($first[0]['id']);
});

it('reflete o bruto do dia a cada rodada', function () {
    $user = User::factory()->create();
    $subject = '01JDDDDDDDDDDDDDDDDDDDDDDD';

    analyticsAggregateView($user, $subject);

    $this->artisan('badgeforge:analytics:aggregate')->assertSuccessful();

    expect(analyticsAggregateDailyRows()[0]['views'])->toBe(1);

    analyticsAggregateView($user, $subject);

    $this->artisan('badgeforge:analytics:aggregate')->assertSuccessful();

    expect(analyticsAggregateDailyRows()[0]['views'])->toBe(2);
});

it('so reconstroi a janela pedida e respeita o padrao da config', function () {
    $user = User::factory()->create();

    analyticsAggregateView($user, '01JEEEEEEEEEEEEEEEEEEEEEEE', ['viewed_at' => now()]);
    analyticsAggregateView($user, '01JFFFFFFFFFFFFFFFFFFFFFFF', ['viewed_at' => now()->subDays(2)]);

    $this->artisan('badgeforge:analytics:aggregate', ['--days' => 2])->assertSuccessful();

    expect(analyticsAggregateDailyRows())->toHaveCount(1)
        ->and(analyticsAggregateDailyRows()[0]['subject_id'])->toBe('01JEEEEEEEEEEEEEEEEEEEEEEE');

    config(['badgeforge.analytics.aggregate_days' => 7]);

    $this->artisan('badgeforge:analytics:aggregate')->assertSuccessful();

    expect(analyticsAggregateDailyRows())->toHaveCount(2);
});

it('preserva o agregado de um dia cujo bruto ja foi podado', function () {
    $user = User::factory()->create();
    $oldDay = now()->subDays(100);

    $existing = BadgeViewDaily::factory()->create([
        'user_id' => $user->getKey(),
        'subject_id' => '01JGGGGGGGGGGGGGGGGGGGGGGG',
        'day' => $oldDay->toDateString(),
        'views' => 12,
        'country' => '',
        'referer_domain' => '',
        'format' => 'svg',
    ]);

    $this->artisan('badgeforge:analytics:aggregate', ['--days' => 7])->assertSuccessful();

    expect(BadgeViewDaily::query()->whereKey($existing->getKey())->exists())->toBeTrue()
        ->and($existing->refresh()->views)->toBe(12);
});

it('mantem uma linha por dono mesmo com sujeitos diferentes no mesmo dia', function () {
    $first = User::factory()->create();
    $second = User::factory()->create();

    analyticsAggregateView($first, '01JHHHHHHHHHHHHHHHHHHHHHHH');
    analyticsAggregateView($second, '01JIIIIIIIIIIIIIIIIIIIIIII');

    $this->artisan('badgeforge:analytics:aggregate')->assertSuccessful();

    $rows = analyticsAggregateDailyRows();

    expect($rows)->toHaveCount(2)
        ->and(array_column($rows, 'user_id'))->toBe([$first->getKey(), $second->getKey()]);
});
