<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    /**
     * Um plano pago mensal. O Free nao sai daqui: ele nasce na migracao do
     * catalogo e e encontrado por `Plan::free()`.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(2),
            'name' => 'Pro',
            'price_cents' => 2990,
            'currency' => 'BRL',
            'interval' => 'month',
            'monthly_tokens' => 2000000,
            'max_schema_bytes' => 65536,
            'max_prompt_chars' => 4000,
            'max_image_upload_bytes' => 26214400,
            'lifetime_free_uses' => 0,
            'active' => true,
            'sort' => 10,
        ];
    }

    public function yearly(): static
    {
        return $this->state(fn (array $attributes): array => [
            'interval' => 'year',
            'price_cents' => 29900,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'active' => false,
        ]);
    }
}
