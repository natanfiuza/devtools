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
            'description' => 'Badges dinâmicas para estrelas do GitHub, versões do npm, transferências do Packagist, pulls do Docker, cobertura do Codecov e Coveralls e versões de runtime do Laravel, PHP e Python, além de badges pessoais com credencial associada a cada badge.',
        ],

        'hero' => [
            'eyebrow' => 'BadgeForge',
            'title' => 'Badges que se atualizam sozinhas',
            'subtitle' => 'As badges estáticas são desenhadas no servidor. As de provider leem o GitHub, o npm, o Packagist, o Docker Hub, o Codecov, o Coveralls e as últimas versões do Laravel, PHP e Python de forma anónima, com resposta em cache que nunca quebra a imagem no seu README.',
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
            'title' => 'Comece num minuto',
            'body' => 'Cole o Markdown no seu README. O GitHub renderiza o SVG diretamente e todos os pedidos são guardados em cache do nosso lado.',
            'copy' => 'Copiar',
            'copied' => 'Copiado',
        ],

        'static' => [
            'title' => 'Badges estáticas',
            'body' => 'Rótulo e mensagem fazem parte do URL. Todos os estilos, cores, logos e tipos de letra incluídos no construtor estão disponíveis na query string.',
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
            'unknown_note' => 'Uma métrica inexistente, ou uma resposta que o provider não reconhece, produz a badge como “unknown”.',
            'descriptions' => [
                'github' => 'Estrelas, forks, issues abertas e licença pela API do repositório. Tokens fine-grained funcionam para repositórios privados.',
                'npm' => 'Versão publicada mais recente e contagem de downloads por mês. A contagem de downloads é sempre pública.',
                'packagist' => 'Downloads totais e versão mais recente de um pacote Composer, sempre pelo registo público.',
                'docker' => 'Contagem de pulls do Docker Hub. Imagens privadas usam o username mais um personal access token.',
                'codecov' => 'Cobertura de linhas do branch predefinido, lida da API do Codecov. A badge é sempre pública.',
                'coveralls' => 'Percentagem de cobertura do branch predefinido, lida do endpoint JSON do Coveralls. Apenas público.',
                'laravel' => 'Última versão estável do Laravel e transferências totais no Packagist. As duas métricas partilham um único pedido em cache.',
                'php' => 'Última versão estável do PHP publicada no php.net. Pública e anónima.',
                'python' => 'Última versão estável do Python, lida do endoflife.date. Pública e anónima.',
            ],
        ],

        'personal' => [
            'title' => 'Badges pessoais',
            'intro' => 'As contas pagas publicam a sua própria badge num URL opaco. O URL leva o seu username e o ULID da badge — nunca um token.',
            'url_label' => 'Formato do URL',
            'username_required' => 'Defina um username na sua conta antes de criar uma badge pessoal.',
            'providers_title' => 'Itens privados',
            'providers_body' => 'Uma badge pessoal pode ler um repositório, pacote ou imagem privada. A credencial é sua, fica cifrada e está associada apenas àquela badge.',
            'github' => 'GitHub: um personal access token com leitura no repositório (fine-grained ou clássico).',
            'npm' => 'npm: um token para pacote privado. A contagem de downloads (dm) é sempre pública.',
            'docker' => 'Docker Hub: username mais um personal access token; ambos são exigidos para imagens privadas.',
            'packagist' => 'Packagist: apenas público. Pacotes privados não são suportados.',
            'security_title' => 'Como a credencial é guardada',
            'security_body' => 'O token é cifrado com a chave da aplicação e nunca aparece no URL da imagem, nas props da página, nos logs ou nas exportações. Se a chave da aplicação for trocada, a badge continua a servir a última imagem boa até definir a credencial de novo.',
            'manage_cta' => 'Gerir badges pessoais',
            'tool_cta' => 'Criar uma badge pessoal no construtor',
            'refresh_note' => 'A imagem é stateless. Para forçar uma nova geração use a ação de refresh no painel; o URL público ignora ?refresh=1.',
        ],

        'endpoint' => [
            'title' => 'Badges de endpoint JSON',
            'intro' => 'Registe um URL HTTPS no painel e receba um URL de badge opaco. O URL de origem nunca aparece na imagem, e quem lê o seu README não precisa de conta para ver a badge.',
            'url_label' => 'Formato do URL',
            'contract_title' => 'JSON esperado',
            'contract_body' => 'A origem tem de responder no schema shields.io versão 1: schemaVersion 1, label e message não vazios, e color/labelColor opcionais usados como padrão — um parâmetro da query vence sempre. Os restantes campos são ignorados.',
            'cache_title' => 'Cache e fallback',
            'cache_body' => 'A resposta fica em cache durante {ttl} segundos. Se a origem falhar ou nos limitar, a última resposta boa continua a ser servida (STALE); sem resposta anterior, a badge mostra “unavailable”, nunca um erro.',
            'limits_title' => 'Limite de pedidos',
            'limits_anonymous' => 'Badge desconhecida ou anónima: {limit} pedidos por minuto por IP.',
            'limits_free' => 'Dono com conta gratuita: {limit} pedidos por minuto.',
            'limits_paid' => 'Dono com conta paga: {limit} pedidos por minuto.',
            'security_title' => 'Porque é seguro',
            'security_body' => 'Apenas HTTPS na porta 443, sem redirect, e todos os IPs resolvidos têm de ser públicos. A ligação é fixada no IP validado, por isso uma mudança de DNS entre a verificação e o pedido não alcança um endereço interno. O URL de origem fica no servidor: não está na página, no log nem no URL da imagem.',
            'manage_cta' => 'Gerir endpoints JSON',
        ],
        'custom' => [
            'title' => 'Fornecedores personalizados',
            'intro' => 'Contas pagas podem transformar qualquer documento JSON público numa badge. Declara um template de URL HTTPS, os parâmetros do caminho e um JSON Pointer por métrica; o servidor apenas executa esse mapeamento declarativo.',
            'url_label' => 'Formato do URL',
            'paid_title' => 'Apenas planos pagos',
            'paid_body' => 'Criar e editar exigem plano pago. Após um downgrade as badges existentes continuam a servir e podem ser copiadas e eliminadas, mas não editadas.',
            'pointer_title' => 'JSON Pointer',
            'pointer_body' => 'Cada métrica lê um valor escalar com RFC 6901, por exemplo /stargazers_count ou /items/0/score. Valor ausente ou não escalar cai na mensagem neutra em vez de quebrar a imagem.',
            'security_title' => 'URL opaco e origem privada',
            'security_body' => 'A imagem pública usa um identificador opaco em vez do URL de origem. O template fica no servidor, nunca volta ao navegador e nunca aparece em log, props de página ou exportação CSV. Os pedidos apenas usam HTTPS, IP público, porta 443, sem redirect e sem credenciais.',
            'manage_cta' => 'Gerir fornecedores personalizados',
        ],
        'cache' => [
            'title' => 'Cache, ETag e respostas antigas',
            'body' => 'As respostas de provider ficam em cache durante {ttl} segundos. A imagem sai com ETag e Cache-Control, por isso um segundo pedido recebe 304 quando nada mudou.',
            'stale_title' => 'Stale-if-error',
            'stale_body' => 'Quando o provider falha ou limita a nossa taxa, a última resposta boa continua a ser servida. Sem resposta anterior, a badge mostra uma mensagem curta de indisponibilidade em vez de erro.',
            'refresh_title' => 'Refresh',
            'refresh_body' => 'URLs públicos de provider nunca forçam refresh. As badges pessoais têm uma ação de refresh que só o dono aciona.',
        ],

        'errors' => [
            'title' => 'Erros e fallback',
            'intro' => 'Um URL de badge responde sempre com imagem. As exceções são um caminho inexistente (404) e um pedido de PNG num servidor sem Imagick (503).',
            'cached' => 'Provider fora do ar ou com limite de taxa: a imagem em cache é servida (cabeçalho X-Badge-Cache: STALE).',
            'fallback' => 'Ainda sem imagem em cache: a badge renderiza o rótulo da métrica com “indisponível”.',
            'unknown' => 'O provider respondeu, mas a métrica não pode ser lida: a badge renderiza “unknown”.',
            'not_found' => 'Provider desconhecido, métrica desconhecida ou caminho inválido: 404.',
            'private' => 'Um item privado sem credencial nunca é servido por um URL público de provider.',
        ],

        'security' => [
            'title' => 'Segurança e privacidade',
            'anonymous' => 'As métricas públicas são lidas anonimamente. Não existe token global de provider na configuração nem no ambiente.',
            'credential' => 'As credenciais pertencem ao dono da badge, ficam cifradas em repouso e valem para uma única badge.',
            'logs' => 'As respostas de provider são projetadas antes do cache: só os números que a badge mostra são guardados. Descrições, READMEs e tokens nunca chegam ao cache nem aos logs.',
            'url' => 'URLs de badge pessoal carregam apenas o username e o ULID; revogar o acesso é eliminar a badge.',
        ],

    ],

];
