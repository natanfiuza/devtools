<?php

return [
    'title' => 'Developer tools that stay out of your way',
    'subtitle' => 'Format, convert, and inspect data right in your browser. No sign-up for the local tools.',

    'tools' => [
        'title' => 'Tools',
        'subtitle' => 'Tools run in your browser: whatever you paste here never leaves the machine — except in AI SQL Builder, which sends the schema and description to the AI provider.',
        'filter' => 'Filter by category',
        'all' => 'All',
        'empty' => 'No tool in this category yet.',
    ],

    'ai' => [
        'badge' => 'Available',
        'title' => 'AI SQL Builder',
        'body' => 'Describe the query in plain language and get SQL for your database, plus Eloquent, Prisma and the rest of your stack.',
        'note' => 'Needs a verified account: the query goes through the model and spends the plan quota.',
        'cta' => 'Open AI SQL Builder',
        'preview_prompt' => 'List the 10 customers who bought the most in the last 30 days',
        'preview_sql' => "SELECT c.name, SUM(o.total) AS spent\nFROM customers c\nJOIN orders o ON o.customer_id = c.id\nWHERE o.created_at >= NOW() - INTERVAL '30 days'\nGROUP BY c.name\nORDER BY spent DESC\nLIMIT 10;",
    ],

    'api' => [
        'badge' => 'REST API v1',
        'title' => 'Automate and integrate directly into your workflow',
        'body' => 'Generate SQL and ORM code programmatically. Connect devtools to your CI/CD pipelines, CLI scripts, internal tools, and n8n automations without copy-pasting through the UI.',
        'note' => 'Bearer token authentication (Sanctum), asynchronous processing, and instant responses on cache hit.',
        'cta_docs' => 'View API documentation',
        'cta_tokens' => 'Manage API tokens',
        'preview_title' => 'cURL request example',
        'preview_code' => "curl -sS -X POST \"https://devtools.natanfiuza.dev.br/api/v1/sql-queries\" \\\n  -H \"Authorization: Bearer YOUR_TOKEN\" \\\n  -H \"Accept: application/json\" \\\n  -H \"Content-Type: application/json\" \\\n  -d '{\n    \"schema_source\": \"ddl\",\n    \"dialect\": \"mysql\",\n    \"output_target\": \"sql\",\n    \"schema\": \"CREATE TABLE users (id BIGINT PRIMARY KEY, name VARCHAR(120), active TINYINT(1));\",\n    \"description\": \"List active users ordered by name\"\n  }'",
    ],

    'donation' => [
        'badge' => 'Support devtools',
        'title' => 'Help keep the tools online and free for everyone',
        'body' => 'devtools is an independent project built by developers for developers. All utility tools run 100% in your browser with complete privacy and zero intrusive ads. Pix donations directly help fund server hosting and AI model quotas.',
        'note' => 'Any amount makes a difference and keeps the project sustainable and accessible to everyone.',
        'cta' => 'Make a donation via Pix',
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
        'checkout' => [
            'unavailable' => 'Online payment is currently unavailable. You can register your interest below and we will contact you.',
            'path_label' => 'How would you like to subscribe?',
            'paths' => [
                'one_time' => [
                    'title' => 'Pay now',
                    'body' => 'Pix, card or bank slip paid upfront. Unlocks one period as soon as the payment is approved.',
                ],
                'recurring' => [
                    'title' => 'Automatic card renewal',
                    'body' => 'Your card is charged every period. Access unlocks when the first payment is confirmed.',
                ],
                'manual' => [
                    'title' => 'I prefer manual support',
                    'body' => 'Register your request and we will arrange the payment method with you.',
                ],
            ],
            'recurring_badge' => 'Renewal active',
            'recurring_blocked' => 'Automatic renewal is already active on this plan. Cancel it in the billing area before creating another one.',
            'redirect_note' => 'You will be taken to Mercado Pago to complete the payment. Nothing is unlocked until confirmation.',
            'one_time_action' => 'Pay now',
            'recurring_action' => 'Enable automatic renewal',
            'errors' => [
                'unavailable' => 'Online payment is unavailable. Use the manual support option to complete your subscription.',
                'recurring_active' => 'Automatic renewal is already active on this plan. Cancel it before creating another one.',
                'plan_unavailable' => 'This plan is not available for online payment.',
                'gateway' => 'We could not open the payment right now. Try again shortly or use the manual support option.',
            ],
            'status' => [
                'processing' => [
                    'title' => 'Payment processing',
                    'body' => 'We are waiting for the provider to confirm. Access unlocks automatically as soon as the payment is approved.',
                ],
                'approved' => [
                    'title' => 'Payment approved',
                    'body' => 'Your new period is already available on your account. You can follow everything in the billing area.',
                ],
                'expired' => [
                    'title' => 'Checkout expired',
                    'body' => 'This payment attempt expired without confirmation. Choose a plan and try again.',
                ],
                'failed' => [
                    'title' => 'Payment not completed',
                    'body' => 'We could not complete this attempt. No access was unlocked; you can try again or use the manual support option.',
                ],
            ],
        ],
        'username_label' => 'Your exclusive username',
        'username_placeholder' => 'username',
        'username_help' => 'Lowercase letters, numbers, and underscores only (3 to 30 characters). Reserved immediately on your account.',
        'phone_label' => 'Phone / WhatsApp',
        'phone_country_code' => 'Country code',
        'phone_placeholder' => '+1 (555) 000-0000',
        'document_label' => 'Identification document (optional)',
        'document_placeholder' => 'Passport, Tax ID or ID number',
        'redirect_countdown' => 'You will be redirected to the home page in {seconds}s.',
        'redirect_now' => 'Go to home now',
        'redirect_cancel' => 'Stay on this page',
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
