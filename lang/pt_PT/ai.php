<?php

/*
| AI SQL Builder (namespace "ai").
|
| A tela traduz o codigo de erro devolvido pela API; a mensagem tecnica do
| servidor e so fallback. Nenhum texto aqui cita modelo ou provedor: isso e
| configuracao, nao interface.
*/

return [
    'form' => [
        'title' => 'Descreva a consulta',
        'schema_source' => 'Fonte do schema',
        'schema' => 'Schema',
        'schema_placeholder' => 'Cole as tabelas, colunas e relações que a consulta pode usar.',
        'description' => 'O que deve fazer a consulta?',
        'description_placeholder' => 'Exemplo: liste os 10 clientes que mais gastaram nos últimos 30 dias.',
        'dialect' => 'Dialeto',
        'target' => 'Saída',
        'submit' => 'Gerar',
        'submitting' => 'A enviar...',
        'schema_limit' => 'Schema: {used} de {limit}',
        'description_limit' => 'Descrição: {used} de {limit}',
        'over_limit' => 'Isto ultrapassa o limite do seu plano.',
        'required' => 'Preencha o schema e a descrição.',
        'sensitive' => 'Não cole palavras-passe, tokens nem dados pessoais.',
    ],

    'sources' => [
        'ddl' => 'DDL',
        'json' => 'JSON',
        'migration' => 'Migration Laravel',
        'models' => 'Models SQLModel',
    ],

    'dialects' => [
        'mysql' => 'MySQL',
        'pgsql' => 'PostgreSQL',
        'sqlsrv' => 'SQL Server',
        'sqlite' => 'SQLite',
    ],

    'targets' => [
        'sql' => 'SQL',
        'laravel_query_builder' => 'Laravel Query Builder',
        'eloquent' => 'Eloquent',
        'sqlmodel' => 'SQLModel',
        'sqlalchemy' => 'SQLAlchemy',
        'django_orm' => 'Django ORM',
        'prisma' => 'Prisma',
        'drizzle' => 'Drizzle ORM',
        'typeorm' => 'TypeORM',
    ],

    'states' => [
        'idle' => 'Pronto quando quiser.',
        'queued' => 'Na fila...',
        'processing' => 'A gerar...',
        'done' => 'Resultado pronto.',
        'failed' => 'Não foi possível gerar o resultado.',
        'timeout' => 'Está a demorar mais do que o esperado. Pode consultar novamente.',
        'retry' => 'Consultar novamente',
        'poll_hint' => 'Pode manter este separador aberto; o resultado aparece aqui.',
    ],

    'guest' => [
        'title' => 'Entre para usar o AI SQL Builder',
        'body' => 'O AI SQL Builder precisa de uma conta verificada: o schema e a descrição passam pelo fornecedor de IA e consomem a quota do plano.',
        'cta' => 'Entrar',
    ],

    'unverified' => [
        'title' => 'Confirme o seu e-mail para usar a IA',
        'body' => 'Enviámos uma ligação de confirmação para o seu e-mail. Confirme antes de gerar uma consulta.',
        'cta' => 'Reenviar confirmação',
        'sent' => 'Confirmação enviada. Verifique a sua caixa de entrada.',
        'failed' => 'Não foi possível enviar a confirmação. Tente novamente mais tarde.',
    ],

    'suspended' => [
        'title' => 'Conta suspensa',
        'body' => 'Esta conta não pode usar o AI SQL Builder neste momento. Contacte o suporte.',
    ],

    'unavailable' => [
        'title' => 'IA indisponível',
        'body' => 'O serviço de IA não está configurado neste momento. Tente novamente mais tarde.',
    ],

    'quota' => [
        'title' => 'Quota esgotada',
        'body_uses' => 'Utilizou todos os {limit} usos gratuitos desta conta.',
        'body_tokens' => 'Utilizou todos os {limit} tokens deste período.',
        'remaining_uses' => 'Restam {remaining} de {limit} usos',
        'remaining_tokens' => 'Restam {remaining} de {limit} tokens',
        'cta' => 'Ver planos',
    ],

    'result' => [
        'title' => 'Resultado',
        'copy' => 'Copiar',
        'copied' => 'Copiado',
        'download' => 'Transferir',
        'download_name' => 'consulta',
        'assumptions' => 'Pressupostos',
        'warnings' => 'Avisos',
        'tables' => 'Tabelas utilizadas',
        'tokens_in' => 'Entrada: {count} tokens',
        'tokens_out' => 'Saída: {count} tokens',
        'cache_hit' => 'Resultado em cache',
        'clarification' => 'O modelo precisa de mais informação',
        'empty' => 'Nada gerado ainda.',
    ],

    'errors' => [
        'generic' => 'Algo correu mal. Tente novamente.',
        'network' => 'Não foi possível falar com o servidor. Verifique a ligação e tente novamente.',
        'validation' => 'Verifique os campos do formulário e tente novamente.',
        'unauthenticated' => 'A sua sessão expirou. Entre novamente.',
        'forbidden' => 'Esta conta não pode usar o AI SQL Builder.',
        'email_unverified' => 'Confirme o seu e-mail antes de usar a IA.',
        'account_suspended' => 'Esta conta está suspensa.',
        'not_found' => 'Pedido não encontrado.',
        'csrf' => 'A sua sessão expirou. Recarregue a página e tente novamente.',
        'rate_limited' => 'Demasiados pedidos. Tente novamente em {seconds} segundos.',
        'quota_exhausted' => 'Quota esgotada. Veja os planos para continuar.',
        'unavailable' => 'O serviço de IA está indisponível neste momento.',
        'timeout' => 'O pedido demorou demasiado. Tente novamente.',
        'provider_unavailable' => 'O fornecedor de IA está indisponível neste momento. Tente mais tarde.',
        'invalid_response' => 'A IA devolveu uma resposta inválida.',
        'unsafe_sql' => 'O SQL gerado foi bloqueado pela verificação de segurança.',
        'invalid_contract' => 'A resposta da IA não veio no formato esperado.',
        'internal' => 'Erro interno ao processar o pedido.',
        'schema_too_large' => 'O schema ultrapassa o limite de {limit} do seu plano.',
        'description_too_long' => 'A descrição ultrapassa o limite de {limit} caracteres do seu plano.',
        'unsupported_dialect' => 'A saída escolhida não aceita este dialeto.',
    ],

    'warnings' => [
        'review' => 'Reveja o resultado antes de o usar. A consulta nunca é executada aqui.',
        'sensitive' => 'Não cole palavras-passe, tokens nem dados pessoais no schema.',
    ],
];
