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
        'subscribe' => 'Subscribe now',
    ],

    'pricing' => [
        'title' => 'Plans',
        'subtitle' => 'Pick the plan that fits your workflow. No hidden auto-renewal: submit your subscription intent and claim your exclusive handle.',
        'free' => 'Free',
        'yearly_badge' => '2 months free',
        'interval' => [
            'month' => 'per month',
            'year' => 'per year',
        ],
        'features' => [
            'uses' => '{count} lifetime uses',
            'tokens' => '{count} tokens per month',
            'schema' => 'Schema up to {size}',
            'prompt' => 'Description up to {count} characters',
            'username' => "Exclusive {'@'}username reservation",
            'history' => 'Saved history in AI SQL Builder (90 days)',
            'priority' => 'Priority support and early access',
        ],
        'advantages' => [
            'title' => 'Why upgrade to Pro',
            'subtitle' => 'Higher capacity in AI SQL Builder, your own reserved handle on the platform, and priority access to new features.',
            'items' => [
                'username' => [
                    'title' => "Your exclusive {'@'}username",
                    'body' => 'Claim your unique identifier on the platform for your profile and future shared query links.',
                ],
                'history' => [
                    'title' => '90-day query history',
                    'body' => 'Browse, reuse, and copy previously generated SQL and ORM code in AI SQL Builder.',
                ],
                'quota' => [
                    'title' => '2M monthly token quota and expanded limits',
                    'body' => 'Send DDL schemas up to 64 KB and detailed prompts up to 4,000 characters per query.',
                ],
                'priority' => [
                    'title' => 'Priority support and early access',
                    'body' => 'First-hand access to new developer tools and upcoming AI code output targets.',
                ],
                'discount' => [
                    'title' => 'Save 2 months on the yearly plan',
                    'body' => 'Pay the equivalent of 10 months on the yearly plan and get a full 12 months of Pro access.',
                ],
            ],
        ],
    ],

    'subscribe' => [
        'title' => 'Pro subscription intent',
        'subtitle' => "Choose between the monthly or yearly plan, claim your exclusive {'@'}username, and provide your billing details to activate your subscription.",
        'pending_title' => 'Subscription request registered',
        'pending_body' => "We received your subscription intent and your {'@'}username is already reserved on your account. We will reach out to complete activation.",
        'submitted_toast' => 'Subscription intent registered successfully.',
        'plan_label' => 'Choose your plan',
        'username_label' => 'Your exclusive username',
        'username_placeholder' => 'username',
        'username_help' => 'Lowercase letters, numbers, and underscores only (3 to 30 characters). Reserved immediately on your account.',
        'phone_label' => 'Phone / WhatsApp',
        'phone_placeholder' => '+55 (11) 99999-9999',
        'document_label' => 'Tax ID (CPF / CNPJ)',
        'document_placeholder' => '000.000.000-00',
        'payment_method_label' => 'Preferred payment method',
        'payment_methods' => [
            'pix' => 'Pix',
            'credit_card' => 'Credit card',
            'boleto' => 'Bank slip (Boleto)',
        ],
        'notes_label' => 'Notes (optional)',
        'notes_placeholder' => 'Any preferred contact time or billing detail',
        'submit' => 'Submit subscription intent',
        'update_submit' => 'Update subscription intent',
    ],
];
