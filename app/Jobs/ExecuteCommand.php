<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Job assincrono para execucao de comandos Artisan via fila.
 * Padrao de execucao e monitoramento baseado no projeto SIG-Novo.
 */
class ExecuteCommand implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * O tempo maximo de execucao do job em segundos (0 = sem timeout pelo worker).
     */
    public int $timeout = 0;

    /**
     * Define que o job nao falha por timeout.
     */
    public bool $failOnTimeout = false;

    /**
     * Quantidade maxima de tentativas. 1 para evitar execucoes duplicadas.
     */
    public int $tries = 1;

    /**
     * Identificador UUID unico da execucao do job.
     */
    public string $jobUuid;

    /**
     * Create a new job instance.
     *
     * @param  string  $commandName  Nome ou assinatura do comando Artisan.
     * @param  array<string, mixed>  $parameters  Parametros ou opcoes adicionais.
     */
    public function __construct(
        public string $commandName,
        public array $parameters = [],
    ) {
        $this->jobUuid = (string) Str::uuid();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        set_time_limit(0);

        $startTime = microtime(true);

        Log::info("[ExecuteCommand] Iniciando execucao do comando {$this->commandName}", [
            'uuid' => $this->jobUuid,
            'command' => $this->commandName,
            'parameters' => $this->parameters,
        ]);

        $exitCode = Artisan::call($this->commandName, $this->parameters);

        $duration = microtime(true) - $startTime;

        Log::info("[ExecuteCommand] Comando {$this->commandName} finalizado com codigo {$exitCode}", [
            'uuid' => $this->jobUuid,
            'command' => $this->commandName,
            'duration_seconds' => round($duration, 2),
            'exit_code' => $exitCode,
        ]);
    }

    /**
     * Lida com a falha do job.
     */
    public function failed(Throwable $exception): void
    {
        Log::error("[ExecuteCommand] Falha na execucao do comando {$this->commandName}", [
            'uuid' => $this->jobUuid,
            'command' => $this->commandName,
            'parameters' => $this->parameters,
            'error' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ]);
    }

    /**
     * Nome amigavel exibido em filas e logs.
     */
    public function displayName(): string
    {
        $params = http_build_query($this->parameters, '', ', ');

        return static::class." ({$this->commandName}".($params !== '' ? " [{$params}]" : '').')';
    }
}
