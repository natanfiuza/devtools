<?php

namespace Database\Factories;

use App\Enums\SqlDialect;
use App\Models\AiCache;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiCache>
 */
class AiCacheFactory extends Factory
{
    /**
     * Uma resposta valida ja cacheada, com o payload canonico comprimido.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $result = [
            'sql' => 'SELECT id, name FROM users',
            'dialect' => SqlDialect::Mysql->value,
            'assumptions' => [],
            'warnings' => [],
            'tables_used' => ['users'],
            'needs_clarification' => false,
            'clarification' => null,
            'outputs' => ['sql' => 'SELECT id, name FROM users'],
        ];

        $payload = gzcompress('{}', 6);

        return [
            'prompt_hash' => md5(fake()->uuid()),
            'canonical_payload' => base64_encode($payload === false ? '' : $payload),
            'response_json' => json_encode($result, JSON_THROW_ON_ERROR),
            'model' => (string) config('ai.model', 'test-model'),
            'prompt_version' => 1,
            'tokens_in' => 800,
            'tokens_out' => 200,
            'hits' => 0,
            'last_hit_at' => null,
            'created_at' => now(),
            'expires_at' => now()->addDays(30),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => now()->subDay(),
        ]);
    }
}
