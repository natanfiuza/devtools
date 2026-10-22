<?php

namespace App\Support\Ai;

use App\Enums\SqlDialect;

/**
 * O contrato do SPEC ja validado e normalizado.
 *
 * O banco guarda `toArray()` em `response_json`; a tela e o cache leem sempre
 * esta forma, nunca o texto cru do provedor.
 *
 * @phpstan-type ResultArray array{
 *     title: string|null,
 *     explanation: string|null,
 *     sql: string,
 *     dialect: string,
 *     assumptions: list<string>,
 *     warnings: list<string>,
 *     tables_used: list<string>,
 *     needs_clarification: bool,
 *     clarification: string|null,
 *     outputs: array<string, string>
 * }
 */
final readonly class AiResult
{
    /**
     * @param  list<string>  $assumptions
     * @param  list<string>  $warnings
     * @param  list<string>  $tablesUsed
     * @param  array<string, string>  $outputs
     */
    public function __construct(
        public string $sql,
        public SqlDialect $dialect,
        public array $assumptions,
        public array $warnings,
        public array $tablesUsed,
        public bool $needsClarification,
        public ?string $clarification,
        public array $outputs,
        public ?string $title = null,
        public ?string $explanation = null,
    ) {}

    /**
     * @return ResultArray
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'explanation' => $this->explanation,
            'sql' => $this->sql,
            'dialect' => $this->dialect->value,
            'assumptions' => $this->assumptions,
            'warnings' => $this->warnings,
            'tables_used' => $this->tablesUsed,
            'needs_clarification' => $this->needsClarification,
            'clarification' => $this->clarification,
            'outputs' => $this->outputs,
        ];
    }

    public function json(): string
    {
        return json_encode(
            $this->toArray(),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }
}
