<?php

use App\Enums\AiRequestStatus;
use App\Models\AiRequest;
use App\Models\TaskSchedule;
use App\Models\UsageLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
| Retencao do historico (F7, ADR-051).
|
| A poda apaga so consulta terminal mais velha que a janela. Consulta em voo
| continua porque ainda e dona da reserva e do job; o `usage_ledger` fica
| porque e consumo, e nao historico.
*/

it('poda apenas consultas terminais mais antigas que a retencao', function () {
    $antiga = AiRequest::factory()->create(['status' => AiRequestStatus::Done]);
    $antiga->forceFill(['created_at' => now()->subDays(91)])->save();

    $antigaFalha = AiRequest::factory()->create(['status' => AiRequestStatus::Failed]);
    $antigaFalha->forceFill(['created_at' => now()->subDays(91)])->save();

    $recente = AiRequest::factory()->create(['status' => AiRequestStatus::Done]);

    $emVoo = AiRequest::factory()->create(['status' => AiRequestStatus::Queued]);
    $emVoo->forceFill(['created_at' => now()->subDays(91)])->save();

    UsageLedger::factory()->create();

    $this->artisan('history:prune')->assertSuccessful();

    $this->assertDatabaseMissing('ai_requests', ['id' => $antiga->id]);
    $this->assertDatabaseMissing('ai_requests', ['id' => $antigaFalha->id]);
    $this->assertDatabaseHas('ai_requests', ['id' => $recente->id]);
    $this->assertDatabaseHas('ai_requests', ['id' => $emVoo->id]);
    $this->assertDatabaseCount('usage_ledger', 1);
});

it('conta no dry-run sem apagar nada', function () {
    $antiga = AiRequest::factory()->create(['status' => AiRequestStatus::Done]);
    $antiga->forceFill(['created_at' => now()->subDays(91)])->save();

    $this->artisan('history:prune', ['--dry-run' => true])
        ->expectsOutputToContain('1 consulta')
        ->assertSuccessful();

    $this->assertDatabaseHas('ai_requests', ['id' => $antiga->id]);
});

it('respeita a janela de dias informada', function () {
    $vinteDias = AiRequest::factory()->create(['status' => AiRequestStatus::Done]);
    $vinteDias->forceFill(['created_at' => now()->subDays(20)])->save();

    $this->artisan('history:prune', ['--days' => 10])->assertSuccessful();

    $this->assertDatabaseMissing('ai_requests', ['id' => $vinteDias->id]);
});

it('usa a retencao configurada quando nao ha dias na linha de comando', function () {
    config(['ai.history.retention_days' => 7]);

    $cincoDias = AiRequest::factory()->create(['status' => AiRequestStatus::Done]);
    $cincoDias->forceFill(['created_at' => now()->subDays(8)])->save();

    $this->artisan('history:prune')->assertSuccessful();

    $this->assertDatabaseMissing('ai_requests', ['id' => $cincoDias->id]);
});

it('seeda a poda diaria do historico uma unica vez', function () {
    $linhas = TaskSchedule::query()->where('command', 'history:prune')->get();

    expect($linhas)->toHaveCount(1)
        ->and($linhas->first()->expression)->toBe('45 3 * * *')
        ->and($linhas->first()->parameters)->toBeNull()
        ->and($linhas->first()->is_active)->toBeTrue();
});
