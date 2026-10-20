<?php

return [
    'title' => 'Herramientas para desarrolladores que no estorban',
    'subtitle' => 'Formatea, convierte e inspecciona datos en el navegador. Sin registro para las herramientas locales.',

    'tools' => [
        'title' => 'Herramientas',
        'subtitle' => 'Las herramientas corren en tu navegador: lo que pegues aquí no sale de la máquina — excepto en AI SQL Builder, que envía el esquema y la descripción al proveedor de IA.',
        'filter' => 'Filtrar por categoría',
        'all' => 'Todas',
        'empty' => 'Todavía no hay herramientas en esta categoría.',
    ],

    'ai' => [
        'badge' => 'Disponible',
        'title' => 'AI SQL Builder',
        'body' => 'Describe la consulta en lenguaje natural y recibe el SQL de tu base de datos, además de Eloquent, Prisma y el resto de tu stack.',
        'note' => 'Requiere cuenta verificada: la consulta pasa por el modelo y consume la cuota del plan.',
        'cta' => 'Abrir AI SQL Builder',
        'preview_prompt' => 'Lista los 10 clientes que más compraron en los últimos 30 días',
        'preview_sql' => "SELECT c.name, SUM(o.total) AS spent\nFROM customers c\nJOIN orders o ON o.customer_id = c.id\nWHERE o.created_at >= NOW() - INTERVAL '30 days'\nGROUP BY c.name\nORDER BY spent DESC\nLIMIT 10;",
    ],

    'cta' => [
        'register' => 'Crear cuenta',
        'contact' => 'Escribir por correo',
        'subscribe' => 'Suscribirse ahora',
    ],

    'pricing' => [
        'title' => 'Planes',
        'subtitle' => 'Elige el plan ideal para tu flujo. Sin renovación automática oculta: registra tu intención de suscripción y reserva tu identificador exclusivo.',
        'free' => 'Gratis',
        'yearly_badge' => '2 meses gratis',
        'interval' => [
            'month' => 'por mes',
            'year' => 'por año',
        ],
        'features' => [
            'uses' => '{count} usos de por vida',
            'tokens' => '{count} tokens por mes',
            'schema' => 'Esquema de hasta {size}',
            'prompt' => 'Descripción de hasta {count} caracteres',
            'username' => "Reserva exclusiva de {'@'}nombredeusuario",
            'history' => 'Historial guardado en AI SQL Builder (90 días)',
            'priority' => 'Soporte prioritario y acceso anticipado',
        ],
        'advantages' => [
            'title' => 'Ventajas de ser suscriptor Pro',
            'subtitle' => 'Más capacidad en AI SQL Builder, reserva de tu identificador en la plataforma y prioridad en las novedades.',
            'items' => [
                'username' => [
                    'title' => "Tu {'@'}nombredeusuario exclusivo",
                    'body' => 'Asegura tu identificador único en la plataforma para tu perfil y futuros enlaces compartidos de consultas.',
                ],
                'history' => [
                    'title' => 'Historial de consultas por 90 días',
                    'body' => 'Consulta, reutiliza y copia el SQL y el código ORM generados anteriormente en AI SQL Builder.',
                ],
                'quota' => [
                    'title' => 'Cuota mensual de 2M tokens y límites ampliados',
                    'body' => 'Envía esquemas DDL de hasta 64 KB y descripciones detalladas de hasta 4.000 caracteres por consulta.',
                ],
                'priority' => [
                    'title' => 'Soporte prioritario y acceso anticipado',
                    'body' => 'Acceso de primera mano a nuevas herramientas y nuevos destinos de código con IA.',
                ],
                'discount' => [
                    'title' => 'Ahorro de 2 meses en el plan anual',
                    'body' => 'Con el plan anual pagas el equivalente a 10 meses y aseguras 12 meses completos de acceso Pro.',
                ],
            ],
        ],
    ],

    'subscribe' => [
        'title' => 'Intención de suscripción Pro',
        'subtitle' => "Elige entre el plan mensual o anual, reserva tu {'@'}nombredeusuario exclusivo e indica tus datos para activar la suscripción.",
        'pending_title' => 'Solicitud de suscripción registrada',
        'pending_body' => "Recibimos tu intención de suscripción y tu {'@'}nombredeusuario ya está reservado en tu cuenta. Nos pondremos en contacto para completar la activación.",
        'submitted_toast' => 'Intención de suscripción registrada con éxito.',
        'plan_label' => 'Elige tu plan',
        'username_label' => 'Tu nombre de usuario exclusivo',
        'username_placeholder' => 'nombredeusuario',
        'username_help' => 'Solo letras minúsculas, números y guion bajo (de 3 a 30 caracteres). Reservado de inmediato en tu cuenta.',
        'phone_label' => 'Teléfono / WhatsApp',
        'phone_country_code' => 'Código de país',
        'phone_placeholder' => '+34 612 345 678',
        'document_label' => 'Documento de identificación (opcional)',
        'document_placeholder' => 'DNI, NIE, Pasaporte o documento fiscal',
        'redirect_countdown' => 'Serás redirigido a la página de inicio en {seconds}s.',
        'redirect_now' => 'Ir al inicio ahora',
        'redirect_cancel' => 'Permanecer en esta página',
        'payment_method_label' => 'Forma de pago preferida',
        'payment_methods' => [
            'pix' => 'Pix',
            'credit_card' => 'Tarjeta de crédito',
            'boleto' => 'Boleto bancario',
        ],
        'notes_label' => 'Observaciones (opcional)',
        'notes_placeholder' => 'Alguna preferencia de horario o detalle sobre el cobro',
        'submit' => 'Registrar intención de suscripción',
        'update_submit' => 'Actualizar intención de suscripción',
    ],
];
