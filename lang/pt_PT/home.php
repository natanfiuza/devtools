<?php

return [
    'title' => 'Ferramentas para programadores que ficam fora do seu caminho',
    'subtitle' => 'Formate, converta e inspecione dados diretamente no navegador. Sem registo e sem enviar nada para o servidor.',

    'tools' => [
        'title' => 'Ferramentas',
        'subtitle' => 'Tudo corre no seu navegador: o que colar aqui nunca sai da máquina.',
        'filter' => 'Filtrar por categoria',
        'all' => 'Todas',
        'empty' => 'Ainda não há ferramentas nesta categoria.',
    ],

    'ai' => [
        'badge' => 'Em breve',
        'title' => 'AI SQL Builder',
        'body' => 'Descreva a consulta em linguagem natural e receba o SQL da sua base de dados, além de Eloquent, Prisma e o resto do seu stack.',
        'note' => 'Requer conta verificada: a consulta passa pelo modelo e consome a quota do plano.',
        'preview_prompt' => 'Lista os 10 clientes que mais compraram nos últimos 30 dias',
        'preview_sql' => "SELECT c.name, SUM(o.total) AS spent\nFROM customers c\nJOIN orders o ON o.customer_id = c.id\nWHERE o.created_at >= NOW() - INTERVAL '30 days'\nGROUP BY c.name\nORDER BY spent DESC\nLIMIT 10;",
    ],

    'cta' => [
        'register' => 'Criar conta',
        'contact' => 'Falar por e-mail',
        'subscribe' => 'Subscrever agora',
    ],

    'pricing' => [
        'title' => 'Planos',
        'subtitle' => 'Escolha o plano ideal para o seu fluxo. Sem renovação automática oculta: registe a sua intenção de subscrição e reserve o seu identificador exclusivo.',
        'free' => 'Grátis',
        'yearly_badge' => '2 meses grátis',
        'interval' => [
            'month' => 'por mês',
            'year' => 'por ano',
        ],
        'features' => [
            'uses' => '{count} utilizações vitalícias',
            'tokens' => '{count} tokens por mês',
            'schema' => 'Schema até {size}',
            'prompt' => 'Descrição até {count} caracteres',
            'username' => "Reserva exclusiva de {'@'}nomedeutilizador",
            'history' => 'Histórico guardado no AI SQL Builder (90 dias)',
            'priority' => 'Suporte prioritário e acesso antecipado',
        ],
        'advantages' => [
            'title' => 'Vantagens de ser subscritor Pro',
            'subtitle' => 'Mais capacidade no AI SQL Builder, reserva do seu identificador na plataforma e prioridade nas novidades.',
            'items' => [
                'username' => [
                    'title' => "O seu {'@'}nomedeutilizador exclusivo",
                    'body' => 'Garanta o seu identificador único na plataforma para perfil e futuras ligações partilhadas de consultas.',
                ],
                'history' => [
                    'title' => 'Histórico de consultas por 90 dias',
                    'body' => 'Consulte, reutilize e copie o SQL e o código ORM gerados anteriormente no AI SQL Builder.',
                ],
                'quota' => [
                    'title' => 'Quota mensal de 2M tokens e limites alargados',
                    'body' => 'Envie schemas DDL até 64 KB e descrições detalhadas até 4.000 caracteres por consulta.',
                ],
                'priority' => [
                    'title' => 'Suporte prioritário e acesso antecipado',
                    'body' => 'Acesso em primeira mão às novas ferramentas e aos novos alvos de saída de código com IA.',
                ],
                'discount' => [
                    'title' => 'Poupança de 2 meses no plano anual',
                    'body' => 'Ao subscrever o plano anual paga o equivalente a 10 meses e garante 12 meses completos de acesso Pro.',
                ],
            ],
        ],
    ],

    'subscribe' => [
        'title' => 'Intenção de subscrição Pro',
        'subtitle' => "Escolha entre o plano mensal ou anual, reserve o seu {'@'}nomedeutilizador exclusivo e indique os dados para ativação da subscrição.",
        'pending_title' => 'Pedido de subscrição registado',
        'pending_body' => "Recebemos a sua intenção de subscrição e o seu {'@'}nomedeutilizador já está reservado na sua conta. Entraremos em contacto para concluir a ativação.",
        'submitted_toast' => 'Intenção de subscrição registada com sucesso.',
        'plan_label' => 'Escolha o seu plano',
        'username_label' => 'O seu nome de utilizador exclusivo',
        'username_placeholder' => 'nomedeutilizador',
        'username_help' => 'Apenas letras minúsculas, números e sublinhado (3 a 30 caracteres). Reservado imediatamente na sua conta.',
        'phone_label' => 'Telefone / WhatsApp',
        'phone_placeholder' => '+351 912 345 678',
        'document_label' => 'CPF, CNPJ ou NIF',
        'document_placeholder' => '000.000.000-00',
        'payment_method_label' => 'Forma de pagamento preferida',
        'payment_methods' => [
            'pix' => 'Pix',
            'credit_card' => 'Cartão de crédito',
            'boleto' => 'Boleto bancário',
        ],
        'notes_label' => 'Observações (opcional)',
        'notes_placeholder' => 'Alguma preferência de horário ou detalhe sobre a cobrança',
        'submit' => 'Registar intenção de subscrição',
        'update_submit' => 'Atualizar intenção de subscrição',
    ],
];
