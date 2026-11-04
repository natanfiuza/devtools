<?php

/*
| Documentacao publica do BadgeForge (`/{locale}/docs/badgeforge`, ADR-082).
|
| Os nomes de provider e os nomes de metrica sao marcas/nomes proprios e nao
| se traduzem: o texto da metrica vem de `tools.badgeforge.provider.metrics.*`.
| As traducoes abaixo cobrem apenas o texto explicativo da pagina.
*/

return [

    'badgeforge' => [

        'meta' => [
            'title' => 'Documentação do BadgeForge',
            'description' => 'Badges dinâmicas para estrelas do GitHub, versões do npm, downloads do Packagist e pulls do Docker, além de badges pessoais com credencial vinculada a cada badge.',
        ],

        'hero' => [
            'eyebrow' => 'BadgeForge',
            'title' => 'Badges que se atualizam sozinhas',
            'subtitle' => 'As badges estáticas são desenhadas no servidor. As de provider leem GitHub, npm, Packagist e Docker Hub de forma anônima, com resposta em cache que nunca quebra a imagem no seu README.',
            'open_tool' => 'Abrir o construtor',
        ],

        'endpoints' => [
            'title' => 'Endpoints',
            'static' => 'Badges estáticas',
            'provider' => 'Badges de provider',
            'personal' => 'Badges pessoais',
            'json' => 'Badges de endpoint JSON',
        ],

        'quick_start' => [
            'title' => 'Comece em um minuto',
            'body' => 'Cole o Markdown no seu README. O GitHub renderiza o SVG direto e toda requisição é cacheada do nosso lado.',
            'copy' => 'Copiar',
            'copied' => 'Copiado',
        ],

        'static' => [
            'title' => 'Badges estáticas',
            'body' => 'Rótulo e mensagem fazem parte da URL. Todo estilo, cor, logo e fonte empacotada do construtor está disponível na query string.',
            'example_label' => 'Exemplo',
        ],

        'providers' => [
            'title' => 'Badges de provider',
            'intro' => 'Cada provider expõe uma lista curta de métricas. O caminho depois da métrica segue o próprio provider: repositório, pacote ou imagem.',
            'metric' => 'Métrica',
            'path' => 'Caminho',
            'access' => 'Acesso',
            'public' => 'Público',
            'private' => 'Item privado',
            'documentation' => 'Documentação da API do provider',
            'unknown_note' => 'Uma métrica inexistente, ou uma resposta que o provider não reconhece, rende a badge como “unknown”.',
            'descriptions' => [
                'github' => 'Estrelas, forks, issues abertas e licença pela API do repositório. Tokens fine-grained funcionam para repositórios privados.',
                'npm' => 'Versão publicada mais recente e contagem de downloads por mês. A contagem de downloads é sempre pública.',
                'packagist' => 'Downloads totais e versão mais recente de um pacote Composer, sempre pelo registro público.',
                'docker' => 'Contagem de pulls do Docker Hub. Imagens privadas usam o username mais um personal access token.',
            ],
        ],

        'personal' => [
            'title' => 'Badges pessoais',
            'intro' => 'Contas pagas publicam a própria badge em uma URL opaca. A URL leva seu username e o ULID da badge — nunca um token.',
            'url_label' => 'Formato da URL',
            'username_required' => 'Defina um username na sua conta antes de criar uma badge pessoal.',
            'providers_title' => 'Itens privados',
            'providers_body' => 'Uma badge pessoal pode ler um repositório, pacote ou imagem privada. A credencial é sua, fica cifrada e é vinculada somente àquela badge.',
            'github' => 'GitHub: um personal access token com leitura no repositório (fine-grained ou clássico).',
            'npm' => 'npm: um token para pacote privado. A contagem de downloads (dm) é sempre pública.',
            'docker' => 'Docker Hub: username mais um personal access token; os dois são exigidos para imagens privadas.',
            'packagist' => 'Packagist: somente público. Pacotes privados não são suportados.',
            'security_title' => 'Como a credencial é guardada',
            'security_body' => 'O token é cifrado com a chave da aplicação e nunca aparece na URL da imagem, nas props da página, nos logs ou nas exportações. Se a chave da aplicação for trocada, a badge continua servindo a última imagem boa até você definir a credencial de novo.',
            'manage_cta' => 'Gerenciar badges pessoais',
            'tool_cta' => 'Criar uma badge pessoal no construtor',
            'refresh_note' => 'A imagem é stateless. Para forçar uma nova geração use a ação de refresh no painel; a URL pública ignora ?refresh=1.',
        ],

        'endpoint' => [
            'title' => 'Badges de endpoint JSON',
            'intro' => 'Cadastre uma URL HTTPS no painel e receba uma URL de badge opaca. A URL de origem nunca aparece na imagem, e quem lê o seu README não precisa de conta para ver a badge.',
            'url_label' => 'Formato da URL',
            'contract_title' => 'JSON esperado',
            'contract_body' => 'A origem precisa responder no schema shields.io versão 1: schemaVersion 1, label e message não vazios, e color/labelColor opcionais usados como padrão — um parâmetro da query sempre vence. Os demais campos são ignorados.',
            'cache_title' => 'Cache e fallback',
            'cache_body' => 'A resposta fica em cache por {ttl} segundos. Se a origem cair ou nos limitar, a última resposta boa continua sendo servida (STALE); sem resposta anterior, a badge mostra “unavailable”, nunca um erro.',
            'limits_title' => 'Limite de requisições',
            'limits_anonymous' => 'Badge desconhecida ou anônima: {limit} requisições por minuto por IP.',
            'limits_free' => 'Dono com conta gratuita: {limit} requisições por minuto.',
            'limits_paid' => 'Dono com conta paga: {limit} requisições por minuto.',
            'security_title' => 'Por que é seguro',
            'security_body' => 'Só HTTPS na porta 443, sem redirect, e todos os IPs resolvidos precisam ser públicos. A conexão é pinada no IP validado, então uma mudança de DNS entre a checagem e a requisição não alcança um endereço interno. A URL de origem fica no servidor: não está na página, no log nem na URL da imagem.',
            'manage_cta' => 'Gerenciar endpoints JSON',
        ],
        'cache' => [
            'title' => 'Cache, ETag e respostas antigas',
            'body' => 'As respostas de provider ficam em cache por {ttl} segundos. A imagem sai com ETag e Cache-Control, então uma segunda requisição recebe 304 quando nada mudou.',
            'stale_title' => 'Stale-if-error',
            'stale_body' => 'Quando o provider falha ou limita nossa taxa, a última resposta boa continua sendo servida. Sem resposta anterior, a badge mostra uma mensagem curta de indisponibilidade em vez de erro.',
            'refresh_title' => 'Refresh',
            'refresh_body' => 'URLs públicas de provider nunca forçam refresh. Badges pessoais têm uma ação de refresh que só o dono dispara.',
        ],

        'errors' => [
            'title' => 'Erros e fallback',
            'intro' => 'Uma URL de badge sempre responde com imagem. As exceções são um caminho inexistente (404) e um pedido de PNG em servidor sem Imagick (503).',
            'cached' => 'Provider fora do ar ou com limite de taxa: a imagem em cache é servida (cabeçalho X-Badge-Cache: STALE).',
            'fallback' => 'Ainda sem imagem em cache: a badge renderiza o rótulo da métrica com “indisponível”.',
            'unknown' => 'O provider respondeu, mas a métrica não pode ser lida: a badge renderiza “unknown”.',
            'not_found' => 'Provider desconhecido, métrica desconhecida ou caminho inválido: 404.',
            'private' => 'Item privado sem credencial nunca é servido por uma URL pública de provider.',
        ],

        'security' => [
            'title' => 'Segurança e privacidade',
            'anonymous' => 'Métricas públicas são lidas anonimamente. Não existe token global de provider em configuração ou ambiente.',
            'credential' => 'As credenciais são do dono da badge, ficam cifradas em repouso e valem para uma única badge.',
            'logs' => 'As respostas de provider são projetadas antes do cache: só os números que a badge mostra são guardados. Descrições, READMEs e tokens nunca chegam ao cache nem aos logs.',
            'url' => 'URLs de badge pessoal carregam apenas o username e o ULID; revogar o acesso é excluir a badge.',
        ],

    ],

];
