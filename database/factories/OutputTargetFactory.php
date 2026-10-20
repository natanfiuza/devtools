<?php

namespace Database\Factories;

use App\Enums\SqlDialect;
use App\Models\OutputTarget;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OutputTarget>
 */
class OutputTargetFactory extends Factory
{
    /**
     * Alvo generico para teste. O catalogo real nasce na migracao de seed; este
     * existe para os testes que precisam de um alvo isolado.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->bothify('target-####??'),
            'label' => fake()->words(2, true),
            'language' => 'sql',
            'supported_dialects' => SqlDialect::values(),
            'prompt_template' => 'sql.v1.md',
            'enabled' => true,
            'plan_required' => null,
            'sort' => 0,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'enabled' => false,
        ]);
    }

    /**
     * @param  list<string>  $dialects
     */
    public function onlyDialects(array $dialects): static
    {
        return $this->state(fn (array $attributes): array => [
            'supported_dialects' => $dialects,
        ]);
    }
}
