<?php

namespace Database\Factories;

use App\Models\ColorTag;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ColorTag>
 */
class ColorTagFactory extends Factory
{
    protected $model = ColorTag::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'id' => (string) Str::ulid(),
            'user_id' => User::factory(),
            'name' => $name,
            'key' => Str::lower($name),
            'position' => 0,
        ];
    }
}
