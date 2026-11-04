<?php

namespace Database\Factories;

use App\Models\BadgeProvider;
use App\Models\BadgeProviderDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BadgeProviderDefinition>
 */
class BadgeProviderDefinitionFactory extends Factory
{
    protected $model = BadgeProviderDefinition::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider_id' => BadgeProvider::factory(),
            'slug' => $this->faker->unique()->slug(2),
            'name' => 'GitHub stars',
            'label' => 'stars',
            'client_key' => 'github',
            'extractor_key' => 'stars',
            'path_pattern' => '{owner}/{repo}',
            'required_params' => [
                ['name' => 'owner', 'pattern' => '[A-Za-z0-9-]{1,39}', 'allowSlash' => false, 'placeholderKey' => 'owner'],
                ['name' => 'repo', 'pattern' => '[A-Za-z0-9._-]{1,100}', 'allowSlash' => false, 'placeholderKey' => 'repo'],
            ],
            'optional_params' => null,
            'url_template' => 'https://api.github.com/repos/{owner}/{repo}',
            'response_type' => 'json',
            'parser_class' => null,
            'supports_private' => false,
            'example_path' => 'natanfiuza/devtools',
            'sort' => 0,
            'enabled' => true,
        ];
    }
}
