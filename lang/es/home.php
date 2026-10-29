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

    'api' => [
        'badge' => 'API REST v1',
        'title' => 'Automatiza e integra directamente en tu flujo de trabajo',
        'body' => 'Genera SQL y código ORM mediante programación. Conecta devtools a tus pipelines de CI/CD, scripts CLI, herramientas internas y automatizaciones en n8n sin tener que copiar y pegar desde la interfaz.',
        'note' => 'Autenticación con Bearer token (Sanctum), procesamiento asíncrono y respuesta instantánea si existe en caché.',
        'cta_docs' => 'Ver documentación de la API',
        'cta_tokens' => 'Gestionar tokens de API',
        'preview_title' => 'Ejemplo de petición cURL',
        'preview_code' => "curl -sS -X POST \"https://devtools.natanfiuza.dev.br/api/v1/sql-queries\" \\\n  -H \"Authorization: Bearer TU_TOKEN\" \\\n  -H \"Accept: application/json\" \\\n  -H \"Content-Type: application/json\" \\\n  -d '{'{'}\n    \"schema_source\": \"ddl\",\n    \"dialect\": \"mysql\",\n    \"output_target\": \"sql\",\n    \"schema\": \"CREATE TABLE users (id BIGINT PRIMARY KEY, name VARCHAR(120), active TINYINT(1));\",\n    \"description\": \"Listar usuarios activos ordenados por nombre\"\n  {'}'}'",
    ],

    'donation' => [
        'badge' => 'Apoya devtools',
        'title' => 'Ayuda a mantener las herramientas activas y gratuitas',
        'body' => 'devtools es un proyecto independiente creado por desarrolladores para desarrolladores. Todas las herramientas se ejecutan 100% en tu navegador con total privacidad y sin anuncios molestos. Las donaciones vía Pix ayudan directamente a costear servidores y cuotas de modelos de IA.',
        'note' => 'Cualquier aporte marca la diferencia y mantiene el proyecto sostenible y accesible para todos.',
        'cta' => 'Hacer una donación vía Pix',
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
        'checkout' => [
            'unavailable' => 'El pago en línea no está disponible en este momento. Puedes registrar tu interés abajo y te contactaremos.',
            'path_label' => '¿Cómo quieres suscribirte?',
            'paths' => [
                'one_time' => [
                    'title' => 'Pagar ahora',
                    'body' => 'Pix, tarjeta o boleto pagados al contado. Desbloquea un período en cuanto se aprueba el pago.',
                ],
                'recurring' => [
                    'title' => 'Renovación automática con tarjeta',
                    'body' => 'La tarjeta se cobra en cada período. El acceso se desbloquea al confirmarse el primer pago.',
                ],
                'manual' => [
                    'title' => 'Prefiero atención manual',
                    'body' => 'Registra tu solicitud y acordamos contigo la forma de pago.',
                ],
            ],
            'recurring_badge' => 'Renovación activa',
            'recurring_blocked' => 'Ya hay una renovación automática activa en este plan. Cancélala en el área de facturas antes de crear otra.',
            'redirect_note' => 'Serás redirigido a Mercado Pago para completar el pago. Nada se desbloquea hasta la confirmación.',
            'one_time_action' => 'Pagar ahora',
            'recurring_action' => 'Activar renovación automática',
            'errors' => [
                'unavailable' => 'El pago en línea no está disponible. Usa la atención manual para completar la suscripción.',
                'recurring_active' => 'Ya hay una renovación automática activa en este plan. Cancélala antes de crear otra.',
                'plan_unavailable' => 'Este plan no está disponible para el pago en línea.',
                'gateway' => 'No pudimos abrir el pago ahora. Inténtalo de nuevo en unos instantes o usa la atención manual.',
            ],
            'status' => [
                'processing' => [
                    'title' => 'Pago en procesamiento',
                    'body' => 'Estamos esperando la confirmación del proveedor. El acceso se desbloquea automáticamente en cuanto se apruebe el pago.',
                ],
                'approved' => [
                    'title' => 'Pago aprobado',
                    'body' => 'Tu nuevo período ya está disponible en la cuenta. Puedes seguirlo todo en el área de facturas.',
                ],
                'expired' => [
                    'title' => 'Checkout expirado',
                    'body' => 'Este intento de pago expiró sin confirmación. Elige un plan e inténtalo de nuevo.',
                ],
                'failed' => [
                    'title' => 'Pago no completado',
                    'body' => 'No pudimos completar este intento. No se desbloqueó ningún acceso; puedes intentarlo de nuevo o usar la atención manual.',
                ],
            ],
        ],
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
