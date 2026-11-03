<?php

namespace Database\Factories;

use App\Models\BadgePreset;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BadgePreset>
 */
class BadgePresetFactory extends Factory
{
    protected $model = BadgePreset::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::ulid(),
            'user_id' => User::factory(),
            'name' => 'build-passing',
            'payload' => [
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
}
