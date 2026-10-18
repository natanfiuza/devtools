<?php

use App\Jobs\ExecuteCommand;
use App\Models\TaskSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('executa job ExecuteCommand disparando o comando com parametros', function () {
    $schedule = TaskSchedule::query()->first();
    expect($schedule)->not->toBeNull();

    $job = new ExecuteCommand($schedule->command, $schedule->parameters ?? []);
    $job->handle();

    expect($job->jobUuid)->not->toBeEmpty();
});

it('comando accounts:purge-deleted expurga contas com soft delete maior que a quantidade de dias', function () {
    $userAtivo = User::factory()->create();
    $userRecente = User::factory()->create();
    $userExpirado = User::factory()->create();

    // Soft delete recente (< 30 dias)
    $userRecente->delete();
    DB::table('users')->where('id', $userRecente->id)->update(['deleted_at' => now()->subDays(10)]);

    // Soft delete expirado (> 30 dias)
    $userExpirado->delete();
    DB::table('users')->where('id', $userExpirado->id)->update(['deleted_at' => now()->subDays(35)]);

    $this->artisan('accounts:purge-deleted', ['--days' => 30])
        ->expectsOutputToContain('1 conta(s) expurgada(s) com sucesso.')
        ->assertExitCode(0);

    expect($userAtivo->refresh()->trashed())->toBeFalse()
        ->and($userRecente->refresh()->trashed())->toBeTrue()
        ->and($userRecente->refresh()->anonymized_at)->toBeNull()
        ->and($userExpirado->refresh()->anonymized_at)->not->toBeNull()
        ->and($userExpirado->refresh()->email)->toBe('removido+'.$userExpirado->id.'@removido.invalid');
});
