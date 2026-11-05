<?php

namespace Database\Factories;

use App\Enums\BadgeViewSubject;
use App\Models\BadgeView;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BadgeView>
 */
class BadgeViewFactory extends Factory
{
    protected $model = BadgeView::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject_type' => BadgeViewSubject::Personal,
            'subject_id' => (string) Str::ulid(),
            'user_id' => User::factory(),
            'definition' => null,
            'ip_hash' => hash('sha256', fake()->ipv4()),
            'user_agent_hash' => hash('sha256', fake()->userAgent()),
            'user_agent' => 'Mozilla/5.0',
            'referer_domain' => 'example.com',
            'country' => 'BR',
            'format' => 'svg',
            'style' => 'flat',
            'cache_hit' => null,
            'viewed_at' => now(),
        ];
    }
}
