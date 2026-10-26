<?php

return [
    'meta' => [
        'title' => 'Documentação da API v1',
        'description' => 'Integre o gerador de consultas SQL com seus sistemas via API REST com autenticação Bearer, processamento assíncrono e suporte a múltiplos dialetos e ORMs.',
    ],

    'hero' => [
        'eyebrow' => 'REST API v1',
        'title' => 'Documentação da API',
        'subtitle' => 'Integre a geração de consultas SQL, Query Builders e ORMs diretamente no seu backend, CLI, pipeline de CI/CD ou fluxos de automação como n8n.',
        'base_url_label' => 'URL base da API',
        'copy_base_url' => 'Copiar URL base',
        'copied' => 'Copiado!',
        'manage_tokens' => 'Gerenciar tokens de API',
        'openapi_spec' => 'Baixar OpenAPI 3.1 (YAML)',
    ],

    'auth' => [
        'title' => 'Autenticação e fluxo assíncrono',
        'description' => 'Todas as rotas sob /api/v1 exigem um token pessoal do Laravel Sanctum (ability ai:query) enviado no cabeçalho Authorization.',
        'step_1_title' => '1. Crie seu token',
        'step_1_body' => 'Acesse Configurações > Tokens de API na sua conta, gere um token pessoal e copie o valor exibido uma única vez.',
        'step_2_title' => '2. Envie os cabeçalhos HTTP',
        'step_2_body' => 'Inclua Authorization: Bearer SEU_TOKEN e Accept: application/json em todas as chamadas HTTP.',
        'step_3_title' => '3. Trate 200 (cache) e 202 (fila)',
        'step_3_body' => 'Se uma consulta idêntica já estiver em cache, o POST retorna 200 (status done) de imediato. Caso contrário, retorna 202 Accepted com poll_url para acompanhamento até done ou failed.',
        'headers_label' => 'Cabeçalhos obrigatórios',
    ],

    'examples' => [
        'title' => 'Exemplos de integração',
        'description' => 'Exemplos prontos para enviar um schema, solicitar uma consulta e aguardar o processamento assíncrono.',
        'copy' => 'Copiar código',
        'copied' => 'Copiado!',
        'tabs' => [
            'curl' => 'cURL',
            'js' => 'JavaScript / TS',
            'php' => 'PHP',
            'python' => 'Python',
            'n8n' => 'n8n / HTTP',
        ],
        'n8n_note' => 'No n8n, use um nó HTTP Request com autenticação Header Auth (Authorization: Bearer SEU_TOKEN). Se data.poll_url não for nulo (status queued ou processing), adicione um nó Wait respeitando poll_after_ms conectado a um segundo HTTP Request consultando data.poll_url.',
    ],

    'endpoints' => [
        'title' => 'Endpoints disponíveis',
        'description' => 'Referência completa das rotas públicas da API v1.',
        'params_label' => 'Parâmetros',
        'response_label' => 'Resposta de exemplo',
        'field' => 'Campo',
        'type' => 'Tipo',
        'required' => 'Obrigatório',
        'yes' => 'Sim',
        'no' => 'Não',
        'details' => 'Descrição',
        'targets_title' => 'Alvos de saída habilitados (output_target)',
        'targets_description' => 'Valores aceitos no campo output_target do endpoint POST /api/v1/sql-queries.',
        'target_slug' => 'Slug (output_target)',
        'target_language' => 'Linguagem',
        'target_dialects' => 'Dialetos suportados',
        'target_name' => 'Rótulo',
        'items' => [
            'me' => [
                'summary' => 'Retorna os dados da conta autenticada, o plano ativo e o saldo da cota no período atual.',
            ],
            'targets' => [
                'summary' => 'Lista todos os alvos de saída habilitados (SQL, Query Builders e ORMs) e os dialetos que cada um aceita.',
            ],
            'store_query' => [
                'summary' => 'Envia um schema e uma descrição em linguagem natural para gerar a consulta no formato escolhido.',
                'schema_source_desc' => 'Formato do schema enviado: ddl, json, migration ou models.',
                'dialect_desc' => 'Dialeto SQL de referência: mysql, pgsql, sqlsrv ou sqlite.',
                'output_target_desc' => 'Slug do alvo de saída habilitado (ex.: sql, eloquent, prisma).',
                'schema_desc' => 'Conteúdo do schema das tabelas de referência.',
                'description_desc' => 'Descrição em linguagem natural do que a consulta deve retornar.',
                'locale_desc' => 'Idioma opcional da explicação (pt_BR, pt_PT, en, es, fr).',
            ],
            'list_queries' => [
                'summary' => 'Lista o histórico paginado de consultas geradas pela conta autenticada.',
                'status_desc' => 'Filtra por status: queued, processing, done ou failed.',
                'page_desc' => 'Número da página (padrão 1, {perPage} itens por página).',
            ],
            'show_query' => [
                'summary' => 'Consulta o estado atual e o resultado de uma solicitação específica pelo seu ULID.',
                'ulid_desc' => 'Identificador ULID retornado na criação da consulta.',
            ],
        ],
    ],

    'limits' => [
        'title' => 'Limites de taxa (Rate Limits) e cotas',
        'description' => 'A API aplica limites por minuto e por hora por token autenticado, além da cota de consultas do seu plano.',
        'post_title' => 'Criação de consultas (POST /sql-queries)',
        'read_title' => 'Leitura e polling (GET /me, /output-targets, /sql-queries)',
        'per_minute' => '{count} requisições / minuto',
        'per_hour' => '{count} requisições / hora',
        'headers_note' => 'As respostas incluem X-RateLimit-Limit e X-RateLimit-Remaining. Quando o limite é atingido, a API responde 429 Too Many Requests com cabeçalho Retry-After.',
    ],

    'errors' => [
        'title' => 'Códigos de status e erros',
        'description' => 'Todas as falhas retornam um JSON padronizado contendo error.code, error.message e error.details.',
        'status' => 'Status HTTP',
        'code' => 'error.code',
        'when' => 'Quando ocorre',
        'items' => [
            '401' => 'Token ausente, inválido ou já revogado.',
            '402' => 'Cota de requisições ou tokens de IA do plano esgotada no período.',
            '403' => 'Token sem a ability ai:query ou conta suspensa por um administrador.',
            '404' => 'Consulta não encontrada ou pertencente a outra conta.',
            '422' => 'Falha de validação nos campos enviados, schema acima do limite ou dialeto incompatível.',
            '429' => 'Limite de requisições por minuto ou por hora excedido.',
            '503' => 'Provedor de IA temporariamente indisponível ou não configurado.',
        ],
    ],
];
