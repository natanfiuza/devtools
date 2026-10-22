<?php

namespace App\Support\Payments;

/**
 * Preferencia de checkout criada no provedor.
 */
final readonly class GatewayPreference
{
    public function __construct(
        public string $id,
        public ?string $initPoint,
    ) {}
}
