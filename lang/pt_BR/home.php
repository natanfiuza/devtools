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
        'checkout' => [
            'unavailable' => 'O pagamento online está indisponível no momento. Você pode registrar seu interesse abaixo e falaremos com você.',
            'path_label' => 'Como você quer assinar?',
            'paths' => [
                'one_time' => [
                    'title' => 'Pagar agora',
                    'body' => 'Pix, cartão ou boleto à vista. Libera um período assim que o pagamento for aprovado.',
                ],
                'recurring' => [
                    'title' => 'Renovação automática no cartão',
                    'body' => 'O cartão é cobrado a cada período. A liberação acontece na confirmação do primeiro pagamento.',
                ],
                'manual' => [
                    'title' => 'Prefiro atendimento manual',
                    'body' => 'Registre seu pedido e combine a forma de pagamento com a gente.',
                ],
            ],
            'recurring_badge' => 'Renovação ativa',
            'recurring_blocked' => 'Já existe uma renovação automática ativa neste plano. Cancele-a na área de faturas antes de criar outra.',
            'redirect_note' => 'Você será levado ao Mercado Pago para concluir o pagamento. Nada é liberado até a confirmação.',
            'one_time_action' => 'Pagar agora',
            'recurring_action' => 'Ativar renovação automática',
            'errors' => [
                'unavailable' => 'O pagamento online está indisponível. Use o atendimento manual para concluir a assinatura.',
                'recurring_active' => 'Já existe uma renovação automática ativa neste plano. Cancele-a antes de criar outra.',
                'plan_unavailable' => 'Este plano não está disponível para pagamento online.',
                'gateway' => 'Não foi possível abrir o pagamento agora. Tente novamente em instantes ou use o atendimento manual.',
            ],
            'status' => [
                'processing' => [
                    'title' => 'Pagamento em processamento',
                    'body' => 'Estamos aguardando a confirmação do provedor. A liberação acontece automaticamente assim que o pagamento for aprovado.',
                ],
                'approved' => [
                    'title' => 'Pagamento aprovado',
                    'body' => 'Seu novo período já está liberado na conta. Você pode acompanhar tudo na área de faturas.',
                ],
                'expired' => [
                    'title' => 'Checkout expirado',
                    'body' => 'Esta tentativa de pagamento expirou sem confirmação. Escolha um plano e tente novamente.',
                ],
                'failed' => [
                    'title' => 'Pagamento não concluído',
                    'body' => 'Não foi possível concluir esta tentativa. Nenhuma cobrança foi liberada; você pode tentar novamente ou usar o atendimento manual.',
                ],
            ],
        ],
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
