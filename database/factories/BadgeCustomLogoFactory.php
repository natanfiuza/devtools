<?php

namespace Database\Factories;

use App\Models\BadgeCustomLogo;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BadgeCustomLogo>
 */
class BadgeCustomLogoFactory extends Factory
{
    protected $model = BadgeCustomLogo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::ulid(),
            'user_id' => User::factory(),
            'name' => 'badge-logo',
            'view_box' => '0 0 24 24',
            'paths' => [
                ['d' => 'M12 2L2 22h20z'],
            ],
            'path_count' => 1,
            'size_bytes' => 64,
        ];
    }

    public function multipath(): static
    {
        return $this->state(fn (array $attributes): array => [
            'paths' => [
                ['d' => 'M2 2h20v20H2z', 'fillRule' => 'evenodd'],
                ['d' => 'M7 7h10v10H7z', 'fillRule' => 'evenodd', 'clipRule' => 'evenodd'],
            ],
            'path_count' => 2,
        ]);
    }
}
