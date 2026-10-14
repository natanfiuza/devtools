<?php

return [
    'title' => 'Herramientas para desarrolladores que no estorban',
    'subtitle' => 'Formatea, convierte e inspecciona datos en el navegador. Sin registro y sin enviar nada al servidor.',

    'tools' => [
        'title' => 'Herramientas',
        'subtitle' => 'Todo corre en tu navegador: lo que pegues aquí no sale de la máquina.',
        'filter' => 'Filtrar por categoría',
        'all' => 'Todas',
        'empty' => 'Todavía no hay herramientas en esta categoría.',
    ],

    'ai' => [
        'badge' => 'Próximamente',
        'title' => 'AI SQL Builder',
        'body' => 'Describe la consulta en lenguaje natural y recibe el SQL de tu base de datos, además de Eloquent, Prisma y el resto de tu stack.',
        'note' => 'Requiere cuenta verificada: la consulta pasa por el modelo y consume la cuota del plan.',
        'preview_prompt' => 'Lista los 10 clientes que más compraron en los últimos 30 días',
        'preview_sql' => "SELECT c.name, SUM(o.total) AS spent\nFROM customers c\nJOIN orders o ON o.customer_id = c.id\nWHERE o.created_at >= NOW() - INTERVAL '30 days'\nGROUP BY c.name\nORDER BY spent DESC\nLIMIT 10;",
    ],

    'cta' => [
        'register' => 'Crear cuenta',
        'contact' => 'Escribir por correo',
    ],

    'pricing' => [
        'title' => 'Planes',
        'subtitle' => 'Sin renovación automática: el cobro se acuerda por correo y el plan vale hasta el final del periodo.',
        'free' => 'Gratis',
        'interval' => [
            'month' => 'por mes',
            'year' => 'por año',
        ],
        'features' => [
            'uses' => '{count} usos de por vida',
            'tokens' => '{count} tokens por mes',
            'schema' => 'Esquema de hasta {size}',
            'prompt' => 'Descripción de hasta {count} caracteres',
        ],
    ],
];
