<?php

namespace Database\Factories;

use App\Enums\AiFailureCode;
use App\Enums\AiRequestStatus;
use App\Enums\QuotaUnit;
use App\Enums\SchemaSource;
use App\Enums\SqlDialect;
use App\Models\AiRequest;
use App\Models\OutputTarget;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiRequest>
 */
class AiRequestFactory extends Factory
{
    /**
     * Uma solicitacao paga na fila, com a reserva estimada ja debitada.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'output_target_id' => OutputTarget::factory(),
            'status' => AiRequestStatus::Queued,
            'locale' => 'en',
            'dialect' => SqlDialect::Mysql,
            'schema_source' => SchemaSource::Ddl,
            'prompt_hash' => md5(fake()->uuid()),
            'prompt_version' => 1,
            'prompt_template' => 'sql.v1.md',
            'model' => (string) config('ai.model', 'test-model'),
            'estimated_tokens' => 1200,
            'reserved_tokens' => 1200,
            'reserved_uses' => 0,
            'quota_unit' => QuotaUnit::Tokens,
            'tokens_in' => null,
            'tokens_out' => null,
            'cache_hit' => false,
            'error_code' => null,
            'history_visible' => false,
            'canonical_payload' => null,
            'response_json' => null,
        ];
    }

    /**
     * Solicitacao do Free: a reserva e em usos, nao em tokens.
     */
    public function free(): static
    {
        return $this->state(fn (array $attributes): array => [
            'estimated_tokens' => 0,
            'reserved_tokens' => 0,
            'reserved_uses' => 1,
            'quota_unit' => QuotaUnit::Uses,
        ]);
    }

    public function done(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AiRequestStatus::Done,
            'tokens_in' => 800,
            'tokens_out' => 200,
            'canonical_payload' => null,
            'response_json' => json_encode([
                'sql' => 'SELECT id, name FROM users',
                'dialect' => SqlDialect::Mysql->value,
                'assumptions' => [],
                'warnings' => [],
                'tables_used' => ['users'],
                'needs_clarification' => false,
                'clarification' => null,
                'outputs' => ['sql' => 'SELECT id, name FROM users'],
            ], JSON_THROW_ON_ERROR),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AiRequestStatus::Failed,
            'error_code' => AiFailureCode::Internal,
            'canonical_payload' => null,
        ]);
    }
}
