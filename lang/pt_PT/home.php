<?php

return [
    'title' => 'Ferramentas para programadores que ficam fora do seu caminho',
    'subtitle' => 'Formate, converta e inspecione dados diretamente no navegador. Sem registo para as ferramentas locais.',

    'tools' => [
        'title' => 'Ferramentas',
        'subtitle' => 'As ferramentas correm no seu navegador: o que colar aqui nunca sai da máquina — exceto no AI SQL Builder, que envia o schema e a descrição ao fornecedor de IA.',
        'filter' => 'Filtrar por categoria',
        'all' => 'Todas',
        'empty' => 'Ainda não há ferramentas nesta categoria.',
    ],

    'ai' => [
        'badge' => 'Disponível',
        'title' => 'AI SQL Builder',
        'body' => 'Descreva a consulta em linguagem natural e receba o SQL da sua base de dados, além de Eloquent, Prisma e o resto do seu stack.',
        'note' => 'Requer conta verificada: a consulta passa pelo modelo e consome a quota do plano.',
        'cta' => 'Abrir o AI SQL Builder',
        'preview_prompt' => 'Lista os 10 clientes que mais compraram nos últimos 30 dias',
        'preview_sql' => "SELECT c.name, SUM(o.total) AS spent\nFROM customers c\nJOIN orders o ON o.customer_id = c.id\nWHERE o.created_at >= NOW() - INTERVAL '30 days'\nGROUP BY c.name\nORDER BY spent DESC\nLIMIT 10;",
    ],

    'api' => [
        'badge' => 'API REST v1',
        'title' => 'Automatize e integre diretamente no seu fluxo de trabalho',
        'body' => 'Gere SQL e código de ORM programaticamente. Conecte o devtools aos seus pipelines de CI/CD, scripts CLI, ferramentas internas e automações no n8n sem necessitar de copiar e colar pela interface.',
        'note' => 'Autenticação com Bearer Token (Sanctum), processamento assíncrono e resposta instantânea em caso de cache.',
        'cta_docs' => 'Ver documentação da API',
        'cta_tokens' => 'Gerir tokens de API',
        'preview_title' => 'Exemplo de pedido cURL',
        'preview_code' => "curl -sS -X POST \"https://devtools.natanfiuza.dev.br/api/v1/sql-queries\" \\\n  -H \"Authorization: Bearer SEU_TOKEN\" \\\n  -H \"Accept: application/json\" \\\n  -H \"Content-Type: application/json\" \\\n  -d '{\n    \"schema_source\": \"ddl\",\n    \"dialect\": \"mysql\",\n    \"output_target\": \"sql\",\n    \"schema\": \"CREATE TABLE users (id BIGINT PRIMARY KEY, name VARCHAR(120), active TINYINT(1));\",\n    \"description\": \"Listar utilizadores ativos ordenados por nome\"\n  }'",
    ],

    'donation' => [
        'badge' => 'Apoie o devtools',
        'title' => 'Ajude a manter as ferramentas online e gratuitas',
        'body' => 'O devtools é um projeto independente criado por programadores para programadores. Todas as ferramentas utilitárias correm 100% no seu navegador com total privacidade e sem anúncios invasivos. Doações via Pix ajudam diretamente a suportar custos de servidores e modelos de IA.',
        'note' => 'Qualquer valor faz a diferença e mantém o projeto sustentável e acessível para toda a comunidade.',
        'cta' => 'Fazer uma doação via Pix',
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
        'checkout' => [
            'unavailable' => 'O pagamento online está indisponível neste momento. Pode registar o seu interesse abaixo e entraremos em contacto.',
            'path_label' => 'Como pretende subscrever?',
            'paths' => [
                'one_time' => [
                    'title' => 'Pagar agora',
                    'body' => 'Pix, cartão ou boleto pagos à vista. Liberta um período assim que o pagamento for aprovado.',
                ],
                'recurring' => [
                    'title' => 'Renovação automática no cartão',
                    'body' => 'O cartão é cobrado em cada período. A libertação acontece na confirmação do primeiro pagamento.',
                ],
                'manual' => [
                    'title' => 'Prefiro atendimento manual',
                    'body' => 'Registe o seu pedido e combinamos consigo a forma de pagamento.',
                ],
            ],
            'recurring_badge' => 'Renovação ativa',
            'recurring_blocked' => 'Já existe uma renovação automática ativa neste plano. Cancele-a na área de faturas antes de criar outra.',
            'redirect_note' => 'Será encaminhado para o Mercado Pago para concluir o pagamento. Nada é libertado até à confirmação.',
            'one_time_action' => 'Pagar agora',
            'recurring_action' => 'Ativar renovação automática',
            'errors' => [
                'unavailable' => 'O pagamento online está indisponível. Utilize o atendimento manual para concluir a subscrição.',
                'recurring_active' => 'Já existe uma renovação automática ativa neste plano. Cancele-a antes de criar outra.',
                'plan_unavailable' => 'Este plano não está disponível para pagamento online.',
                'gateway' => 'Não foi possível abrir o pagamento agora. Tente novamente dentro de instantes ou utilize o atendimento manual.',
            ],
            'status' => [
                'processing' => [
                    'title' => 'Pagamento em processamento',
                    'body' => 'Estamos a aguardar a confirmação do fornecedor. A libertação acontece automaticamente assim que o pagamento for aprovado.',
                ],
                'approved' => [
                    'title' => 'Pagamento aprovado',
                    'body' => 'O seu novo período já está disponível na conta. Pode acompanhar tudo na área de faturas.',
                ],
                'expired' => [
                    'title' => 'Checkout expirado',
                    'body' => 'Esta tentativa de pagamento expirou sem confirmação. Escolha um plano e tente novamente.',
                ],
                'failed' => [
                    'title' => 'Pagamento não concluído',
                    'body' => 'Não foi possível concluir esta tentativa. Nenhuma cobrança foi libertada; pode tentar novamente ou utilizar o atendimento manual.',
                ],
            ],
        ],
        'username_label' => 'O seu nome de utilizador exclusivo',
        'username_placeholder' => 'nomedeutilizador',
        'username_help' => 'Apenas letras minúsculas, números e sublinhado (3 a 30 caracteres). Reservado imediatamente na sua conta.',
        'phone_label' => 'Telefone / WhatsApp',
        'phone_country_code' => 'Código do país',
        'phone_placeholder' => '+351 912 345 678',
        'document_label' => 'Documento de identificação (opcional)',
        'document_placeholder' => 'NIF, Cartão de Cidadão ou passaporte',
        'redirect_countdown' => 'Será redirecionado para a página inicial em {seconds}s.',
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
        'submit' => 'Registar intenção de subscrição',
        'update_submit' => 'Atualizar intenção de subscrição',
    ],
];
