<?php

namespace Database\Factories;

use App\Models\ColorPalette;
use App\Models\ColorPaletteMute;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ColorPaletteMute>
 */
class ColorPaletteMuteFactory extends Factory
{
    protected $model = ColorPaletteMute::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'color_palette_id' => ColorPalette::factory()->public(),
            'user_id' => User::factory(),
        ];
    }
}
