<?php

use App\Models\BadgeProviderDefinition;
use App\Support\BadgeForge\Providers\ProviderClientRegistry;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

uses(TestCase::class);

it('resolve cada client_key conhecido', function () {
    $registry = app(ProviderClientRegistry::class);

    foreach (['github', 'npm', 'packagist', 'docker'] as $key) {
        $definition = new BadgeProviderDefinition(['client_key' => $key]);

        expect($registry->for($definition)?->key())->toBe($key);
    }
});

it('devolve null e loga apenas slugs para client_key desconhecido', function () {
    Log::spy();

    $definition = new BadgeProviderDefinition([
        'provider_id' => 7,
        'slug' => 'stars',
        'client_key' => 'unknown-client',
    ]);

    expect(app(ProviderClientRegistry::class)->for($definition))->toBeNull();

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => $message === 'badgeforge.provider.unknown_client'
            && $context === ['provider_id' => 7, 'definition' => 'stars', 'client_key' => 'unknown-client']);
});
