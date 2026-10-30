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
        'description' => 'O que a consulta ou operação deve fazer?',
        'description_placeholder' => 'Exemplo: liste os 10 clientes mais ativos ou atualize o status dos pedidos pendentes.',
        'dialect' => 'Dialeto',
        'target' => 'Saída',
        'submit' => 'Gerar',
        'submitting' => 'Enviando...',
        'schema_limit' => 'Schema: {used} de {limit}',
        'description_limit' => 'Descrição: {used} de {limit}',
        'over_limit' => 'Isto passa do limite do seu plano.',
        'required' => 'Preencha o schema e a descrição.',
        'sensitive' => 'Não cole senhas, tokens nem dados pessoais.',
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
        'idle' => 'Pronto quando você quiser.',
        'queued' => 'Na fila...',
        'processing' => 'Gerando...',
        'done' => 'Resultado pronto.',
        'failed' => 'Não foi possível gerar o resultado.',
        'timeout' => 'Está demorando mais que o esperado. Você pode consultar de novo.',
        'retry' => 'Consultar de novo',
        'poll_hint' => 'Você pode manter esta aba aberta; o resultado aparece aqui.',
    ],

    'guest' => [
        'title' => 'Entre para usar o AI SQL Builder',
        'body' => 'O AI SQL Builder precisa de uma conta verificada: o schema e a descrição passam pelo provedor de IA e consomem a cota do plano.',
        'cta' => 'Entrar',
    ],

    'unverified' => [
        'title' => 'Confirme seu e-mail para usar a IA',
        'body' => 'Enviamos um link de confirmação para o seu e-mail. Confirme antes de gerar uma consulta.',
        'cta' => 'Reenviar confirmação',
        'sent' => 'Confirmação enviada. Confira sua caixa de entrada.',
        'failed' => 'Não foi possível enviar a confirmação. Tente novamente mais tarde.',
    ],

    'suspended' => [
        'title' => 'Conta suspensa',
        'body' => 'Esta conta não pode usar o AI SQL Builder no momento. Fale com o suporte.',
    ],

    'unavailable' => [
        'title' => 'IA indisponível',
        'body' => 'O serviço de IA não está configurado no momento. Tente novamente mais tarde.',
    ],

    'quota' => [
        'title' => 'Cota esgotada',
        'body_uses' => 'Você usou todos os {limit} usos gratuitos desta conta.',
        'body_tokens' => 'Você usou todos os {limit} tokens deste período.',
        'remaining_uses' => 'Restam {remaining} de {limit} usos',
        'remaining_tokens' => 'Restam {remaining} de {limit} tokens',
        'cta' => 'Ver planos',
    ],

    'result' => [
        'title' => 'Resultado',
        'copy' => 'Copiar',
        'copied' => 'Copiado',
        'download' => 'Baixar',
        'download_name' => 'consulta',
        'explanation' => 'Como a consulta foi construída',
        'tip' => 'Dica de boas práticas',
        'assumptions' => 'Premissas',
        'warnings' => 'Avisos',
        'tables' => 'Tabelas usadas',
        'tokens_in' => 'Entrada: {count} tokens',
        'tokens_out' => 'Saída: {count} tokens',
        'cache_hit' => 'Resultado em cache',
        'clarification' => 'O modelo precisa de mais informação',
        'empty' => 'Nada gerado ainda.',
    ],

    'tips' => [
        'sql' => 'Certifique-se de que colunas presentes em cláusulas WHERE, JOIN e ORDER BY possuam índices apropriados. Use EXPLAIN para avaliar o plano de execução.',
        'laravel_query_builder' => 'Prefira iniciar queries a partir do Model (ex: User::query()) em vez de DB::table(\'users\'). Isso preserva cast de atributos, scopes locais, relacionamentos e autocompletion no IDE.',
        'eloquent' => 'Para evitar o problema de N+1 queries, use sempre o carregamento adiantado (eager loading) com with() ao acessar relacionamentos na listagem.',
        'sqlmodel' => 'Utilize a sessão do SQLModel com select(Model) para manter a validação do Pydantic e a integração com SQLAlchemy ao mesmo tempo.',
        'sqlalchemy' => 'No SQLAlchemy 2.0+, use o estilo moderno session.scalars(select(Model)) em vez do método legado session.query(Model).',
        'django_orm' => 'Use select_related() para relacionamentos ForeignKey/OneToOne e prefetch_related() para ManyToMany para otimizar acessos e evitar consultas extras.',
        'prisma' => 'Prefira usar select para retornar apenas os campos necessários na consulta, reduzindo o payload trafegado entre o banco e a aplicação.',
        'drizzle' => 'O Drizzle permite queries relacionais (db.query.*) com melhor ergonomia e inferência de tipos completa, ou a sintaxe clássica em formato SQL core.',
        'typeorm' => 'Ao usar o QueryBuilder do TypeORM, defina aliases únicos e selecione apenas as colunas estritamente necessárias para evitar sobrecarga de hidratação.',
    ],

    'errors' => [
        'generic' => 'Algo deu errado. Tente novamente.',
        'network' => 'Não foi possível falar com o servidor. Confira sua conexão e tente de novo.',
        'validation' => 'Confira os campos do formulário e tente de novo.',
        'unauthenticated' => 'Sua sessão expirou. Entre novamente.',
        'forbidden' => 'Esta conta não pode usar o AI SQL Builder.',
        'email_unverified' => 'Confirme seu e-mail antes de usar a IA.',
        'account_suspended' => 'Esta conta está suspensa.',
        'not_found' => 'Solicitação não encontrada.',
        'csrf' => 'Sua sessão expirou. Recarregue a página e tente de novo.',
        'rate_limited' => 'Muitas solicitações. Tente de novo em {seconds} segundos.',
        'quota_exhausted' => 'Cota esgotada. Veja os planos para continuar.',
        'unavailable' => 'O serviço de IA está indisponível no momento.',
        'timeout' => 'A solicitação demorou demais. Tente novamente.',
        'provider_unavailable' => 'O provedor de IA está indisponível no momento. Tente mais tarde.',
        'invalid_response' => 'A IA devolveu uma resposta inválida.',
        'unsafe_sql' => 'O SQL gerado foi bloqueado pela checagem de segurança.',
        'invalid_contract' => 'A resposta da IA não veio no formato esperado.',
        'internal' => 'Erro interno ao processar a solicitação.',
        'schema_too_large' => 'O schema passa do limite de {limit} do seu plano.',
        'description_too_long' => 'A descrição passa do limite de {limit} caracteres do seu plano.',
        'unsupported_dialect' => 'A saída escolhida não aceita este dialeto.',
    ],

    'warnings' => [
        'review' => 'Revise o resultado antes de usar. A consulta ou operação nunca é executada aqui.',
        'sensitive' => 'Não cole senhas, tokens nem dados pessoais no schema.',
    ],

    'history' => [
        'title' => 'Consultas Anteriores',
        'search_placeholder' => 'Buscar no schema, prompt ou título...',
        'loading' => 'Carregando histórico...',
        'error' => 'Não foi possível carregar o histórico de consultas.',
        'empty' => 'Nenhuma consulta realizada ainda.',
        'no_search_results' => 'Nenhuma consulta encontrada para "{query}".',
        'untitled' => 'Consulta sem título',
        'collapse' => 'Recolher histórico',
        'expand' => 'Expandir histórico',
    ],
];
