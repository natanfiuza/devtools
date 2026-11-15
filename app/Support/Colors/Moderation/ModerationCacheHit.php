<?php

namespace App\Support\Colors\Moderation;

use App\Models\AiCache;

/**
 * Linha de cache de moderacao ja revalidada (F7, ADR-140).
 */
final readonly class ModerationCacheHit
{
    public function __construct(
        public AiCache $cache,
        public ModerationResult $result,
    ) {}
}
