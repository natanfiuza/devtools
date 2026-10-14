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
            'empty' => 'Cole, digite ou solte um arquivo JSON para começar.',
            'parsing' => 'Analisando...',
            'unavailable' => 'Ferramenta indisponível.',
            'invalid' => 'JSON inválido na linha {line}, coluna {column}.',
            'invalid_no_position' => 'JSON inválido.',
            'stale_tree' => 'Árvore da última versão válida.',
            'too_large' => 'O texto tem {size} e passa do limite de {limit}. A análise está desligada.',
            'file_too_large' => 'O arquivo tem {size} e passa do limite de {limit}.',
            'file_type' => 'Formato não aceito. Envie um arquivo .json ou .txt.',
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
    ],

    'json-to-code' => [
        'name' => 'JSON para código',
        'description' => 'Transforme JSON em tipos TypeScript, DTOs PHP e modelos Python.',
        'keywords' => 'gerar tipos, interface, dto, pydantic',
    ],

    'sql-formatter' => [
        'name' => 'SQL Formatter',
        'description' => 'Formate consultas SQL nos dialetos MySQL, PostgreSQL, SQL Server e SQLite.',
        'keywords' => 'embelezar, indentar, consulta',
    ],

    'base64-url-hash' => [
        'name' => 'Base64, URL e Hash',
        'description' => 'Codifique e decodifique Base64 e URL, e gere hashes MD5, SHA-1 e SHA-256.',
        'keywords' => 'codificar, decodificar, resumo, digest',
    ],

    'jwt-decoder' => [
        'name' => 'JWT Decoder',
        'description' => 'Leia o cabeçalho e o payload de um JWT. A assinatura não é validada.',
        'keywords' => 'token, bearer, claims, autenticação',
    ],

    'timestamp-uuid' => [
        'name' => 'Timestamp e UUID',
        'description' => 'Converta timestamps Unix e datas, e gere valores UUID e ULID.',
        'keywords' => 'unix, epoch, data, guid',
    ],

    'regex-tester' => [
        'name' => 'Regex Tester',
        'description' => 'Teste expressões regulares com destaque ao vivo das correspondências e dos grupos.',
        'keywords' => 'padrão, correspondência, grupos',
    ],

];
