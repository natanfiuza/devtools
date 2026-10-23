<?php

namespace Database\Factories;

use App\Models\Base64ImageConversion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Base64ImageConversion>
 */
class Base64ImageConversionFactory extends Factory
{
    protected $model = Base64ImageConversion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $id = (string) Str::ulid();

        return [
            'id' => $id,
            'user_id' => User::factory(),
            'filename' => 'sample.png',
            'mime_type' => 'image/png',
            'file_size_bytes' => 1024,
            'base64_size_bytes' => 1400,
            'is_paid_plan' => false,
            'disk_path_original' => storage_path("app/data/base64_images/test/{$id}.original.png"),
            'disk_path_converted' => storage_path("app/data/base64_images/test/{$id}.txt"),
            'file_deleted_at' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_paid_plan' => true,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'file_deleted_at' => now(),
        ]);
    }
}
