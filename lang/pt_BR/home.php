<?php

return [
    'title' => 'Ferramentas para desenvolvedores que ficam fora do seu caminho',
    'subtitle' => 'Formate, converta e inspecione dados direto no navegador. Sem cadastro, sem enviar nada para o servidor.',

    'tools' => [
        'title' => 'Ferramentas',
        'subtitle' => 'Tudo roda no seu navegador: o que você cola aqui não sai da máquina.',
        'filter' => 'Filtrar por categoria',
        'all' => 'Todas',
        'empty' => 'Nenhuma ferramenta nesta categoria ainda.',
    ],

    'ai' => [
        'badge' => 'Em breve',
        'title' => 'AI SQL Builder',
        'body' => 'Descreva a consulta em linguagem natural e receba o SQL do seu banco, além de Eloquent, Prisma e o resto do seu stack.',
        'note' => 'Precisa de conta verificada: a consulta passa pelo modelo e consome a cota do plano.',
        'preview_prompt' => 'Liste os 10 clientes que mais compraram nos últimos 30 dias',
        'preview_sql' => "SELECT c.name, SUM(o.total) AS spent\nFROM customers c\nJOIN orders o ON o.customer_id = c.id\nWHERE o.created_at >= NOW() - INTERVAL '30 days'\nGROUP BY c.name\nORDER BY spent DESC\nLIMIT 10;",
    ],

    'cta' => [
        'register' => 'Criar conta',
        'contact' => 'Falar por e-mail',
    ],

    'pricing' => [
        'title' => 'Planos',
        'subtitle' => 'Sem renovação automática: a cobrança é combinada por e-mail e o plano vale até o fim do período.',
        'free' => 'Grátis',
        'interval' => [
            'month' => 'por mês',
            'year' => 'por ano',
        ],
        'features' => [
            'uses' => '{count} usos vitalícios',
            'tokens' => '{count} tokens por mês',
            'schema' => 'Schema de até {size}',
            'prompt' => 'Descrição de até {count} caracteres',
        ],
    ],
];
