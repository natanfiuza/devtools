<?php

use App\Models\OutputTarget;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\UsageLedger;
use App\Models\User;
use App\Support\Ai\AiToolProps;
use App\Support\ToolProps;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
| Props do AI SQL Builder na pagina generica (ADR-043).
|
| Elas existem para a tela explicar o estado antes de a pessoa preencher o
| formulario: visitante, e-mail nao verificado, conta suspensa, cota esgotada e
| provider desligado. Nao sao autorizacao - o POST continua validando tudo -,
| mas precisam contar a mesma historia que a API, senao a tela oferece o botao
| que o servidor vai recusar.
*/

beforeEach(function () {
    config([
        'ai.base_url' => 'https://api.deepseek.test',
        'ai.model' => 'test-model',
        'ai.api_key' => 'test-key',
    ]);
});

it('entrega o pacote do visitante com os limites do Free', function () {
    $props = AiToolProps::for(null);

    expect($props['available'])->toBeTrue()
        ->and($props['canUse'])->toBe(['allowed' => false, 'reason' => 'guest'])
        ->and($props['quota'])->toBeNull()
        ->and($props['sources'])->toBe(['ddl', 'json', 'migration', 'models'])
        ->and($props['dialects'])->toBe(['mysql', 'pgsql', 'sqlsrv', 'sqlite'])
        ->and($props['targets'])->toHaveCount(9)
        ->and($props['targets'][0]['slug'])->toBe('sql')
        ->and($props['targets'][0]['dialects'])->toBe(['mysql', 'pgsql', 'sqlsrv', 'sqlite'])
        // O teto tecnico e maior; o Free e o piso de qualquer conta.
        ->and($props['limits'])->toBe(['schema' => 8192, 'description' => 500])
        ->and($props['poll'])->toBe([
            'initialMs' => 1500,
            'maxMs' => 5000,
            'timeoutMs' => 120000,
        ])
        ->and($props['endpoints'])->toBe([
            'store' => '/api/ai/queries',
            'show' => '/api/ai/queries/__ID__',
            'history' => '/api/ai/history',
        ]);
});

it('libera a conta verificada e expoe a cota em usos', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $props = AiToolProps::for($user);

    expect($props['canUse'])->toBe(['allowed' => true, 'reason' => null])
        ->and($props['quota'])->toMatchArray([
            'unit' => 'uses',
            'limit' => 5,
            'used' => 0,
            'remaining' => 5,
            'exhausted' => false,
            'window' => ['start' => null, 'end' => null],
        ]);
});

it('bloqueia conta sem e-mail verificado', function () {
    $user = User::factory()->unverified()->create();

    $props = AiToolProps::for($user);

    expect($props['canUse'])->toBe(['allowed' => false, 'reason' => 'email_unverified']);
});

it('bloqueia conta suspensa', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'suspended_at' => now(),
    ]);

    $props = AiToolProps::for($user);

    expect($props['canUse'])->toBe(['allowed' => false, 'reason' => 'account_suspended']);
});

it('bloqueia quando a cota acabou', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    UsageLedger::factory()->count(5)->for($user)->create();

    $props = AiToolProps::for($user);

    expect($props['canUse'])->toBe(['allowed' => false, 'reason' => 'quota_exhausted'])
        ->and($props['quota']['exhausted'])->toBeTrue()
        ->and($props['quota']['remaining'])->toBe(0);
});

it('bloqueia quando o provider nao esta configurado', function () {
    config(['ai.api_key' => null]);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $props = AiToolProps::for($user);

    expect($props['available'])->toBeFalse()
        ->and($props['canUse'])->toBe(['allowed' => false, 'reason' => 'unavailable']);
});

it('usa os limites e a unidade do plano pago', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $plan = Plan::query()->where('slug', 'pro-monthly')->sole();

    Subscription::factory()->for($user)->for($plan)->create();

    $props = AiToolProps::for($user);

    expect($props['canUse'])->toBe(['allowed' => true, 'reason' => null])
        ->and($props['limits'])->toBe(['schema' => 65536, 'description' => 4000])
        ->and($props['quota']['unit'])->toBe('tokens')
        ->and($props['quota']['limit'])->toBe($plan->monthly_tokens);
});

it('esconde alvo desligado do catalogo da tela', function () {
    OutputTarget::query()->where('slug', 'prisma')->update(['enabled' => false]);

    $props = AiToolProps::for(null);

    expect(array_column($props['targets'], 'slug'))->not->toContain('prisma')
        ->and($props['targets'])->toHaveCount(8);
});

it('devolve pacote vazio para tool que nao declara props', function () {
    expect(ToolProps::for('json-viewer', null))->toEqual((object) [])
        ->and(ToolProps::for('ai-sql-builder', null))->not->toEqual((object) []);
});
