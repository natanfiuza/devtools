<?php

namespace Database\Factories;

use App\Models\ColorPreset;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ColorPreset>
 */
class ColorPresetFactory extends Factory
{
    protected $model = ColorPreset::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::ulid(),
            'user_id' => User::factory(),
            'name' => fake()->unique()->words(2, true),
            'base' => fake()->hexColor(),
        ];
    }
}
