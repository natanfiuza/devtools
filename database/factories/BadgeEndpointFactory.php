<?php

namespace Database\Factories;

use App\Models\BadgeEndpoint;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BadgeEndpoint>
 */
class BadgeEndpointFactory extends Factory
{
    protected $model = BadgeEndpoint::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::ulid(),
            'user_id' => User::factory(),
            'name' => 'endpoint-'.fake()->unique()->numberBetween(1, 999999),
            'url' => 'https://example.com/badge.json',
        ];
    }
}
