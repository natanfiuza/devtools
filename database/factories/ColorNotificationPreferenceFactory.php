<?php

namespace Database\Factories;

use App\Models\ColorNotificationPreference;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ColorNotificationPreference>
 */
class ColorNotificationPreferenceFactory extends Factory
{
    protected $model = ColorNotificationPreference::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            ...ColorNotificationPreference::defaults(),
        ];
    }

    public function allEnabled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'email_on_like' => true,
            'email_on_follow' => true,
            'email_on_comment' => true,
            'email_on_reply' => true,
        ]);
    }
}
