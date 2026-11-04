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
        'drop' => 'Largue o ficheiro aqui',

        'actions' => [
            'open' => 'Abrir ficheiro',
            'example' => 'Exemplo',
            'clear' => 'Limpar',
            'copy' => 'Copiar',
            'copied' => 'Copiado',
            'copy_manual' => 'Selecione o texto e copie',
            'download' => 'Descarregar',
        ],

        'states' => [
            'empty' => 'Cole, escreva ou largue um ficheiro para começar.',
            'parsing' => 'A processar...',
            'unavailable' => 'Ferramenta indisponível.',
            'invalid' => 'Entrada inválida na linha {line}, coluna {column}.',
            'invalid_no_position' => 'Entrada inválida.',
            'stale_output' => 'Saída da última entrada válida.',
            'too_large' => 'O texto tem {size} e passa do limite de {limit}: nada será processado.',
            'file_too_large' => 'O ficheiro tem {size} e passa do limite de {limit}.',
            'file_type' => 'Formato não aceite. Envie um ficheiro {formats}.',
            'file_unreadable' => 'Não foi possível ler o ficheiro.',
            'drop_hint' => 'Largue para abrir o ficheiro.',
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
        'badges' => 'Imagens',
    ],

    'json-viewer' => [
        'name' => 'JSON Viewer',
        'description' => 'Explore, formate e valide JSON em árvore, com pesquisa e cópia de qualquer caminho.',
        'keywords' => 'formatar, validar, árvore, minimizar, caminho',
        'intro' => 'Cole um JSON para ver a árvore colorida, formatar, minimizar e copiar o caminho de qualquer nó. Tudo corre no seu navegador: nada do que cola aqui é enviado para o servidor.',

        'format' => 'Formatar',
        'minify' => 'Minimizar',
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
            'placeholder' => 'Pesquisar chave ou valor',
            'label' => 'Pesquisar na árvore',
            'clear' => 'Limpar pesquisa',
            'results' => 'Resultados: {count}',
            'truncated' => 'A mostrar os primeiros {count} resultados.',
            'none' => 'Sem resultados.',
        ],

        'stats' => [
            'keys' => 'Chaves: {count}',
            'items' => 'Itens: {count}',
        ],

        'shortcuts' => [
            'format' => 'Formatar o documento',
            'search' => 'Pesquisar na árvore',
            'clear' => 'Limpar a pesquisa',
        ],
    ],

    'ai-sql-builder' => [
        'name' => 'AI SQL Builder',
        'description' => 'Descreva a consulta em linguagem natural e receba o SQL pronto para o seu dialeto.',
        'keywords' => 'linguagem natural, gerar consulta, base de dados',
        'intro' => 'Descreva a consulta, cole o schema e escolha a saída: SQL, Eloquent, Prisma e mais. Esta é a única ferramenta que sai do navegador: o schema e a descrição seguem para o fornecedor de IA e consomem a quota do plano. O SQL gerado nunca é executado aqui.',
    ],

    'json-to-code' => [
        'name' => 'JSON para código',
        'description' => 'Transforme JSON em tipos TypeScript, DTOs PHP e modelos Python.',
        'keywords' => 'gerar tipos, interface, dto, pydantic',
        'intro' => 'Cole um JSON e escolha o alvo: interfaces TypeScript, DTOs PHP com propriedades promovidas ou modelos Pydantic. A inferência corre no seu navegador: nada do que cola aqui é enviado para o servidor.',

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
            'root_object' => 'A raiz tem de ser um objeto JSON com pelo menos uma chave.',
        ],

        'stats' => [
            'types' => 'Tipos: {count}',
        ],
    ],

    'sql-formatter' => [
        'name' => 'SQL Formatter',
        'description' => 'Formate consultas SQL nos dialetos MySQL, PostgreSQL, SQL Server e SQLite.',
        'keywords' => 'embelezar, indentar, consulta',
        'intro' => 'Cole a consulta e escolha o dialeto: o SQL sai indentado do lado direito, e a caixa das palavras-chave é a sua escolha. A formatação corre no seu navegador: nada do que cola aqui é enviado para o servidor.',

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
        'description' => 'Codifique e descodifique Base64 e URL, e gere os hashes MD5, SHA-1, SHA-256 e SHA-512.',
        'keywords' => 'codificar, descodificar, resumo, digest',
        'intro' => 'Uma entrada, três leituras: o mesmo texto fica em Base64, em URL codificado ou em hash. Escolha o separador à direita e a direção à esquerda. A conta corre no seu navegador: nada do que cola aqui é enviado para o servidor.',

        'direction' => 'Direção',
        'file_types' => 'TXT',
        'row_copied' => '{label} copiado',

        'directions' => [
            'encode' => 'Codificar',
            'decode' => 'Descodificar',
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
            'invalid_url' => 'Isto não é um URL codificado válido: há uma sequência de escape quebrada ou bytes que não formam UTF-8.',
            'no_crypto' => 'Este navegador só expõe a Web Crypto em ligação segura (HTTPS), por isso as três linhas SHA ficam sem valor aqui. O MD5 continua — mas lembre-se: MD5 é checksum, e não assinatura.',
        ],
    ],

    'jwt-decoder' => [
        'name' => 'JWT Decoder',
        'description' => 'Leia o cabeçalho e o payload de um JWT. A assinatura não é validada.',
        'keywords' => 'token, bearer, claims, autenticação',
        'intro' => 'Cole o token (o prefixo Bearer pode vir junto) para ler o cabeçalho, o payload e as datas dos claims. A ferramenta descodifica e não valida a assinatura, e tudo corre no seu navegador: nada do que cola aqui é enviado para o servidor.',

        'header' => 'Cabeçalho',
        'payload' => 'Payload',
        'signature' => 'Assinatura',
        'signature_bytes' => '{count} bytes',
        'not_verified' => 'não verificada',
        'block_copied' => '{label} copiado',

        'warning' => 'Este descodificador não valida a assinatura: mostra o que está escrito no token, e nada aqui prova que o token é autêntico.',

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
        'intro' => 'Cole um instante — Unix em segundos ou milissegundos, uma data ou um UUID v7/ULID — para ver as seis leituras dele. O painel da direita também gera UUID v4, v7 e ULID. Tudo corre no seu navegador: nada do que cola aqui é enviado ao servidor.',

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
        'intro' => 'Escreva a expressão regular na barra de cima e o texto de teste no painel da esquerda: as correspondências saem destacadas, uma a uma, com os grupos numerados e nomeados, e o campo de substituição mostra o texto trocado. A pesquisa corre no seu navegador: nada do que cola aqui é enviado para o servidor.',

        'pattern' => 'Expressão regular',
        'replacement' => 'Substituir por',
        'replacement_hint' => 'Ex.: $1 ou $<nome>',
        'position' => 'Posição {start}',
        'matches_count' => 'Correspondências: {count}',
        'matches_none' => 'Nenhuma correspondência.',
        'truncated' => 'A mostrar as primeiras {count} correspondências.',
        'replaced_title' => 'Resultado da substituição',
        'file_types' => 'TXT',

        'flags' => [
            'i' => 'Ignorar maiúsculas e minúsculas',
            'm' => 'Multilinha: ^ e $ em cada linha',
            's' => 'O ponto também corresponde à quebra de linha',
            'u' => 'Unicode: corresponder por ponto de código',
        ],

        'states' => [
            'pattern' => 'A expressão não é válida. O detalhe abaixo é a mensagem do motor do navegador.',
            'pattern_empty' => 'Escreva a expressão regular para testar.',
            'timeout' => 'A pesquisa passou de {seconds} segundos e foi interrompida: não é mostrado nenhum resultado. Restrinja a expressão para evitar o retrocesso.',
        ],
    ],

    'markdown-to-pdf' => [
        'name' => 'Markdown to PDF',
        'description' => 'Converta Markdown em documentos PDF estilizados com pré-visualização em tempo real, formatos de página, fontes e temas.',
        'keywords' => 'markdown, pdf, conversor, documento, exportar, gfm, imprimir',
        'intro' => 'Cole ou abra Markdown para pré-visualizar o documento formatado em tempo real. Personalize formatos de página, fontes e temas, e descarregue o seu PDF ou imprima com precisão vetorial. Tudo é processado no seu navegador: nada do que colar aqui é enviado para o servidor.',

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
            'academic' => 'Académico',
        ],

        'actions' => [
            'download_pdf' => 'Descarregar PDF',
            'print' => 'Imprimir / Guardar como PDF',
            'generating' => 'A gerar...',
        ],

        'stats' => [
            'words' => 'Palavras: {count}',
            'chars' => 'Caracteres: {count}',
        ],
    ],

    'base64-image' => [
        'name' => 'Base64 Image Encoder / Decoder',
        'description' => 'Converta imagens em Base64 Data URI (texto simples, tag img ou CSS) e descodifique Base64 de volta para imagem.',
        'intro' => 'Converta imagens em Base64 Data URI com suporte a tags img e CSS, ou descodifique sequências Base64 de volta em imagens com pré-visualização e transferência.',
        'keywords' => 'imagem, base64, data-uri, codificador, descodificador, converter, css, html',
        'upload_title' => 'Clique para selecionar ou arraste uma imagem',
        'upload_subtitle' => 'Suporta PNG, JPG, GIF, WebP, SVG, BMP, AVIF e outros formatos',
        'max_limit_badge' => 'Limite: {limit}',
        'pro_plan_badge' => 'Plano Pro (Até 25 MB)',
        'converting' => 'A converter imagem no servidor...',
        'converting_hint' => 'Aguarde pelo processamento do ficheiro.',
        'new_image' => 'Nova Imagem',
        'original_size' => 'Tamanho original',
        'base64_size' => 'Tamanho Base64',
        'retention_info' => 'Histórico disponível por {hours}h',
        'retention_info_day' => 'Histórico disponível por {days} dia',
        'retention_info_days' => 'Histórico disponível por {days} dias',
        'direct_download_notice_title' => 'Ficheiro grande convertido',
        'direct_download_notice_desc' => 'Para proteger o navegador, a transferência do ficheiro .txt com o Data URI foi iniciada diretamente.',
        'copy' => 'Copiar',
        'copied' => 'Copiado!',
        'download_txt' => 'Descarregar .txt',
        'download_json' => 'Descarregar .json',
        'download_xml' => 'Descarregar .xml',
        'download_image' => 'Descarregar Imagem',
        'preview_img' => 'Visualizar',
        'size' => 'Tamanho',
        'example' => 'Exemplo',
        'clear' => 'Limpar',
        'decoder_input_label' => 'Cole a string Base64 ou Data URI:',
        'decoder_output_label' => 'Imagem Descodificada:',
        'decoder_placeholder' => 'data:image/png;base64,iVBORw0KGgo...',
        'decoder_empty_prompt' => 'Nenhuma imagem descodificada ainda.',
        'decoder_empty_hint' => 'Cole uma string Base64 válida à esquerda para visualizar e descarregar.',
        'history_empty_title' => 'Nenhuma conversão recente',
        'history_empty_desc' => 'As imagens que converter aparecerão aqui durante o período de retenção.',
        'active_badge' => 'Ativo',
        'expired_badge' => 'Expirado',
        'confirm_delete' => 'Tem a certeza de que deseja eliminar esta conversão do histórico?',
        'tabs' => [
            'encoder' => 'Codificador (Imagem → Base64)',
            'decoder' => 'Descodificador (Base64 → Imagem)',
            'history' => 'Histórico',
        ],
        'formats' => [
            'data_uri' => 'Data URI / Texto Simples',
            'img_tag' => 'Tag <img> HTML',
            'css_bg' => 'CSS background-image',
            'json' => 'JSON',
            'xml' => 'XML',
        ],
        'errors' => [
            'title' => 'Erro na conversão',
            'invalid_image' => 'O ficheiro enviado não é uma imagem válida.',
            'file_too_large' => 'O ficheiro excede o limite máximo permitido de {limit}.',
            'conversion_failed' => 'Ocorreu uma falha ao processar a imagem. Tente novamente.',
            'invalid_base64' => 'A sequência Base64 fornecida é inválida ou está corrompida.',
            'file_expired' => 'O ficheiro expirou e já foi removido do servidor.',
        ],
    ],
    'share-text' => [
        'name' => 'Partilhar Texto',
        'description' => 'Partilhe textos, notas e código com ligações curtas por ULID e compatibilidade total com Hastebin.',
        'intro' => 'Partilhe notas, código e textos com ligações curtas por ULID. Visitantes anónimos contam com retenção de 72 horas e utilizadores registados desfrutam de armazenamento permanente com compatibilidade total com a API do Hastebin.',
        'keywords' => 'hastebin, pastebin, partilhar texto, snippet, codigo, notas, ulid, raw',
        'placeholder' => 'Cole ou digite o seu texto aqui...',
        'actions' => [
            'save' => 'Guardar',
            'new' => 'Novo',
            'duplicate' => 'Duplicar & Editar',
            'raw' => 'Texto Simples (Raw)',
            'copy_link' => 'Copiar Ligação',
            'copied' => 'Ligação copiada!',
            'history' => 'Histórico',
        ],
        'badges' => [
            'anonymous' => 'Expira em 72h (anónimo)',
            'permanent' => 'Permanente (sua conta)',
            'draft' => 'Rascunho não guardado',
        ],
        'dialog' => [
            'title' => 'Texto Partilhado com Sucesso',
            'description' => 'O seu texto foi guardado com identificador único e está disponível para partilha.',
            'url_label' => 'Ligação de partilha:',
            'raw_url_label' => 'Ligação direta (Raw):',
            'close' => 'Fechar',
        ],
        'history' => [
            'title' => 'Os Seus Textos Partilhados',
            'empty' => 'Nenhum texto partilhado ainda.',
            'open' => 'Abrir',
            'delete' => 'Eliminar',
            'confirm_delete' => 'Tem a certeza de que deseja eliminar permanentemente este texto?',
        ],
        'languages' => [
            'auto' => 'Texto Simples',
            'json' => 'JSON',
            'sql' => 'SQL',
            'markdown' => 'Markdown',
        ],
        'errors' => [
            'empty_content' => 'O conteúdo não pode estar vazio.',
            'size_exceeded' => 'O texto excede o limite máximo permitido de :max MB.',
            'save_failed' => 'Ocorreu um erro ao guardar o texto. Tente novamente.',
            'not_found' => 'Texto não encontrado ou expirado.',
        ],
        'messages' => [
            'deleted' => 'Texto eliminado com sucesso.',
        ],
    ],

    'badgeforge' => [
        'name' => 'BadgeForge',
        'description' => 'Crie badges SVG para os seus READMEs com pré-visualização em tempo real e excertos prontos para Markdown, HTML e reStructuredText.',
        'keywords' => 'selo, badge, shields, svg, readme, markdown, ci, cobertura',
        'intro' => 'Monte o badge, veja a pré-visualização e copie o excerto. A imagem é servida como SVG por este site; nada do que escreve aqui sai do navegador além da URL que copia.',

        'fields' => [
            'label' => 'Rótulo',
            'message' => 'Mensagem',
            'style' => 'Estilo',
            'color' => 'Cor',
            'label_color' => 'Cor do rótulo',
            'logo' => 'Logo',
            'logo_color' => 'Cor do logo',
            'logo_size' => 'Tamanho do logo',
            'logo_position' => 'Posição do logo',
            'link' => 'Link',
            'cache_seconds' => 'Cache (segundos)',
            'font' => 'Fonte',
            'format' => 'Formato',
        ],

        'placeholders' => [
            'label' => 'build',
            'message' => 'passing',
            'color' => '#9f9f9f ou green',
            'label_color' => '#555555',
            'logo_color' => '#ffffff',
            'link' => 'https://exemplo.com',
        ],

        'positions' => [
            'left' => 'Esquerda',
            'right' => 'Direita',
        ],

        'logo_none' => 'Sem logo',

        'fonts' => [
            'default' => 'Predefinida do estilo',
        ],

        'formats' => [
            'svg' => 'SVG',
            'png' => 'PNG',
        ],

        'sections' => [
            'content' => 'Conteúdo',
            'colors' => 'Cores',
            'logo' => 'Logo',
            'advanced' => 'Avançado',
        ],

        'preview' => [
            'title' => 'Pré-visualização',
            'dark' => 'Fundo escuro',
            'light' => 'Fundo claro',
            'empty' => 'Escreva um rótulo e uma mensagem para ver o badge.',
            'dimensions' => '{width} × {height} px',
        ],

        'snippets' => [
            'title' => 'Incorporar',
            'url' => 'URL',
            'markdown' => 'Markdown',
            'html' => 'HTML',
            'rst' => 'reStructuredText',
        ],

        'actions' => [
            'sample' => 'Exemplo',
            'reset' => 'Limpar',
        ],

        'management' => [
            'logos_button' => 'Os meus logos',
            'presets_button' => 'Predefinições',
            'login_cta' => 'Inicie sessão para enviar os seus próprios logos e guardar predefinições.',
            'login_action' => 'Iniciar sessão',
            'upgrade_cta' => 'O seu plano permite {limit} logos personalizados. Mude de plano para logos ilimitados.',
            'upgrade' => 'Melhorar plano',
            'limit_note' => 'Logos personalizados usados: {used} de {limit}.',
            'unlimited_note' => 'Logos personalizados usados: {used} (ilimitado).',
            'preset_limit_note' => 'Presets utilizados: {used} de {limit}.',
            'preset_unlimited_note' => 'Presets utilizados: {used} (ilimitados).',
            'logo_created' => 'Logo guardado.',
            'logo_updated' => 'Logo renomeado.',
            'logo_deleted' => 'Logo eliminado.',
            'preset_created' => 'Predefinição guardada.',
            'preset_updated' => 'Predefinição renomeada.',
            'preset_deleted' => 'Predefinição eliminada.',
            'throttled' => 'Demasiadas ações. Aguarde um instante e tente novamente.',
            'badge_created' => 'Badge criada.',
            'badge_updated' => 'Badge renomeada.',
            'badge_credential_updated' => 'Credencial atualizada. A badge será gerada novamente.',
            'badge_deleted' => 'Badge eliminada.',
            'logos_title' => 'Os meus logos',
            'logos_description' => 'Ficheiros SVG constituídos apenas por caminhos. O ficheiro não é guardado; apenas os caminhos normalizados ficam.',
            'presets_title' => 'Predefinições',
            'presets_description' => 'Cópias do formulário do construtor. Aplique uma para preencher o formulário de novo.',
            'empty_logos' => 'Ainda não enviou nenhum logo.',
            'empty_presets' => 'Ainda não guardou nenhuma predefinição.',
            'upload' => 'Enviar SVG',
            'uploading' => 'A enviar...',
            'upload_hint' => 'SVG até {size} KB.',
            'logo_file' => 'Ficheiro SVG',
            'name' => 'Nome',
            'name_placeholder' => 'O meu logo',
            'preset_name_placeholder' => 'A minha predefinição',
            'save' => 'Guardar',
            'cancel' => 'Cancelar',
            'rename' => 'Renomear',
            'delete' => 'Eliminar',
            'delete_title' => 'Eliminar "{name}"?',
            'delete_description' => 'Esta ação não pode ser desfeita.',
            'preview_loading' => 'A carregar pré-visualização...',
            'preview_error' => 'Não foi possível carregar a pré-visualização.',
            'path_count' => '{count} caminhos',
            'preview' => 'Pré-visualizar',
            'save_preset' => 'Guardar como predefinição',
            'apply_preset' => 'Aplicar',
            'preset_orphaned' => 'O logo desta predefinição já não existe. Será aplicada sem logo.',
            'open_in_builder' => 'Abrir no construtor',
            'manage_in_dashboard' => 'Gerir no painel',
        ],

        'modes' => [
            'static' => 'Estático',
            'provider' => 'Fornecedores',
        ],
        'provider' => [
            'sections' => [
                'metric' => 'Métrica',
            ],
            'fields' => [
                'provider' => 'Fornecedor',
                'definition' => 'Métrica',
            ],
            'invalid_param' => 'Verifique os parâmetros e tente novamente.',
            'example' => 'Exemplo: {path}',
            'cache_note' => 'A cache dos badges de fornecedor é controlada pelo servidor.',
            'preview_invalid' => 'Preencha os parâmetros para ver o badge.',
            'params' => [
                'owner' => 'Proprietário',
                'repo' => 'Repositório',
                'package' => 'Pacote',
                'vendor' => 'Fornecedor',
                'namespace' => 'Namespace',
                'repository' => 'Repositório',
            ],
            'metrics' => [
                'github_stars' => 'Estrelas do GitHub',
                'github_forks' => 'Forks do GitHub',
                'github_issues' => 'Issues abertas do GitHub',
                'github_license' => 'Licença do GitHub',
                'npm_v' => 'Versão do npm',
                'npm_dm' => 'Transferências mensais do npm',
                'packagist_dt' => 'Transferências do Packagist',
                'packagist_v' => 'Versão do Packagist',
                'docker_pulls' => 'Pulls do Docker Hub',
            ],
        ],
        'personal' => [
            'create_button' => 'Badge pessoal',
            'title' => 'Badges pessoais',
            'description' => 'Badges publicados com o seu nome de utilizador, prontos a incorporar. Os de fornecedor atualizam-se sozinhos.',
            'dialog_title' => 'Criar badge pessoal',
            'dialog_description' => 'Guarde o badge atual com o seu nome de utilizador e partilhe o URL.',
            'name' => 'Nome',
            'name_placeholder' => 'O meu badge',
            'metric_note' => 'Métrica: {metric}',
            'private' => 'Privado',
            'private_hint' => 'Exige um token de acesso para ler repositório ou pacote privado.',
            'credential' => 'Token de acesso pessoal',
            'credential_placeholder' => 'Nunca é mostrado novamente depois de guardar',
            'credential_username' => 'Utilizador da conta',
            'credential_username_placeholder' => 'Utilizador do Docker',
            'params_invalid' => 'Corrija os parâmetros antes de guardar.',
            'create' => 'Criar badge',
            'creating' => 'A criar...',
            'upgrade_cta' => 'Os badges pessoais estão disponíveis em planos pagos. Melhore o plano para publicar badges com o seu nome de utilizador.',
            'upgrade' => 'Melhorar plano',
            'readonly_note' => 'O seu plano já não inclui a criação de badges pessoais. Os existentes continuam a funcionar e podem ser copiados ou eliminados.',
            'empty' => 'Ainda não tem badges pessoais. Crie um no construtor.',
            'copy_svg' => 'Copiar SVG',
            'copy_png' => 'Copiar PNG',
            'refresh' => 'Atualizar',
            'refreshing' => 'A atualizar...',
            'refresh_failed' => 'Não foi possível atualizar o badge. Tente mais tarde.',
            'change_credential' => 'Trocar token',
            'credential_set' => 'Token de acesso guardado',
            'credential_missing' => 'Token de acesso em falta',
            'credential_not_required' => 'Item público',
        ],
        'endpoint' => [
            'cta_title' => 'Badge de endpoint JSON',
            'cta_description' => 'Transforme um URL JSON em badge. Registe o endpoint no painel da sua conta; o URL de origem fica no servidor.',
            'cta_action' => 'Gerir endpoints',
            'create_button' => 'Novo endpoint',
            'title' => 'Badges de endpoint JSON',
            'description' => 'Badges geradas a partir de um URL JSON que regista. O URL de origem fica no servidor e nunca é exposto; a imagem pública usa um identificador opaco.',
            'readonly_note' => 'Confirme o seu e-mail para registar endpoints. As badges existentes continuam a funcionar.',
            'empty' => 'Ainda não tem badges de endpoint JSON.',
            'dialog_title' => 'Registar endpoint JSON',
            'dialog_description' => 'O endpoint tem de devolver o contrato JSON do shields via HTTPS.',
            'name' => 'Nome',
            'name_placeholder' => 'Deploys',
            'url' => 'URL de origem',
            'url_placeholder' => 'https://example.com/badge.json',
            'url_hint' => 'Apenas HTTPS, IP público e sem redirecionamentos. O URL fica no servidor e nunca volta ao navegador.',
            'create' => 'Registar endpoint',
            'creating' => 'A registar...',
            'copy_svg' => 'Copiar SVG',
            'copy_png' => 'Copiar PNG',
            'delete_description' => 'O URL público deixa de funcionar e a cópia em cache é descartada. O URL de origem não é apagado em nenhum outro lugar.',
        ],
        'social_note' => 'O estilo social usa sempre as suas próprias cores.',
    ],
];
