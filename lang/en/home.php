<?php

return [
    'title' => 'Developer tools that stay out of your way',
    'subtitle' => 'Format, convert and inspect data right in the browser. No sign-up, nothing sent to a server.',

    'tools' => [
        'title' => 'Tools',
        'subtitle' => 'Everything runs in your browser: whatever you paste here never leaves the machine.',
        'filter' => 'Filter by category',
        'all' => 'All',
        'empty' => 'No tool in this category yet.',
    ],

    'ai' => [
        'badge' => 'Coming soon',
        'title' => 'AI SQL Builder',
        'body' => 'Describe the query in plain language and get SQL for your database, plus Eloquent, Prisma and the rest of your stack.',
        'note' => 'Needs a verified account: the query goes through the model and spends the plan quota.',
        'preview_prompt' => 'List the 10 customers who bought the most in the last 30 days',
        'preview_sql' => "SELECT c.name, SUM(o.total) AS spent\nFROM customers c\nJOIN orders o ON o.customer_id = c.id\nWHERE o.created_at >= NOW() - INTERVAL '30 days'\nGROUP BY c.name\nORDER BY spent DESC\nLIMIT 10;",
    ],

    'cta' => [
        'register' => 'Create account',
        'contact' => 'Talk by e-mail',
    ],

    'pricing' => [
        'title' => 'Plans',
        'subtitle' => 'No automatic renewal: billing is arranged by e-mail and the plan runs until the end of the period.',
        'free' => 'Free',
        'interval' => [
            'month' => 'per month',
            'year' => 'per year',
        ],
        'features' => [
            'uses' => '{count} lifetime uses',
            'tokens' => '{count} tokens per month',
            'schema' => 'Schema up to {size}',
            'prompt' => 'Description up to {count} characters',
        ],
    ],
];
