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
    ],

    'pricing' => [
        'title' => 'Planos',
        'subtitle' => 'Sem renovação automática: a cobrança é combinada por e-mail e o plano vale até ao fim do período.',
        'free' => 'Grátis',
        'interval' => [
            'month' => 'por mês',
            'year' => 'por ano',
        ],
        'features' => [
            'uses' => '{count} utilizações vitalícias',
            'tokens' => '{count} tokens por mês',
            'schema' => 'Schema até {size}',
            'prompt' => 'Descrição até {count} caracteres',
        ],
    ],
];
