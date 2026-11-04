<?php

/*
| Documentacao publica do BadgeForge (`/{locale}/docs/badgeforge`, ADR-082).
|
| Os nomes de provider e os nomes de metrica sao marcas/nomes proprios e nao
| se traduzem: o texto da metrica vem de `tools.badgeforge.provider.metrics.*`.
| As traducoes abaixo cobrem apenas o texto explicativo da pagina.
*/

return [

    'badgeforge' => [

        'meta' => [
            'title' => 'BadgeForge documentation',
            'description' => 'Dynamic badges for GitHub stars, npm versions, Packagist downloads, Docker pulls, Codecov and Coveralls coverage and Laravel, PHP and Python runtime versions, plus personal badges with a credential bound to each badge.',
        ],

        'hero' => [
            'eyebrow' => 'BadgeForge',
            'title' => 'Badges that update themselves',
            'subtitle' => 'Static badges are drawn on the server. Provider badges read GitHub, npm, Packagist, Docker Hub, Codecov, Coveralls and the latest Laravel, PHP and Python versions anonymously, with a cached response that never breaks the image in your README.',
            'open_tool' => 'Open the builder',
        ],

        'endpoints' => [
            'title' => 'Endpoints',
            'static' => 'Static badges',
            'provider' => 'Provider badges',
            'personal' => 'Personal badges',
            'json' => 'JSON endpoint badges',
        ],

        'quick_start' => [
            'title' => 'Quick start',
            'body' => 'Paste the Markdown into your README. GitHub renders the SVG directly and every request is cached on our side.',
            'copy' => 'Copy',
            'copied' => 'Copied',
        ],

        'static' => [
            'title' => 'Static badges',
            'body' => 'Label and message are part of the URL. Every style, colour, logo and bundled font from the builder is available in the query string.',
            'example_label' => 'Example',
        ],

        'providers' => [
            'title' => 'Provider badges',
            'intro' => 'Each provider exposes a short list of metrics. The path after the metric follows the provider itself: repository, package or image coordinates.',
            'metric' => 'Metric',
            'path' => 'Path',
            'access' => 'Access',
            'public' => 'Public',
            'private' => 'Private item',
            'documentation' => 'Provider API documentation',
            'unknown_note' => 'A metric that does not exist, or a response the provider does not recognise, renders the badge as “unknown”.',
            'descriptions' => [
                'github' => 'Stars, forks, open issues and license from the repository API. Fine-grained tokens work for private repositories.',
                'npm' => 'Latest published version and monthly download counts. Download counts are always public.',
                'packagist' => 'Total downloads and the latest version of a Composer package, always from the public registry.',
                'docker' => 'Pull counts from Docker Hub. Private images use the username plus a personal access token.',
                'codecov' => 'Line coverage for the default branch, read from the Codecov API. The badge is always public.',
                'coveralls' => 'Coverage percentage for the default branch, read from the Coveralls JSON endpoint. Public only.',
                'laravel' => 'Latest stable Laravel release and total downloads from Packagist. Both metrics share one cached request.',
                'php' => 'Latest stable PHP release published on php.net. Public and anonymous.',
                'python' => 'Latest stable Python release, read from endoflife.date. Public and anonymous.',
            ],
        ],

        'personal' => [
            'title' => 'Personal badges',
            'intro' => 'Paid accounts publish their own badge at an opaque URL. The URL contains your username and the badge ULID — never a token.',
            'url_label' => 'URL format',
            'username_required' => 'Set a username on your account before creating a personal badge.',
            'providers_title' => 'Private items',
            'providers_body' => 'A personal badge may read a private repository, package or image. The credential is yours, is stored encrypted and is bound to that badge only.',
            'github' => 'GitHub: a personal access token with read access to the repository (fine-grained or classic).',
            'npm' => 'npm: a token for a private package. Download counts (dm) are always public.',
            'docker' => 'Docker Hub: username plus a personal access token; both are required for private images.',
            'packagist' => 'Packagist: public only. Private packages are not supported.',
            'security_title' => 'How the credential is stored',
            'security_body' => 'The token is encrypted with the application key and never appears in the image URL, page props, logs or exports. If the application key is replaced, the badge keeps serving the last good image until you set the credential again.',
            'manage_cta' => 'Manage personal badges',
            'tool_cta' => 'Create a personal badge in the builder',
            'refresh_note' => 'The image is stateless. To force a new generation use the refresh action in your dashboard; the public URL ignores ?refresh=1.',
        ],

        'endpoint' => [
            'title' => 'JSON endpoint badges',
            'intro' => 'Register an HTTPS URL in your dashboard and get an opaque badge URL. The source URL never appears in the image URL, and readers of your README do not need an account to see the badge.',
            'url_label' => 'URL format',
            'contract_title' => 'Expected JSON',
            'contract_body' => 'The source must answer with shields.io schema version 1: schemaVersion 1, non-empty label and message, and optional color/labelColor used as defaults — a query parameter always wins. Other fields are ignored.',
            'cache_title' => 'Cache and fallback',
            'cache_body' => 'The response is cached for {ttl} seconds. If the source is offline or rate limited, the last good response keeps being served (STALE); with no previous response the badge shows “unavailable”, never an error.',
            'limits_title' => 'Rate limit',
            'limits_anonymous' => 'Unknown or anonymous badge: {limit} requests per minute per IP.',
            'limits_free' => 'Free account owner: {limit} requests per minute.',
            'limits_paid' => 'Paid account owner: {limit} requests per minute.',
            'security_title' => 'Why it is safe',
            'security_body' => 'Only HTTPS on port 443, no redirects, and every resolved IP must be public. The connection is pinned to the validated IP, so a DNS change between the check and the fetch cannot reach an internal address. The source URL stays on the server: it is not in the page, the logs or the image URL.',
            'manage_cta' => 'Manage JSON endpoints',
        ],
        'cache' => [
            'title' => 'Cache, ETag and stale responses',
            'body' => 'Provider responses are cached for {ttl} seconds. The image is served with ETag and Cache-Control, so a second request is answered with 304 when nothing changed.',
            'stale_title' => 'Stale-if-error',
            'stale_body' => 'When the provider fails or rate limits us, the last good response keeps being served. With no previous response, the badge shows a short fallback message instead of an error.',
            'refresh_title' => 'Refresh',
            'refresh_body' => 'Public provider URLs never force a refresh. Personal badges have a refresh action that only the owner can trigger.',
        ],

        'errors' => [
            'title' => 'Errors and fallbacks',
            'intro' => 'A badge URL always answers with an image. The exceptions are a path that does not exist (404) and a PNG request on a server without Imagick (503).',
            'cached' => 'Provider offline or rate limited: the cached image is served (header X-Badge-Cache: STALE).',
            'fallback' => 'No cached image yet: the badge renders the metric label with “unavailable”.',
            'unknown' => 'The provider answered but the metric cannot be read: the badge renders “unknown”.',
            'not_found' => 'Unknown provider, unknown metric or invalid path: 404.',
            'private' => 'A private item without a credential is never served by a public provider URL.',
        ],

        'security' => [
            'title' => 'Security and privacy',
            'anonymous' => 'Public metrics are read anonymously. There is no global provider token in configuration or environment.',
            'credential' => 'Credentials belong to the badge owner, are encrypted at rest and are scoped to a single badge.',
            'logs' => 'Provider responses are projected before caching: only the numbers the badge shows are stored. Descriptions, READMEs and tokens never reach the cache or the logs.',
            'url' => 'Personal badge URLs carry only the username and the ULID; revoking access means deleting the badge.',
        ],

    ],

];
