<?php

namespace Database\Factories;

use App\Models\BadgeAsset;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BadgeAsset>
 */
class BadgeAssetFactory extends Factory
{
    protected $model = BadgeAsset::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::ulid(),
            'user_id' => User::factory(),
            'name' => 'my-badge',
            'type' => 'static',
            'private' => false,
            'provider_slug' => null,
            'definition_slug' => null,
            'params' => null,
            'form' => [
                'label' => 'build',
                'message' => 'passing',
                'style' => 'flat',
                'font' => 'default',
                'color' => '#44cc11',
                'labelColor' => '#555555',
                'logo' => 'none',
                'logoColor' => '#ffffff',
                'logoSize' => 10,
                'logoPosition' => 'left',
                'link' => '',
                'cacheSeconds' => 86400,
            ],
        ];
    }

    public function static(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => 'static',
            'provider_slug' => null,
            'definition_slug' => null,
            'params' => null,
        ]);
    }

    public function provider(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => 'provider',
            'provider_slug' => 'github',
            'definition_slug' => 'stars',
            'params' => [
                'owner' => 'natanfiuza',
                'repo' => 'devtools',
            ],
        ]);
    }

    public function private(): static
    {
        return $this->state(fn (array $attributes): array => [
            'private' => true,
            'credential' => 'ghp_factory_token',
            'credential_set_at' => now(),
        ]);
    }
}
