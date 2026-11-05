<?php

namespace Database\Factories;

use App\Enums\CustomMetricFormatter;
use App\Models\BadgeCustomProvider;
use App\Models\BadgeCustomProviderDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BadgeCustomProviderDefinition>
 */
class BadgeCustomProviderDefinitionFactory extends Factory
{
    protected $model = BadgeCustomProviderDefinition::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::ulid(),
            'provider_id' => BadgeCustomProvider::factory(),
            'slug' => 'metric-'.fake()->unique()->numberBetween(1, 999999),
            'label' => 'stars',
            'pointer' => '/stargazers_count',
            'formatter' => CustomMetricFormatter::Number,
            'sort' => 0,
        ];
    }
}
