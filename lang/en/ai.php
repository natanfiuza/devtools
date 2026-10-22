<?php

/*
| AI SQL Builder (namespace "ai").
|
| A tela traduz o codigo de erro devolvido pela API; a mensagem tecnica do
| servidor e so fallback. Nenhum texto aqui cita modelo ou provedor: isso e
| configuracao, nao interface.
*/

return [
    'form' => [
        'title' => 'Describe the query',
        'schema_source' => 'Schema source',
        'schema' => 'Schema',
        'schema_placeholder' => 'Paste the tables, columns and relationships the query may use.',
        'description' => 'What should the query do?',
        'description_placeholder' => 'Example: list the 10 customers who spent the most in the last 30 days.',
        'dialect' => 'Dialect',
        'target' => 'Output',
        'submit' => 'Generate',
        'submitting' => 'Sending...',
        'schema_limit' => 'Schema: {used} of {limit}',
        'description_limit' => 'Description: {used} of {limit}',
        'over_limit' => 'This is over the limit of your plan.',
        'required' => 'Fill in the schema and the description.',
        'sensitive' => 'Do not paste passwords, tokens or personal data.',
    ],

    'sources' => [
        'ddl' => 'DDL',
        'json' => 'JSON',
        'migration' => 'Laravel migration',
        'models' => 'SQLModel models',
    ],

    'dialects' => [
        'mysql' => 'MySQL',
        'pgsql' => 'PostgreSQL',
        'sqlsrv' => 'SQL Server',
        'sqlite' => 'SQLite',
    ],

    'targets' => [
        'sql' => 'SQL',
        'laravel_query_builder' => 'Laravel Query Builder',
        'eloquent' => 'Eloquent',
        'sqlmodel' => 'SQLModel',
        'sqlalchemy' => 'SQLAlchemy',
        'django_orm' => 'Django ORM',
        'prisma' => 'Prisma',
        'drizzle' => 'Drizzle ORM',
        'typeorm' => 'TypeORM',
    ],

    'states' => [
        'idle' => 'Ready when you are.',
        'queued' => 'In the queue...',
        'processing' => 'Generating...',
        'done' => 'Result ready.',
        'failed' => 'Could not generate the result.',
        'timeout' => 'This is taking longer than expected. You can check again.',
        'retry' => 'Check again',
        'poll_hint' => 'You can keep this tab open; the result appears here.',
    ],

    'guest' => [
        'title' => 'Sign in to use the AI SQL Builder',
        'body' => 'The AI SQL Builder needs a verified account: the schema and the description go through the AI provider and spend the plan quota.',
        'cta' => 'Sign in',
    ],

    'unverified' => [
        'title' => 'Confirm your email to use the AI',
        'body' => 'We sent a confirmation link to your email. Confirm it before generating a query.',
        'cta' => 'Resend confirmation',
        'sent' => 'Confirmation sent. Check your inbox.',
        'failed' => 'Could not send the confirmation. Try again later.',
    ],

    'suspended' => [
        'title' => 'Account suspended',
        'body' => 'This account cannot use the AI SQL Builder right now. Contact support.',
    ],

    'unavailable' => [
        'title' => 'AI unavailable',
        'body' => 'The AI service is not configured right now. Try again later.',
    ],

    'quota' => [
        'title' => 'Quota exhausted',
        'body_uses' => 'You have used all {limit} free uses of this account.',
        'body_tokens' => 'You have used all {limit} tokens of this period.',
        'remaining_uses' => '{remaining} of {limit} uses left',
        'remaining_tokens' => '{remaining} of {limit} tokens left',
        'cta' => 'See plans',
    ],

    'result' => [
        'title' => 'Result',
        'copy' => 'Copy',
        'copied' => 'Copied',
        'download' => 'Download',
        'download_name' => 'query',
        'assumptions' => 'Assumptions',
        'warnings' => 'Warnings',
        'tables' => 'Tables used',
        'tokens_in' => 'Input: {count} tokens',
        'tokens_out' => 'Output: {count} tokens',
        'cache_hit' => 'Cached result',
        'clarification' => 'The model needs more information',
        'empty' => 'Nothing generated yet.',
    ],

    'errors' => [
        'generic' => 'Something went wrong. Try again.',
        'network' => 'Could not reach the server. Check your connection and try again.',
        'validation' => 'Check the form fields and try again.',
        'unauthenticated' => 'Your session expired. Sign in again.',
        'forbidden' => 'This account cannot use the AI SQL Builder.',
        'email_unverified' => 'Confirm your email before using the AI.',
        'account_suspended' => 'This account is suspended.',
        'not_found' => 'Request not found.',
        'csrf' => 'Your session expired. Reload the page and try again.',
        'rate_limited' => 'Too many requests. Try again in {seconds} seconds.',
        'quota_exhausted' => 'Quota exhausted. See the plans to continue.',
        'unavailable' => 'The AI service is unavailable right now.',
        'timeout' => 'The request took too long. Try again.',
        'provider_unavailable' => 'The AI provider is unavailable right now. Try again later.',
        'invalid_response' => 'The AI returned an invalid response.',
        'unsafe_sql' => 'The generated SQL was blocked by the safety check.',
        'invalid_contract' => 'The AI response did not match the expected format.',
        'internal' => 'Internal error while processing the request.',
        'schema_too_large' => 'The schema is over the {limit} limit of your plan.',
        'description_too_long' => 'The description is over the {limit} character limit of your plan.',
        'unsupported_dialect' => 'The selected output does not support this dialect.',
    ],

    'warnings' => [
        'review' => 'Review the result before using it. The query is never executed here.',
        'sensitive' => 'Do not paste passwords, tokens or personal data into the schema.',
    ],

    'history' => [
        'title' => 'Previous Queries',
        'search_placeholder' => 'Search in schema, prompt or title...',
        'loading' => 'Loading history...',
        'error' => 'Unable to load query history.',
        'empty' => 'No queries made yet.',
        'no_search_results' => 'No queries found for "{query}".',
        'untitled' => 'Untitled query',
        'collapse' => 'Collapse history',
        'expand' => 'Expand history',
    ],
];
