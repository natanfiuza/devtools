<?php

namespace App\Support\BadgeForge\Endpoint;

use RuntimeException;

/**
 * Corpo da origem estourou `endpoint.max_body_bytes` durante o download
 * (F5, ADR-084). O cliente converte em `too_large`.
 */
final class ResponseBodyTooLarge extends RuntimeException {}
