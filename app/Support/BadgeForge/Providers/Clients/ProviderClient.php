<?php

namespace App\Support\BadgeForge\Providers\Clients;

use App\Models\BadgeProviderDefinition;
use App\Support\BadgeForge\Providers\ProviderFetchException;
use App\Support\BadgeForge\Providers\ProviderFetchRequest;
use App\Support\BadgeForge\Providers\ProviderPayload;

/**
 * Cliente de um provider externo (F4, ADR-080).
 *
 * `key()` e a ponte com `badge_provider_definitions.client_key`; o banco
 * nunca escolhe uma classe, apenas um rotulo conhecido pelo registry.
 */
interface ProviderClient
{
    public function key(): string;

    /**
     * @throws ProviderFetchException
     */
    public function fetch(ProviderFetchRequest $request): ProviderPayload;

    public function supportsPrivate(BadgeProviderDefinition $definition): bool;
}
