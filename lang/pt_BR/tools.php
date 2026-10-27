<?php

/*
|--------------------------------------------------------------------------
| Registro de tools (textos)
|--------------------------------------------------------------------------
|
| O `manifest.json` de cada tool guarda so identidade e ciclo de vida; todo
| texto visivel mora aqui, por convencao `tools.<slug>.<campo>` (o slug e o
| nome do diretorio). Os nomes das tools ficam em ingles em todos os
| idiomas: sao nomes de produto, como aparecem na SPEC e no DESIGN.
|
*/

return [

    'status' => [
        'soon' => 'Em breve',
    ],

    /*
    | Textos da casca de qualquer tool (os paineis, a barra de acoes e os
    | estados). O que e de uma ferramenta so mora em `tools.<slug>.*`.
    */
    'ui' => [
        'input' => 'Entrada',
        'output' => 'Saída',
        'resize' => 'Redimensionar os painéis',
        'drop' => 'Solte o arquivo aqui',

        'actions' => [
            'open' => 'Abrir arquivo',
            'example' => 'Exemplo',
            'clear' => 'Limpar',
            'copy' => 'Copiar',
            'copied' => 'Copiado',
            'copy_manual' => 'Selecione o texto e copie',
            'download' => 'Baixar',
        ],

        'states' => [
            'empty' => 'Cole, digite ou solte um arquivo para começar.',
            'parsing' => 'Processando...',
            'unavailable' => 'Ferramenta indisponível.',
            'invalid' => 'Entrada inválida na linha {line}, coluna {column}.',
            'invalid_no_position' => 'Entrada inválida.',
            'stale_output' => 'Saída da última entrada válida.',
            'too_large' => 'O texto tem {size} e passa do limite de {limit}: nada será processado.',
            'file_too_large' => 'O arquivo tem {size} e passa do limite de {limit}.',
            'file_type' => 'Formato não aceito. Envie um arquivo {formats}.',
            'file_unreadable' => 'Não foi possível ler o arquivo.',
            'drop_hint' => 'Solte para abrir o arquivo.',
        ],

        'limits' => [
            'of' => 'de {limit}',
        ],

        'shortcuts' => [
            'title' => 'Atalhos',
        ],
    ],

    'categories' => [
        'json' => 'JSON',
        'sql' => 'SQL',
        'encode' => 'Codificação e hash',
        'security' => 'Segurança',
        'time' => 'Data e hora',
        'text' => 'Texto',
    ],

    'json-viewer' => [
        'name' => 'JSON Viewer',
        'description' => 'Explore, formate e valide JSON em árvore, com busca e cópia de qualquer caminho.',
        'keywords' => 'formatar, validar, árvore, minificar, caminho',
        'intro' => 'Cole um JSON para ver a árvore colorida, formatar, minimizar e copiar o caminho de qualquer nó. Tudo roda no seu navegador: nada do que você cola aqui é enviado para o servidor.',

        'format' => 'Formatar',
        'minify' => 'Minificar',
        'sort_keys' => 'Ordenar chaves',
        'expand_all' => 'Expandir tudo',
        'collapse_all' => 'Recolher tudo',
        'download_name' => 'documento',
        'file_types' => 'JSON ou TXT',

        'tree' => [
            'toggle' => 'Recolher ou expandir',
            'copy_path' => 'Copiar caminho',
            'copied' => 'Caminho copiado: {path}',
        ],

        'search' => [
            'placeholder' => 'Buscar chave ou valor',
            'label' => 'Buscar na árvore',
            'clear' => 'Limpar busca',
            'results' => 'Resultados: {count}',
            'truncated' => 'Mostrando os primeiros {count} resultados.',
            'none' => 'Nenhum resultado.',
        ],

        'stats' => [
            'keys' => 'Chaves: {count}',
            'items' => 'Itens: {count}',
        ],

        'shortcuts' => [
            'format' => 'Formatar o documento',
            'search' => 'Buscar na árvore',
            'clear' => 'Limpar a busca',
        ],
    ],

    'ai-sql-builder' => [
        'name' => 'AI SQL Builder',
        'description' => 'Descreva a consulta em linguagem natural e receba o SQL pronto para o seu dialeto.',
        'keywords' => 'linguagem natural, gerar consulta, banco de dados',
        'intro' => 'Descreva a consulta, cole o schema e escolha a saída: SQL, Eloquent, Prisma e mais. Esta é a única ferramenta que sai do navegador: o schema e a descrição vão para o provedor de IA e consomem a cota do plano. O SQL gerado nunca é executado aqui.',
    ],

    'json-to-code' => [
        'name' => 'JSON para código',
        'description' => 'Transforme JSON em tipos TypeScript, DTOs PHP e modelos Python.',
        'keywords' => 'gerar tipos, interface, dto, pydantic',
        'intro' => 'Cole um JSON e escolha o alvo: interfaces TypeScript, DTOs PHP com propriedades promovidas ou modelos Pydantic. A inferência roda no seu navegador: nada do que você cola aqui é enviado para o servidor.',

        'target' => 'Linguagem de destino',
        'root_name' => 'Nome do tipo raiz',
        'download_name' => 'tipos',
        'file_types' => 'JSON ou TXT',

        'targets' => [
            'typescript' => 'TypeScript',
            'php' => 'PHP (DTO)',
            'pydantic' => 'Pydantic',
        ],

        'states' => [
            'root_object' => 'A raiz precisa ser um objeto JSON com pelo menos uma chave.',
        ],

        'stats' => [
            'types' => 'Tipos: {count}',
        ],
    ],

    'sql-formatter' => [
        'name' => 'SQL Formatter',
        'description' => 'Formate consultas SQL nos dialetos MySQL, PostgreSQL, SQL Server e SQLite.',
        'keywords' => 'embelezar, indentar, consulta',
        'intro' => 'Cole a consulta e escolha o dialeto: o SQL sai indentado do lado direito, e a caixa das palavras-chave é a sua escolha. A formatação roda no seu navegador: nada do que você cola aqui é enviado para o servidor.',

        'dialect' => 'Dialeto',
        'keyword_case' => 'Caixa das palavras-chave',
        'download_name' => 'consulta',
        'file_types' => 'SQL ou TXT',

        'dialects' => [
            'mysql' => 'MySQL',
            'pgsql' => 'PostgreSQL',
            'sqlsrv' => 'SQL Server',
            'sqlite' => 'SQLite',
        ],

        'cases' => [
            'upper' => 'MAIÚSCULAS',
            'lower' => 'minúsculas',
            'preserve' => 'Manter',
        ],
    ],

    'base64-url-hash' => [
        'name' => 'Base64, URL e Hash',
        'description' => 'Codifique e decodifique Base64 e URL, e gere os hashes MD5, SHA-1, SHA-256 e SHA-512.',
        'keywords' => 'codificar, decodificar, resumo, digest',
        'intro' => 'Uma entrada, três leituras: o mesmo texto vira Base64, vira URL codificada ou vira hash. Escolha a aba à direita e a direção à esquerda. A conta roda no seu navegador: nada do que você cola aqui é enviado para o servidor.',

        'direction' => 'Direção',
        'file_types' => 'TXT',
        'row_copied' => '{label} copiado',

        'directions' => [
            'encode' => 'Codificar',
            'decode' => 'Decodificar',
        ],

        'modes' => [
            'base64' => 'Base64',
            'url' => 'URL',
            'hash' => 'Hash',
        ],

        'rows' => [
            'base64' => 'Base64',
            'urlSafe' => 'Base64 URL-safe',
            'component' => 'Componente',
            'form' => 'Formulário',
            'md5' => 'MD5',
            'sha1' => 'SHA-1',
            'sha256' => 'SHA-256',
            'sha512' => 'SHA-512',
        ],

        'states' => [
            'invalid_base64' => 'Isto não é o Base64 de um texto: há caractere fora do alfabeto, sobra um caractere solto no fim, ou os bytes não formam UTF-8.',
            'invalid_url' => 'Isto não é uma URL codificada válida: há uma sequência de escape quebrada ou bytes que não formam UTF-8.',
            'no_crypto' => 'Este navegador só expõe a Web Crypto em conexão segura (HTTPS), então as três linhas SHA ficam sem valor aqui. O MD5 continua — mas lembre: MD5 é checksum, e não assinatura.',
        ],
    ],

    'jwt-decoder' => [
        'name' => 'JWT Decoder',
        'description' => 'Leia o cabeçalho e o payload de um JWT. A assinatura não é validada.',
        'keywords' => 'token, bearer, claims, autenticação',
        'intro' => 'Cole o token (o prefixo Bearer pode vir junto) para ler o cabeçalho, o payload e as datas dos claims. A ferramenta decodifica e não valida a assinatura, e tudo roda no seu navegador: nada do que você cola aqui vai para o servidor.',

        'header' => 'Cabeçalho',
        'payload' => 'Payload',
        'signature' => 'Assinatura',
        'signature_bytes' => '{count} bytes',
        'not_verified' => 'não verificada',
        'block_copied' => '{label} copiado',

        'warning' => 'Este decodificador não valida a assinatura: ele mostra o que está escrito no token, e nada aqui prova que o token é autêntico.',

        'claims_title' => 'Datas',

        'claims' => [
            'expired' => 'expirado',
            'not_yet' => 'ainda não vale',
        ],

        'states' => [
            'shape' => 'Isto não é um JWT: são três partes separadas por ponto (cabeçalho, payload e assinatura), e aqui não há três.',
            'base64' => 'Alguma das partes não é base64url válido, ou os bytes não formam UTF-8.',
            'json' => 'O cabeçalho ou o payload não é um objeto JSON.',
        ],
    ],

    'timestamp-uuid' => [
        'name' => 'Timestamp e UUID',
        'description' => 'Converta timestamps Unix e datas, e gere valores UUID e ULID.',
        'keywords' => 'unix, epoch, data, guid',
        'intro' => 'Cole um instante — Unix em segundos ou milissegundos, uma data ou um UUID v7/ULID — para ver as seis leituras dele. O painel da direita também gera UUID v4, v7 e ULID. Tudo roda no seu navegador: nada do que você cola aqui é enviado ao servidor.',

        'now' => 'Agora',
        'kind' => 'Tipo de id',
        'quantity' => 'Quantidade',
        'generate' => 'Gerar',
        'ids_title' => 'Ids gerados: {count}',
        'copy_ids' => 'Copiar todos',
        'ids_copied' => '{count} ids copiados',
        'id_copied' => 'Id copiado',
        'row_copied' => '{label} copiado',
        'recognised' => 'O id colado guarda este instante ({kind})',

        'kinds' => [
            'uuid_v4' => 'UUID v4',
            'uuid_v7' => 'UUID v7',
            'ulid' => 'ULID',
        ],

        'readings' => [
            'unix_seconds' => 'Unix (segundos)',
            'unix_ms' => 'Unix (milissegundos)',
            'iso_utc' => 'ISO 8601 (UTC)',
            'iso_local' => 'ISO 8601 (local)',
            'long_date' => 'Data por extenso',
            'relative' => 'Tempo relativo',
        ],

        'states' => [
            'unreadable' => 'Não deu para ler um instante aqui: use o Unix em segundos ou milissegundos, uma data (ISO 8601) ou um UUID v7/ULID.',
        ],
    ],

    'regex-tester' => [
        'name' => 'Regex Tester',
        'description' => 'Teste expressões regulares com destaque ao vivo das correspondências e dos grupos.',
        'keywords' => 'padrão, correspondência, grupos',
        'intro' => 'Escreva a expressão regular na barra de cima e o texto de teste no painel da esquerda: as correspondências saem destacadas, uma a uma, com os grupos numerados e nomeados, e o campo de substituição mostra o texto trocado. A busca corre no seu navegador: nada do que você cola aqui é enviado ao servidor.',

        'pattern' => 'Expressão regular',
        'replacement' => 'Substituir por',
        'replacement_hint' => 'Ex.: $1 ou $<nome>',
        'position' => 'Posição {start}',
        'matches_count' => 'Correspondências: {count}',
        'matches_none' => 'Nenhuma correspondência.',
        'truncated' => 'Mostrando as primeiras {count} correspondências.',
        'replaced_title' => 'Resultado da substituição',
        'file_types' => 'TXT',

        'flags' => [
            'i' => 'Ignorar maiúsculas e minúsculas',
            'm' => 'Multilinha: ^ e $ em cada linha',
            's' => 'O ponto também casa a quebra de linha',
            'u' => 'Unicode: casar por ponto de código',
        ],

        'states' => [
            'pattern' => 'A expressão não é válida. O detalhe abaixo é a mensagem do motor do navegador.',
            'pattern_empty' => 'Escreva a expressão regular para testar.',
            'timeout' => 'A busca passou de {seconds} segundos e foi interrompida: nenhum resultado é mostrado. Restrinja a expressão para evitar o retrocesso.',
        ],
    ],

    'markdown-to-pdf' => [
        'name' => 'Markdown to PDF',
        'description' => 'Converta Markdown em documentos PDF estilizados com pré-visualização em tempo real, formatos de página, fontes e temas.',
        'keywords' => 'markdown, pdf, conversor, documento, exportar, gfm, imprimir',
        'intro' => 'Cole ou abra Markdown para visualizar o documento formatado em tempo real. Personalize tamanhos de página, fontes e temas, e então baixe seu PDF ou imprima com precisão vetorial. Tudo roda no seu navegador: nada do que colar aqui é enviado ao servidor.',

        'download_name' => 'documento',
        'file_types' => 'MD ou TXT',

        'controls' => [
            'format' => 'Formato',
            'orientation' => 'Orientação',
            'margin' => 'Margem',
            'font' => 'Fonte',
            'font_size' => 'Tamanho',
            'theme' => 'Tema',
        ],

        'formats' => [
            'a4' => 'A4',
            'letter' => 'Carta',
            'a5' => 'A5',
        ],

        'orientations' => [
            'portrait' => 'Retrato',
            'landscape' => 'Paisagem',
        ],

        'margins' => [
            'normal' => 'Normal (20mm)',
            'compact' => 'Compacta (10mm)',
            'wide' => 'Larga (30mm)',
        ],

        'fonts' => [
            'sans' => 'Sans-serif',
            'serif' => 'Serif',
            'mono' => 'Monospace',
            'fira-code' => 'Fira Code',
        ],

        'font_sizes' => [
            'sm' => 'Pequeno',
            'md' => 'Médio',
            'lg' => 'Grande',
        ],

        'themes' => [
            'modern' => 'Moderno',
            'editorial' => 'Editorial',
            'minimal' => 'Minimalista P&B',
            'academic' => 'Acadêmico',
        ],

        'actions' => [
            'download_pdf' => 'Baixar PDF',
            'print' => 'Imprimir / Salvar como PDF',
            'generating' => 'Gerando...',
        ],

        'stats' => [
            'words' => 'Palavras: {count}',
            'chars' => 'Caracteres: {count}',
        ],
    ],

    'base64-image' => [
        'name' => 'Base64 Image Encoder / Decoder',
        'description' => 'Converta imagens em Base64 Data URI (texto puro, tag img ou CSS) e decodifique Base64 de volta para imagem.',
        'intro' => 'Converta imagens em Base64 Data URI com suporte a tags img e CSS, ou decodifique sequências Base64 de volta em imagens com pré-visualização e download.',
        'keywords' => 'imagem, base64, data-uri, codificador, decodificador, converter, css, html',
        'upload_title' => 'Clique para selecionar ou arraste uma imagem',
        'upload_subtitle' => 'Suporta PNG, JPG, GIF, WebP, SVG, BMP, AVIF e outros formatos',
        'max_limit_badge' => 'Limite: {limit}',
        'pro_plan_badge' => 'Plano Pro (Até 25 MB)',
        'converting' => 'Convertendo imagem no servidor...',
        'converting_hint' => 'Aguarde o processamento do arquivo.',
        'new_image' => 'Nova Imagem',
        'original_size' => 'Tamanho original',
        'base64_size' => 'Tamanho Base64',
        'retention_info' => 'Histórico disponível por {hours}h',
        'retention_info_day' => 'Histórico disponível por {days} dia',
        'retention_info_days' => 'Histórico disponível por {days} dias',
        'direct_download_notice_title' => 'Arquivo grande convertido',
        'direct_download_notice_desc' => 'Para proteger o navegador, o download do arquivo .txt com a Data URI foi iniciado diretamente.',
        'copy' => 'Copiar',
        'copied' => 'Copiado!',
        'download_txt' => 'Baixar .txt',
        'download_json' => 'Baixar .json',
        'download_xml' => 'Baixar .xml',
        'download_image' => 'Baixar Imagem',
        'preview_img' => 'Visualizar',
        'size' => 'Tamanho',
        'example' => 'Exemplo',
        'clear' => 'Limpar',
        'decoder_input_label' => 'Cole a string Base64 ou Data URI:',
        'decoder_output_label' => 'Imagem Decodificada:',
        'decoder_placeholder' => 'data:image/png;base64,iVBORw0KGgo...',
        'decoder_empty_prompt' => 'Nenhuma imagem decodificada ainda.',
        'decoder_empty_hint' => 'Cole uma string Base64 válida à esquerda para visualizar e baixar.',
        'history_empty_title' => 'Nenhuma conversão recente',
        'history_empty_desc' => 'As imagens que você converter aparecerão aqui durante o período de retenção.',
        'active_badge' => 'Ativo',
        'expired_badge' => 'Expirado',
        'confirm_delete' => 'Tem certeza que deseja excluir esta conversão do histórico?',
        'tabs' => [
            'encoder' => 'Codificador (Imagem → Base64)',
            'decoder' => 'Decodificador (Base64 → Imagem)',
            'history' => 'Histórico',
        ],
        'formats' => [
            'data_uri' => 'Data URI / Texto Puro',
            'img_tag' => 'Tag <img> HTML',
            'css_bg' => 'CSS background-image',
            'json' => 'JSON',
            'xml' => 'XML',
        ],
        'errors' => [
            'title' => 'Erro na conversão',
            'invalid_image' => 'O arquivo enviado não é uma imagem válida.',
            'file_too_large' => 'O arquivo excede o limite máximo permitido de {limit}.',
            'conversion_failed' => 'Ocorreu uma falha ao processar a imagem. Tente novamente.',
            'invalid_base64' => 'A sequência Base64 fornecida é inválida ou está corrompida.',
            'file_expired' => 'O arquivo expirou e já foi removido do servidor.',
        ],
    ],
    'share-text' => [
        'name' => 'Compartilhar Texto',
        'description' => 'Compartilhe textos, notas e códigos com links curtos por ULID e compatibilidade total com Hastebin.',
        'intro' => 'Compartilhe notas, códigos e textos com links curtos por ULID. Visitantes anônimos contam com retenção de 72 horas e usuários cadastrados desfrutam de armazenamento permanente com compatibilidade total com a API do Hastebin.',
        'keywords' => 'hastebin, pastebin, compartilhar texto, snippet, codigo, notas, ulid, raw',
        'placeholder' => 'Cole ou digite seu texto aqui...',
        'actions' => [
            'save' => 'Salvar',
            'new' => 'Novo',
            'duplicate' => 'Duplicar & Editar',
            'raw' => 'Texto Puro (Raw)',
            'copy_link' => 'Copiar Link',
            'copied' => 'Link copiado!',
            'history' => 'Histórico',
        ],
        'badges' => [
            'anonymous' => 'Expira em 72h (anônimo)',
            'permanent' => 'Permanente (sua conta)',
            'draft' => 'Rascunho não salvo',
        ],
        'dialog' => [
            'title' => 'Texto Compartilhado com Sucesso',
            'description' => 'Seu texto foi salvo com identificador único e já está disponível para compartilhamento.',
            'url_label' => 'Link de compartilhamento:',
            'raw_url_label' => 'Link direto (Raw):',
            'close' => 'Fechar',
        ],
        'history' => [
            'title' => 'Seus Textos Compartilhados',
            'empty' => 'Nenhum texto compartilhado ainda.',
            'open' => 'Abrir',
            'delete' => 'Excluir',
            'confirm_delete' => 'Tem certeza que deseja excluir permanentemente este texto?',
        ],
        'languages' => [
            'auto' => 'Texto Puro',
            'json' => 'JSON',
            'sql' => 'SQL',
            'markdown' => 'Markdown',
        ],
        'errors' => [
            'empty_content' => 'O conteúdo não pode estar vazio.',
            'size_exceeded' => 'O texto excede o limite máximo permitido de :max MB.',
            'save_failed' => 'Ocorreu um erro ao salvar o texto. Tente novamente.',
            'not_found' => 'Texto não encontrado ou expirado.',
        ],
        'messages' => [
            'deleted' => 'Texto excluído com sucesso.',
        ],
    ],
];
