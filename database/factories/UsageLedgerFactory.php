<?php

namespace Database\Factories;

use App\Enums\LedgerEntryType;
use App\Models\UsageLedger;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UsageLedger>
 */
class UsageLedgerFactory extends Factory
{
    /**
     * Um uso consumido: a requisicao aconteceu (1 uso) e custou tantos tokens.
     * Cada plano le a coluna que lhe cabe - o Free conta usos, os pagos contam
     * tokens (App\Support\Quota).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'ai_request_id' => null,
            'ai_request_ulid' => null,
            'type' => LedgerEntryType::Debit,
            'tokens' => 1000,
            'uses' => 1,
            'created_at' => now(),
        ];
    }

    public function refund(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => LedgerEntryType::Refund,
        ]);
    }

    /**
     * Concessao manual de cota pelo admin.
     */
    public function grant(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => LedgerEntryType::Grant,
        ]);
    }
}
