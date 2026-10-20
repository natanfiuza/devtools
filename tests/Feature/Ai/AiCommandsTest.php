<?php

use App\Actions\Ai\StartAiQuery;
use App\Enums\AiFailureCode;
use App\Enums\AiRequestStatus;
use App\Enums\SchemaSource;
use App\Enums\SqlDialect;
use App\Models\AiCache;
use App\Models\AiRequest;
use App\Models\OutputTarget;
use App\Models\UsageLedger;
use App\Models\User;
use App\Support\Quota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();

    config([
        'ai.base_url' => 'https://api.deepseek.test',
        'ai.model' => 'test-model',
        'ai.api_key' => 'test-key',
        'ai.estimate_output_tokens' => 900,
        'ai.job.timeout' => 90,
        'ai.job.reconcile_grace_seconds' => 30,
    ]);
});

function aiCommandRequest(): AiRequest
{
    $user = User::factory()->create();
    $target = OutputTarget::query()->where('slug', 'sql')->sole();

    return app(StartAiQuery::class)(
        $user,
        $target,
        SchemaSource::Ddl,
        SqlDialect::Mysql,
        'CREATE TABLE users (id int);',
        'Liste os usuarios',
    )->request;
}

it('conta o cache expirado no dry-run sem apagar', function () {
    AiCache::factory()->create();
    AiCache::factory()->expired()->create();

    $this->artisan('ai:prune-cache', ['--dry-run' => true])
        ->assertSuccessful();

    $this->assertDatabaseCount('ai_cache', 2);
});

it('apaga somente o cache expirado', function () {
    $active = AiCache::factory()->create();
    $expired = AiCache::factory()->expired()->create();

    $this->artisan('ai:prune-cache')
        ->assertSuccessful();

    $this->assertDatabaseMissing('ai_cache', ['id' => $expired->id]);
    $this->assertDatabaseHas('ai_cache', ['id' => $active->id]);
});

it('conta solicitacoes presas no dry-run sem encerrar', function () {
    $request = aiCommandRequest();
    $request->forceFill(['created_at' => now()->subMinutes(10)])->save();

    $this->artisan('ai:reconcile', ['--dry-run' => true])
        ->assertSuccessful();

    expect($request->refresh()->status)->toBe(AiRequestStatus::Queued)
        ->and(Quota::for($request->user)->used())->toBe(1);
});

it('encerra solicitacoes presas e estorna a reserva', function () {
    $stale = aiCommandRequest();
    $stale->forceFill(['created_at' => now()->subMinutes(10)])->save();

    $fresh = aiCommandRequest();
    $fresh->forceFill(['created_at' => now()->subMinute()])->save();

    $this->artisan('ai:reconcile')
        ->assertSuccessful();

    $stale->refresh();

    expect($stale->status)->toBe(AiRequestStatus::Failed)
        ->and($stale->error_code)->toBe(AiFailureCode::Timeout)
        ->and($stale->canonical_payload)->toBeNull()
        ->and(Quota::for($stale->user)->used())->toBe(0);

    expect($fresh->refresh()->status)->toBe(AiRequestStatus::Queued)
        ->and(Quota::for($fresh->user)->used())->toBe(1);

    expect(UsageLedger::query()->where('type', 'refund')->count())->toBe(1);
});
