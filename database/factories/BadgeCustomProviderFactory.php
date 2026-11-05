<?php

namespace Database\Factories;

use App\Models\BadgeCustomProvider;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BadgeCustomProvider>
 */
class BadgeCustomProviderFactory extends Factory
{
    protected $model = BadgeCustomProvider::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::ulid(),
            'user_id' => User::factory(),
            'name' => 'custom-'.fake()->unique()->numberBetween(1, 999999),
            'url_template' => 'https://example.com/repos/{owner}/{repo}.json',
            'path_params' => ['owner', 'repo'],
            'example_path' => 'natanfiuza/devtools',
            'metrics_version' => 1,
            'enabled' => true,
        ];
    }
}
