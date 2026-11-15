<?php

namespace Database\Factories;

use App\Enums\ColorCommentStatus;
use App\Models\ColorPalette;
use App\Models\ColorPaletteComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ColorPaletteComment>
 */
class ColorPaletteCommentFactory extends Factory
{
    protected $model = ColorPaletteComment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'color_palette_id' => ColorPalette::factory()->public(),
            'parent_id' => null,
            'user_id' => User::factory(),
            'body' => fake()->words(6, true),
            'approved_body' => null,
            'status' => ColorCommentStatus::Pending,
            'locale' => 'pt_BR',
            'revision' => 1,
            'moderation_reason' => null,
            'moderated_at' => null,
            'notified_at' => null,
            'edited_at' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ColorCommentStatus::Approved,
            'approved_body' => $attributes['body'],
            'moderated_at' => now(),
        ]);
    }

    public function flagged(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ColorCommentStatus::Flagged,
            'moderated_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ColorCommentStatus::Rejected,
            'moderated_at' => now(),
        ]);
    }

    /**
     * Uma resposta à raiz informada, na mesma paleta.
     */
    public function replyTo(ColorPaletteComment $parent): static
    {
        return $this->state(fn (array $attributes): array => [
            'color_palette_id' => $parent->color_palette_id,
            'parent_id' => $parent->getKey(),
        ]);
    }

    public function deleted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'deleted_at' => now(),
        ]);
    }
}
