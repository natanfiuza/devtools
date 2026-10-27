<?php

namespace Database\Factories;

use App\Models\SharedText;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SharedText>
 */
class SharedTextFactory extends Factory
{
    protected $model = SharedText::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $id = (string) Str::ulid();

        return [
            'id' => $id,
            'user_id' => User::factory(),
            'file_path' => storage_path("app/data/sharetext/test/{$id}.txt"),
            'size_bytes' => 128,
            'extension' => null,
            'expires_at' => null,
        ];
    }

    public function anonymous(): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => null,
            'file_path' => storage_path("app/data/sharetext/anonymous/{$attributes['id']}.txt"),
            'expires_at' => now()->addHours(72),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => now()->subMinute(),
        ]);
    }
}
