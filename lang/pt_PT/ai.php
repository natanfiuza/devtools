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
        'title' => 'Descreva a consulta ou operação',
        'schema_source' => 'Fonte do schema',
        'schema' => 'Schema',
        'schema_placeholder' => 'Cole as tabelas, colunas e relações que a consulta ou operação pode usar.',
        'description' => 'O que deve fazer a consulta ou operação?',
        'description_placeholder' => 'Exemplo: liste os 10 clientes mais ativos ou atualize o estado das encomendas pendentes.',
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
        'explanation' => 'Como a consulta foi construída',
        'tip' => 'Dica de boas práticas',
        'assumptions' => 'Pressupostos',
        'warnings' => 'Avisos',
        'tables' => 'Tabelas utilizadas',
        'tokens_in' => 'Entrada: {count} tokens',
        'tokens_out' => 'Saída: {count} tokens',
        'cache_hit' => 'Resultado em cache',
        'clarification' => 'O modelo precisa de mais informação',
        'empty' => 'Nada gerado ainda.',
    ],

    'tips' => [
        'sql' => 'Certifique-se de que as colunas presentes em cláusulas WHERE, JOIN e ORDER BY possuem índices apropriados. Use EXPLAIN para avaliar o plano de execução.',
        'laravel_query_builder' => 'Prefira iniciar consultas a partir do Model (ex: User::query()) em vez de DB::table(\'users\'). Isto preserva conversão de atributos, âmbitos locais, relações e conclusão automática no IDE.',
        'eloquent' => 'Para evitar o problema de N+1 consultas, utilize sempre o carregamento adiantado (eager loading) com with() ao aceder a relações na listagem.',
        'sqlmodel' => 'Utilize a sessão do SQLModel com select(Model) para manter a validação do Pydantic e a integração com SQLAlchemy em simultâneo.',
        'sqlalchemy' => 'No SQLAlchemy 2.0+, utilize o estilo moderno session.scalars(select(Model)) em vez do método legado session.query(Model).',
        'django_orm' => 'Utilize select_related() para relações ForeignKey/OneToOne e prefetch_related() para ManyToMany de modo a otimizar acessos e evitar consultas adicionais.',
        'prisma' => 'Prefira utilizar select para devolver apenas os campos necessários na consulta, reduzindo a carga de dados transmitida.',
        'drizzle' => 'O Drizzle permite consultas relacionais (db.query.*) com melhor ergonomia e inferência de tipos completa, ou a sintaxe clássica em formato SQL core.',
        'typeorm' => 'Ao utilizar o QueryBuilder do TypeORM, defina identificadores únicos e selecione apenas as colunas estritamente necessárias.',
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
        'review' => 'Reveja o resultado antes de o usar. A consulta ou operação nunca é executada aqui.',
        'sensitive' => 'Não cole palavras-passe, tokens nem dados pessoais no schema.',
    ],

    'history' => [
        'title' => 'Consultas Anteriores',
        'search_placeholder' => 'Pesquisar no schema, prompt ou título...',
        'loading' => 'A carregar histórico...',
        'error' => 'Não foi possível carregar o histórico de consultas.',
        'empty' => 'Nenhuma consulta realizada ainda.',
        'no_search_results' => 'Nenhuma consulta encontrada para "{query}".',
        'untitled' => 'Consulta sem título',
        'collapse' => 'Recolher histórico',
        'expand' => 'Expandir histórico',
    ],
];
