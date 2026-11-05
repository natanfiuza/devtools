<?php

namespace Database\Factories;

use App\Enums\BadgeViewSubject;
use App\Models\BadgeViewDaily;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BadgeViewDaily>
 */
class BadgeViewDailyFactory extends Factory
{
    protected $model = BadgeViewDaily::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject_type' => BadgeViewSubject::Personal,
            'subject_id' => (string) Str::ulid(),
            'user_id' => User::factory(),
            'day' => now()->toDateString(),
            'definition' => '',
            'country' => 'BR',
            'referer_domain' => 'example.com',
            'format' => 'svg',
            'views' => 1,
            'unique_visitors' => 1,
            'cache_hits' => 0,
        ];
    }
}
