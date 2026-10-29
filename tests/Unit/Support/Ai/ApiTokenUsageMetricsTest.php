<?php

use App\Models\AiRequest;
use App\Models\User;
use App\Support\Ai\ApiTokenUsageMetrics;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('calcula o consumo do mes atual agrupado por dia e por token', function () {
    $now = CarbonImmutable::parse('2026-10-29 12:00:00');
    $user = User::factory()->create();

    $token1 = $user->createToken('token-prod', ['ai:query']);
    $token2 = $user->createToken('token-dev', ['ai:query']);

    // Requisicoes no dia 2026-10-28
    AiRequest::factory()->done()->create([
        'user_id' => $user->id,
        'api_token_id' => $token1->accessToken->id,
        'tokens_in' => 500,
        'tokens_out' => 150,
        'created_at' => '2026-10-28 10:00:00',
    ]);

    AiRequest::factory()->done()->create([
        'user_id' => $user->id,
        'api_token_id' => $token2->accessToken->id,
        'tokens_in' => 200,
        'tokens_out' => 100,
        'created_at' => '2026-10-28 14:00:00',
    ]);

    // Requisicao no dia 2026-10-29
    AiRequest::factory()->done()->create([
        'user_id' => $user->id,
        'api_token_id' => $token1->accessToken->id,
        'tokens_in' => 1000,
        'tokens_out' => 300,
        'created_at' => '2026-10-29 09:00:00',
    ]);

    $service = new ApiTokenUsageMetrics;
    $result = $service->forUser($user, ApiTokenUsageMetrics::PERIOD_CURRENT_MONTH, now: $now);

    expect($result['period']['type'])->toBe('current_month')
        ->and($result['period']['startDate'])->toBe('2026-10-01')
        ->and($result['period']['endDate'])->toBe('2026-10-31')
        ->and($result['summary']['totalTokens'])->toBe(2250) // (500+150) + (200+100) + (1000+300) = 650 + 300 + 1300 = 2250
        ->and($result['summary']['totalRequests'])->toBe(3);

    // Encontrar dia 28
    $day28 = collect($result['days'])->firstWhere('date', '2026-10-28');
    expect($day28)->not->toBeNull()
        ->and($day28['totalTokens'])->toBe(950)
        ->and($day28['totalRequests'])->toBe(2)
        ->and($day28['breakdown'])->toHaveCount(2);

    // Encontrar dia 29
    $day29 = collect($result['days'])->firstWhere('date', '2026-10-29');
    expect($day29)->not->toBeNull()
        ->and($day29['totalTokens'])->toBe(1300)
        ->and($day29['totalRequests'])->toBe(1);

    // Dias sem requisicoes tem total 0
    $day01 = collect($result['days'])->firstWhere('date', '2026-10-01');
    expect($day01)->not->toBeNull()
        ->and($day01['totalTokens'])->toBe(0)
        ->and($day01['totalRequests'])->toBe(0)
        ->and($day01['breakdown'])->toBeEmpty();
});

it('rotula tokens revogados e mantem a soma do dia exata', function () {
    $now = CarbonImmutable::parse('2026-10-29 12:00:00');
    $user = User::factory()->create();

    $activeToken = $user->createToken('token-ativo', ['ai:query']);

    // Simulando requisicao com um token que nao existe mais em personal_access_tokens
    AiRequest::factory()->done()->create([
        'user_id' => $user->id,
        'api_token_id' => 9999, // Revogado
        'tokens_in' => 400,
        'tokens_out' => 100,
        'created_at' => '2026-10-28 10:00:00',
    ]);

    AiRequest::factory()->done()->create([
        'user_id' => $user->id,
        'api_token_id' => $activeToken->accessToken->id,
        'tokens_in' => 600,
        'tokens_out' => 200,
        'created_at' => '2026-10-28 11:00:00',
    ]);

    $service = new ApiTokenUsageMetrics;
    $result = $service->forUser($user, ApiTokenUsageMetrics::PERIOD_CURRENT_MONTH, now: $now);

    $day28 = collect($result['days'])->firstWhere('date', '2026-10-28');
    expect($day28['totalTokens'])->toBe(1300)
        ->and($day28['totalRequests'])->toBe(2);

    $revokedItem = collect($day28['breakdown'])->firstWhere('isRevoked', true);
    expect($revokedItem)->not->toBeNull()
        ->and($revokedItem['tokens'])->toBe(500)
        ->and($revokedItem['tokenId'])->toBe('revoked');
});

it('respeita o periodo do mes anterior e periodo customizado com teto de 90 dias', function () {
    $now = CarbonImmutable::parse('2026-10-29 12:00:00');
    $user = User::factory()->create();

    $service = new ApiTokenUsageMetrics;

    // Mes anterior
    $previous = $service->forUser($user, ApiTokenUsageMetrics::PERIOD_PREVIOUS_MONTH, now: $now);
    expect($previous['period']['type'])->toBe('previous_month')
        ->and($previous['period']['startDate'])->toBe('2026-09-01')
        ->and($previous['period']['endDate'])->toBe('2026-09-30');

    // Custom com intervalo superior a 90 dias e limitado a 90 dias
    $custom = $service->forUser(
        $user,
        ApiTokenUsageMetrics::PERIOD_CUSTOM,
        customStart: '2026-01-01',
        customEnd: '2026-06-01',
        now: $now
    );

    $start = CarbonImmutable::parse($custom['period']['startDate']);
    $end = CarbonImmutable::parse($custom['period']['endDate']);

    expect($custom['period']['type'])->toBe('custom')
        ->and($start->diffInDays($end))->toBeLessThanOrEqual(90);
});
