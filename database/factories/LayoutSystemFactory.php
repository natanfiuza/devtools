<?php

namespace Database\Factories;

use App\Enums\PaletteVisibility;
use App\Models\LayoutSystem;
use App\Models\User;
use App\Support\Colors\LayoutSystemCatalog;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LayoutSystem>
 */
class LayoutSystemFactory extends Factory
{
    protected $model = LayoutSystem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::ulid(),
            'user_id' => User::factory(),
            'duplicated_from_id' => null,
            'source_palette_id' => null,
            'name' => fake()->unique()->words(3, true),
            'description' => null,
            'visibility' => PaletteVisibility::Private,
            'state' => self::stateArray(),
            'source' => null,
            'schema_version' => 1,
            'algorithm_version' => 1,
            'suggestions_version' => 1,
            'snapshot_version' => 1,
            'tokens_count' => 66,
            'duplications_count' => 0,
            'slug' => null,
            'archived_slug' => null,
            'archived_at' => null,
        ];
    }

    public function public(): static
    {
        return $this->state(fn (array $attributes): array => [
            'visibility' => PaletteVisibility::Public,
            'slug' => fake()->unique()->slug(3),
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes): array => [
            'archived_at' => now(),
        ]);
    }

    /**
     * Estado v1 valido, com 33 tokens e 16 checks por tema.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function stateArray(array $overrides = []): array
    {
        $state = [
            'stateVersion' => 1,
            'inputs' => [
                'seedText' => '#3b82f6',
                'backgroundText' => '',
                'secondaryText' => '',
                'strategy' => 'analogous',
            ],
            'secondary' => null,
            'snapshot' => self::snapshotArray(),
        ];

        return array_replace_recursive($state, $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    public static function snapshotArray(): array
    {
        $hexes = ['light' => '#3b82f6', 'dark' => '#60a5fa'];

        $themes = [];

        foreach ($hexes as $theme => $hex) {
            $tokens = [];

            foreach (LayoutSystemCatalog::TOKENS as $spec) {
                $tokens[] = [
                    'id' => $spec['id'],
                    'family' => $spec['family'],
                    'theme' => $theme,
                    'rgba' => ['r' => 59, 'g' => 130, 'b' => 246, 'a' => 1],
                    'oklch' => ['l' => 0.62, 'c' => 0.19, 'h' => 259],
                    'hex' => $hex,
                    'source' => 'seed',
                    'clipped' => false,
                ];
            }

            $checks = [];

            foreach (LayoutSystemCatalog::PAIRS as $pair) {
                $checks[] = [
                    'foreground' => $pair['foreground'],
                    'background' => $pair['background'],
                    'verdict' => [
                        'ratio' => 4.5,
                        'aaNormal' => true,
                        'aaaNormal' => false,
                        'aaLarge' => true,
                        'aaaLarge' => false,
                    ],
                ];
            }

            $themes[] = ['theme' => $theme, 'tokens' => $tokens, 'checks' => $checks];
        }

        return [
            'snapshotVersion' => 1,
            'schemaVersion' => 1,
            'algorithmVersion' => 1,
            'suggestionsVersion' => 1,
            'target' => 'aa',
            'seed' => [
                'original' => ['r' => 59, 'g' => 130, 'b' => 246, 'a' => 1],
                'effective' => ['r' => 59, 'g' => 130, 'b' => 246, 'a' => 1],
                'background' => null,
                'achromatic' => false,
            ],
            'strategy' => 'analogous',
            'secondarySource' => 'auto',
            'themes' => $themes,
            'overrides' => [],
            'locks' => [],
            'acceptedAdjustments' => [],
            'diagnostics' => [],
        ];
    }
}
