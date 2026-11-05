<?php

namespace Database\Factories;

use App\Models\BadgeProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BadgeProvider>
 */
class BadgeProviderFactory extends Factory
{
    protected $model = BadgeProvider::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => $this->faker->unique()->slug(2),
            'name' => 'Example provider',
            'category' => 'vcs',
            'enabled' => true,
            'requires_auth' => false,
            'auth_type' => null,
            'base_url' => null,
            'cache_ttl' => null,
            'documentation_url' => null,
            'rate_limit_per_minute' => null,
            'config' => null,
        ];
    }

    public function github(): static
    {
        return $this->state(fn (array $attributes): array => [
            'slug' => 'github',
            'name' => 'GitHub',
            'category' => 'vcs',
            'requires_auth' => true,
            'auth_type' => 'bearer',
            'base_url' => 'https://api.github.com',
            'documentation_url' => 'https://docs.github.com/en/rest/repos/repos',
        ]);
    }
}
