<?php

namespace Database\Factories;

use App\Enums\PaletteKind;
use App\Enums\PaletteVisibility;
use App\Models\ColorPalette;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ColorPalette>
 */
class ColorPaletteFactory extends Factory
{
    protected $model = ColorPalette::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $colors = self::scaleColors();

        return [
            'id' => (string) Str::ulid(),
            'user_id' => User::factory(),
            'duplicated_from_id' => null,
            'name' => fake()->unique()->words(3, true),
            'description' => null,
            'visibility' => PaletteVisibility::Private,
            'kind' => PaletteKind::Scale,
            'algorithm_version' => (int) config('colors.algorithm_versions.scale', 1),
            'colors' => $colors,
            'roles' => self::rolesFromScale($colors['items']),
            'source' => null,
            'colors_count' => count($colors['items']),
            'duplications_count' => 0,
            'slug' => null,
            'archived_slug' => null,
            'archived_at' => null,
            'og_version' => null,
        ];
    }

    public function public(): static
    {
        return $this->state(fn (array $attributes): array => [
            'visibility' => PaletteVisibility::Public,
            'slug' => fake()->unique()->slug(2),
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes): array => [
            'archived_at' => now(),
        ]);
    }

    public function extracted(): static
    {
        $items = [
            ['hex' => '#112233'],
            ['hex' => '#334455'],
            ['hex' => '#556677'],
            ['hex' => '#778899'],
            ['hex' => '#99aabb'],
            ['hex' => '#bbccdd'],
            ['hex' => '#ddeeff'],
        ];

        return $this->state(fn (array $attributes): array => [
            'kind' => PaletteKind::Extracted,
            'algorithm_version' => (int) config('colors.algorithm_versions.extraction', 1),
            'colors' => ['base' => '#334455', 'items' => $items],
            'roles' => self::rolesFromExtracted(),
            'source' => ['kind' => 'file', 'label' => 'foto.png'],
            'colors_count' => count($items),
        ]);
    }

    /**
     * @return array{base: string, items: list<array{hex: string, tone: int}>}
     */
    public static function scaleColors(): array
    {
        $tones = [
            50 => '#eef2ff',
            100 => '#e0e7ff',
            200 => '#c7d2fe',
            300 => '#a5b4fc',
            400 => '#818cf8',
            500 => '#6366f1',
            600 => '#4f46e5',
            700 => '#4338ca',
            800 => '#3730a3',
            900 => '#312e81',
            950 => '#1e1b4b',
        ];

        $items = [];

        foreach ($tones as $tone => $hex) {
            $items[] = ['hex' => $hex, 'tone' => $tone];
        }

        return ['base' => '#6366f1', 'items' => $items];
    }

    /**
     * @param  list<array{hex: string, tone: int}>  $items
     * @return array{light: array<string, string>, dark: array<string, string>}
     */
    public static function rolesFromScale(array $items): array
    {
        $byTone = [];

        foreach ($items as $item) {
            $byTone[$item['tone']] = $item['hex'];
        }

        return [
            'light' => [
                'primary' => $byTone[600],
                'secondary' => $byTone[700],
                'accent' => $byTone[400],
                'background' => $byTone[50],
                'surface' => $byTone[100],
                'text' => $byTone[950],
                'text_muted' => $byTone[600],
                'border' => $byTone[200],
            ],
            'dark' => [
                'primary' => $byTone[600],
                'secondary' => $byTone[700],
                'accent' => $byTone[400],
                'background' => $byTone[950],
                'surface' => $byTone[900],
                'text' => $byTone[50],
                'text_muted' => $byTone[400],
                'border' => $byTone[800],
            ],
        ];
    }

    /**
     * @return array{light: array<string, string>, dark: array<string, string>}
     */
    public static function rolesFromExtracted(): array
    {
        return [
            'light' => [
                'primary' => '#112233',
                'secondary' => '#334455',
                'accent' => '#556677',
                'background' => '#ffffff',
                'surface' => '#f4f4f5',
                'text' => '#18181b',
                'text_muted' => '#52525b',
                'border' => '#e4e4e7',
            ],
            'dark' => [
                'primary' => '#112233',
                'secondary' => '#334455',
                'accent' => '#556677',
                'background' => '#09090b',
                'surface' => '#18181b',
                'text' => '#fafafa',
                'text_muted' => '#a1a1aa',
                'border' => '#27272a',
            ],
        ];
    }
}
