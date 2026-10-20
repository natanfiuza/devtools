<?php

return [
    'title' => 'Ferramentas para desenvolvedores que ficam fora do seu caminho',
    'subtitle' => 'Formate, converta e inspecione dados direto no navegador. Sem cadastro para as ferramentas locais.',

    'tools' => [
        'title' => 'Ferramentas',
        'subtitle' => 'As ferramentas rodam no seu navegador: o que você cola aqui não sai da máquina — exceto no AI SQL Builder, que envia schema e descrição ao provedor de IA.',
        'filter' => 'Filtrar por categoria',
        'all' => 'Todas',
        'empty' => 'Nenhuma ferramenta nesta categoria ainda.',
    ],

    'ai' => [
        'badge' => 'Disponível',
        'title' => 'AI SQL Builder',
        'body' => 'Descreva a consulta em linguagem natural e receba o SQL do seu banco, além de Eloquent, Prisma e o resto do seu stack.',
        'note' => 'Precisa de conta verificada: a consulta passa pelo modelo e consome a cota do plano.',
        'cta' => 'Abrir o AI SQL Builder',
        'preview_prompt' => 'Liste os 10 clientes que mais compraram nos últimos 30 dias',
        'preview_sql' => "SELECT c.name, SUM(o.total) AS spent\nFROM customers c\nJOIN orders o ON o.customer_id = c.id\nWHERE o.created_at >= NOW() - INTERVAL '30 days'\nGROUP BY c.name\nORDER BY spent DESC\nLIMIT 10;",
    ],

    'cta' => [
        'register' => 'Criar conta',
        'contact' => 'Falar por e-mail',
        'subscribe' => 'Assinar agora',
    ],

    'pricing' => [
        'title' => 'Planos',
        'subtitle' => 'Escolha o plano ideal para o seu fluxo. Sem renovação automática oculta: registre sua intenção de assinatura e reserve seu identificador exclusivo.',
        'free' => 'Grátis',
        'yearly_badge' => '2 meses grátis',
        'interval' => [
            'month' => 'por mês',
            'year' => 'por ano',
        ],
        'features' => [
            'uses' => '{count} usos vitalícios',
            'tokens' => '{count} tokens por mês',
            'schema' => 'Schema de até {size}',
            'prompt' => 'Descrição de até {count} caracteres',
            'username' => "Reserva exclusiva de {'@'}nomedeusuario",
            'history' => 'Histórico salvo no AI SQL Builder (90 dias)',
            'priority' => 'Suporte prioritário e acesso antecipado',
        ],
        'advantages' => [
            'title' => 'Vantagens de ser assinante Pro',
            'subtitle' => 'Mais capacidade no AI SQL Builder, reserva do seu identificador na plataforma e prioridade nas novidades.',
            'items' => [
                'username' => [
                    'title' => "Seu {'@'}nomedeusuario exclusivo",
                    'body' => 'Garanta seu identificador único na plataforma para perfil e futuros links compartilhados de consultas.',
                ],
                'history' => [
                    'title' => 'Histórico de consultas por 90 dias',
                    'body' => 'Consulte, reutilize e copie o SQL e o código ORM gerados anteriormente no AI SQL Builder.',
                ],
                'quota' => [
                    'title' => 'Cota mensal de 2M tokens e limites ampliados',
                    'body' => 'Envie schemas DDL de até 64 KB e descrições detalhadas de até 4.000 caracteres por consulta.',
                ],
                'priority' => [
                    'title' => 'Suporte prioritário e acesso antecipado',
                    'body' => 'Acesso em primeira mão às novas ferramentas e aos novos alvos de saída de código com IA.',
                ],
                'discount' => [
                    'title' => 'Economia de 2 meses no plano anual',
                    'body' => 'Assinando o plano anual você paga o equivalente a 10 meses e garante 12 meses completos de acesso Pro.',
                ],
            ],
        ],
    ],

    'subscribe' => [
        'title' => 'Intenção de assinatura Pro',
        'subtitle' => "Escolha entre o plano mensal ou anual, reserve seu {'@'}nomedeusuario exclusivo e informe os dados para ativação da assinatura.",
        'pending_title' => 'Pedido de assinatura registrado',
        'pending_body' => "Recebemos sua intenção de assinatura e seu {'@'}nomedeusuario já está reservado na sua conta. Entraremos em contato para concluir a ativação.",
        'submitted_toast' => 'Intenção de assinatura registrada com sucesso.',
        'plan_label' => 'Escolha o seu plano',
        'username_label' => 'Seu nome de usuário exclusivo',
        'username_placeholder' => 'nomedeusuario',
        'username_help' => 'Apenas letras minúsculas, números e sublinhado (3 a 30 caracteres). Reservado imediatamente na sua conta.',
        'phone_label' => 'Telefone / WhatsApp',
        'phone_country_code' => 'Código do país',
        'phone_placeholder' => '(11) 99999-9999',
        'document_label' => 'CPF ou CNPJ (opcional)',
        'document_placeholder' => '000.000.000-00 (opcional)',
        'redirect_countdown' => 'Você será redirecionado para a página inicial em {seconds}s.',
        'redirect_now' => 'Ir para o início agora',
        'redirect_cancel' => 'Permanecer nesta página',
        'payment_method_label' => 'Forma de pagamento preferida',
        'payment_methods' => [
            'pix' => 'Pix',
            'credit_card' => 'Cartão de crédito',
            'boleto' => 'Boleto bancário',
        ],
        'notes_label' => 'Observações (opcional)',
        'notes_placeholder' => 'Alguma preferência de horário ou detalhe sobre a cobrança',
        'submit' => 'Registrar intenção de assinatura',
        'update_submit' => 'Atualizar intenção de assinatura',
    ],
];
