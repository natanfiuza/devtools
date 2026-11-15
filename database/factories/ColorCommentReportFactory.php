<?php

namespace Database\Factories;

use App\Enums\ColorCommentReportReason;
use App\Enums\ColorCommentReportStatus;
use App\Models\ColorCommentReport;
use App\Models\ColorPaletteComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ColorCommentReport>
 */
class ColorCommentReportFactory extends Factory
{
    protected $model = ColorCommentReport::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'comment_id' => ColorPaletteComment::factory()->approved(),
            'user_id' => User::factory(),
            'resolved_by' => null,
            'reason' => fake()->randomElement(ColorCommentReportReason::cases()),
            'status' => ColorCommentReportStatus::Pending,
            'reported_at' => now(),
            'resolved_at' => null,
        ];
    }

    public function resolved(User $admin): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ColorCommentReportStatus::Resolved,
            'resolved_by' => $admin->getKey(),
            'resolved_at' => now(),
        ]);
    }

    public function dismissed(User $admin): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ColorCommentReportStatus::Dismissed,
            'resolved_by' => $admin->getKey(),
            'resolved_at' => now(),
        ]);
    }
}
