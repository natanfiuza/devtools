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

    /*
    | Superficie compartilhada entre tools. O seletor de cor e usado por mais
    | de uma ferramenta, entao seus textos sairam de `tools.<slug>.*`.
    */
    'shared' => [
        'color_picker' => [
            'color_input' => [
                'label' => 'Cor',
                'placeholder' => '#7c3aed, rgb(124 58 237), oklch(54% 0.24 293)…',
                'hint' => 'Aceita HEX, RGB, HSL, OKLCH, OKLab e nomes CSS. A cor é aplicada enquanto você digita, assim que o texto é válido.',
                'empty' => 'Digite uma cor para começar.',
                'syntax' => 'Não foi possível entender esse formato. Confira o código e tente de novo.',
                'range' => 'Um dos valores está fora do intervalo permitido.',
                'unknown_name' => 'Não existe uma cor CSS com esse nome.',
            ],

            'area' => [
                'label' => 'Saturação e brilho',
                'hint' => 'As setas ajustam 1 ponto; com Shift, 10 pontos.',
            ],

            'hue' => [
                'label' => 'Matiz',
            ],

            'alpha' => [
                'label' => 'Opacidade',
            ],

            'channels' => [
                'title' => 'Canais',
                'rgb' => 'RGB',
                'hsl' => 'HSL',
                'red' => 'Vermelho',
                'green' => 'Verde',
                'blue' => 'Azul',
                'hue' => 'Matiz',
                'saturation' => 'Saturação',
                'lightness' => 'Luminosidade',
            ],

            'formats' => [
                'title' => 'Formato da cor',
                'hex' => 'HEX',
                'hex8' => 'HEX8',
                'rgb' => 'RGB',
                'rgba' => 'RGBA',
                'hsl' => 'HSL',
                'hsla' => 'HSLA',
                'oklch' => 'OKLCH',
                'oklab' => 'OKLab',
                'name' => 'Nome CSS',
                'copy' => 'Copiar {format}',
                'copied' => 'Copiado: {value}',
                'name_hint' => 'O nome CSS é a cor mais próxima; pode variar um pouco do código.',
            ],
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
        'colors' => 'Cores',
    ],

    'html-color-picker' => [
        'name' => 'HTML Color Picker',
        'description' => 'Escolha uma cor, converta entre HEX, RGB, HSL e OKLCH e confira o contraste WCAG antes de usar.',
        'keywords' => 'cor, hex, rgb, hsl, oklch, contraste, wcag, conta-gotas, tons, tintas',
        'intro' => 'Escolha uma cor na tela ou digite um código, ajuste na área 2D e nos sliders e confira o contraste WCAG contra preto e branco. Tudo roda no seu navegador: nenhuma cor sai da página.',

        'preview' => [
            'alpha' => '{percent}% de opacidade',
            'eyedropper' => 'Conta-gotas da tela',
            'eyedropper_image' => 'Conta-gotas por imagem',
            'gamut' => 'Essa cor está fora do gamut sRGB. O valor digitado ({source}) foi reduzido em croma para {value}, mantendo luminosidade e matiz.',
        ],

        'image' => [
            'title' => 'Conta-gotas por imagem',
            'description' => 'A imagem é lida no seu navegador e nunca é enviada. Clique na imagem ou use as setas para escolher o pixel e confirme.',
            'choose' => 'Escolher uma imagem',
            'error' => 'Não foi possível ler essa imagem. Tente outra.',
            'instructions' => 'Clique ou use as setas para escolher o pixel; Enter aplica.',
            'apply' => 'Usar esta cor',
        ],

        'contrast' => [
            'title' => 'Contraste WCAG',
            'description' => 'A opacidade é composta sobre o fundo escolhido antes da conta.',
            'backdrop' => 'Fundo',
            'white' => 'Branco',
            'black' => 'Preto',
            'custom' => 'Personalizado',
            'black_text' => 'Texto preto ({ratio}:1 {verdict})',
            'white_text' => 'Texto branco ({ratio}:1 {verdict})',
            'pass_word' => 'passa',
            'fail_word' => 'não passa',
            'aa_normal' => 'AA',
            'aaa_normal' => 'AAA',
            'aa_large' => 'AA grande',
            'aaa_large' => 'AAA grande',
            'large_note' => 'Texto grande: 18,66 px em negrito ou 24 px normal.',
        ],

        'shades' => [
            'title' => 'Tons',
            'description' => 'Dez versões mais escuras, em OKLab, com a mesma opacidade.',
        ],

        'tints' => [
            'title' => 'Tintas',
            'description' => 'Dez versões mais claras, em OKLab, com a mesma opacidade.',
        ],

        'history' => [
            'title' => 'Histórico',
            'description' => 'As últimas cores que você aplicou, só neste navegador.',
            'clear' => 'Limpar',
            'empty' => 'Nenhuma cor aplicada ainda.',
        ],

        'favorites' => [
            'title' => 'Favoritos',
            'description' => 'Cores guardadas só neste navegador.',
            'add' => 'Favoritar cor atual',
            'remove' => 'Remover dos favoritos',
            'clear' => 'Limpar',
            'empty' => 'Nenhum favorito ainda.',
        ],

        'tabs' => [
            'contrast' => 'Contraste',
            'shades' => 'Tons',
            'library' => 'Biblioteca',
        ],
    ],

    'palette-generator' => [
        'name' => 'Color Palette Generator',
        'description' => 'Gere uma escala de 11 tons, veja harmonias e extraia as cores dominantes de uma imagem; exporte em CSS, SCSS, JSON e Tailwind.',
        'keywords' => 'paleta, escala de cores, tons, tintas, tailwind, variáveis css, scss, tokens de design, oklch, harmonias, cores dominantes, extração de imagem',
        'intro' => 'Escolha uma cor base e receba na hora uma escala de 11 tons em OKLCH, com contraste WCAG em cada tom e os valores convertidos da base. Veja as harmonias complementar, análoga, triádica, tetrádica e monocromática em OKLCH ou HSL, e extraia até 11 cores dominantes de uma imagem local ou de uma URL HTTPS. Copie um tom ou a escala inteira e baixe em CSS, SCSS, JSON, Tailwind v3 ou Tailwind v4. Tudo roda no seu navegador: nenhuma cor e nenhuma imagem sai da página.',

        'tabs' => [
            'scale' => 'Escala',
            'harmonies' => 'Harmonias',
            'extraction' => 'Extração',
        ],

        'harmonies' => [
            'title' => 'Harmonias',
            'description' => 'Cores que combinam com a base. Clique em uma para usá-la como nova base.',
            'kind_label' => 'Tipo de harmonia',
            'method_label' => 'Método',
            'method_oklch' => 'OKLCH',
            'method_hsl' => 'HSL',
            'kind_complementary' => 'Complementar',
            'kind_analogous' => 'Análoga',
            'kind_triadic' => 'Triádica',
            'kind_tetradic' => 'Tetrádica',
            'kind_monochromatic' => 'Monocromática',
            'base_badge' => 'Base',
            'clipped_badge' => 'Fora do sRGB',
            'achromatic_note' => 'A base é acromática: girar a matiz não cria cores novas, então a harmonia fica reduzida.',
            'color_label' => 'Cor {index} da harmonia',
            'copy' => 'Copiar',
            'use_as_base' => 'Usar como base',
            'copied' => 'Copiado: {value}',
        ],

        'extraction' => [
            'title' => 'Cores dominantes da imagem',
            'description' => 'Extraia as cores dominantes de uma imagem local ou de uma URL HTTPS. Nada é enviado ao servidor.',
            'file_label' => 'Imagem',
            'file_hint' => 'JPEG, PNG, WebP, GIF ou AVIF, até {size}.',
            'url_label' => 'URL da imagem',
            'url_placeholder' => 'https://exemplo.com/foto.jpg',
            'url_hint' => 'Precisa ser HTTPS e permitir CORS; se a origem bloquear, use o arquivo local.',
            'import_url' => 'Importar',
            'importing' => 'Importando…',
            'count_label' => 'Quantidade de cores',
            'count_option' => '{count} cores',
            'cancel' => 'Cancelar',
            'preview_alt' => 'Prévia da imagem importada',
            'status' => [
                'reading' => 'Baixando a imagem…',
                'decoding' => 'Decodificando…',
                'analyzing' => 'Extraindo as cores dominantes…',
            ],
            'results_title' => 'Cores dominantes · {count}',
            'percentage' => '{value}% dos pixels',
            'copy' => 'Copiar',
            'copied' => 'Copiado: {value}',
            'use_as_base' => 'Usar como base',
            'use_file' => 'Usar um arquivo local',
            'insufficient' => 'A imagem tem menos de {requested} cores distintas; foram encontradas {found}.',
            'algorithm_version' => 'Algoritmo v{version}',
            'source_file' => 'Arquivo: {name}',
            'source_url' => 'Imagem de {host}',
            'errors' => [
                'invalid_url' => 'A URL não é válida.',
                'not_https' => 'Somente URLs HTTPS são aceitas.',
                'cors' => 'A origem não permite a leitura direta (CORS). Use um arquivo local.',
                'network' => 'Não foi possível baixar a imagem.',
                'timeout' => 'A imagem demorou demais para responder. Tente de novo.',
                'too_large' => 'A imagem passa do limite de tamanho configurado.',
                'not_image' => 'O endereço não aponta para uma imagem aceita (JPEG, PNG, WebP, GIF ou AVIF).',
                'decode_failed' => 'Não foi possível decodificar a imagem.',
                'dimensions_too_large' => 'A imagem passa dos limites de dimensão configurados.',
                'too_many_pixels' => 'A imagem tem pixels demais para ser analisada.',
                'empty_image' => 'A imagem não tem pixels visíveis (tudo transparente).',
                'unavailable' => 'A extração não está disponível neste navegador.',
                'cancelled' => 'Importação cancelada.',
            ],
        ],

        'base' => [
            'title' => 'Cor base',
            'badge' => 'Base',
            'copy' => 'Copiar {format}',
            'copied' => 'Copiado: {value}',
            'nearest' => 'Tom mais próximo: {tone}',
            'not_500' => 'A base não é o tom 500. Ela foi mantida intacta; a escala continua a mesma.',
            'matches_500' => 'A base é o tom 500 da escala.',
            'clipped' => 'Croma reduzido para caber no sRGB.',
            'alpha_note' => 'O contraste considera o tom composto no fundo da mesma polaridade do texto.',
        ],

        'scale' => [
            'title' => 'Escala de 11 tons',
            'description' => 'Luminosidade fixa em OKLCH; o croma acompanha a cor base.',
            'copy_scale' => 'Copiar escala',
            'copied_scale' => 'Escala copiada: {count} tons',
            'tone_copy' => 'Copiar {tone}',
            'tone_copied' => 'Copiado: {value}',
            'contrast_black' => 'Texto preto {ratio}:1',
            'contrast_white' => 'Texto branco {ratio}:1',
            'pass' => 'passa {level}',
            'fail' => 'não passa {level}',
            'clipped_badge' => 'Croma limitado',
            'clipped_summary' => 'Tons com croma limitado: {tones}',
            'algorithm_version' => 'Algoritmo v{version}',
            'base_tone_badge' => 'Mais próximo',
        ],

        'export' => [
            'title' => 'Exportar',
            'description' => 'Copie ou baixe a escala pronta para o seu projeto.',
            'name_label' => 'Nome da paleta',
            'name_hint' => 'Usado nas variáveis: {slug}',
            'name_invalid' => 'O nome será normalizado para {slug}.',
            'copy' => 'Copiar',
            'copied' => '{name} copiado',
            'download' => 'Baixar',
            'tabs' => [
                'css' => 'CSS',
                'scss' => 'SCSS',
                'json' => 'JSON',
                'tailwind_v3' => 'Tailwind v3',
                'tailwind_v4' => 'Tailwind v4',
            ],
        ],

        'empty' => [
            'title' => 'Escolha uma cor base',
            'body' => 'Assim que houver uma cor válida, os 11 tons aparecem aqui.',
            'restore' => 'Restaurar exemplo',
        ],

        'state' => [
            'stale' => 'A cor digitada não é válida; a escala anterior continua na tela.',
        ],
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

    'badgeforge' => [
        'name' => 'BadgeForge',
        'description' => 'Crie badges SVG para os seus READMEs com prévia em tempo real e trechos prontos para Markdown, HTML e reStructuredText.',
        'keywords' => 'selo, badge, shields, svg, readme, markdown, ci, cobertura',
        'intro' => 'Monte o badge, veja a prévia e copie o trecho. A imagem é servida como SVG por este site; nada do que você digita aqui sai do navegador além da URL que você copia.',

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
            'default' => 'Padrão do estilo',
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
            'title' => 'Prévia',
            'dark' => 'Fundo escuro',
            'light' => 'Fundo claro',
            'empty' => 'Digite um rótulo e uma mensagem para ver o badge.',
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
            'logos_button' => 'Meus logos',
            'presets_button' => 'Presets',
            'login_cta' => 'Entre para enviar seus próprios logos e salvar presets.',
            'login_action' => 'Entrar',
            'upgrade_cta' => 'Seu plano permite {limit} logos personalizados. Faça upgrade para logos ilimitados.',
            'upgrade' => 'Melhorar plano',
            'limit_note' => 'Logos personalizados usados: {used} de {limit}.',
            'unlimited_note' => 'Logos personalizados usados: {used} (ilimitado).',
            'preset_limit_note' => 'Presets usados: {used} de {limit}.',
            'preset_unlimited_note' => 'Presets usados: {used} (ilimitados).',
            'logo_created' => 'Logo salvo.',
            'logo_updated' => 'Logo renomeado.',
            'logo_deleted' => 'Logo excluído.',
            'preset_created' => 'Preset salvo.',
            'preset_updated' => 'Preset renomeado.',
            'preset_deleted' => 'Preset excluído.',
            'throttled' => 'Ações demais. Aguarde um instante e tente novamente.',
            'badge_created' => 'Badge criada.',
            'badge_updated' => 'Badge renomeada.',
            'badge_credential_updated' => 'Credencial atualizada. A badge será gerada novamente.',
            'badge_deleted' => 'Badge excluída.',
            'logos_title' => 'Meus logos',
            'logos_description' => 'Arquivos SVG feitos apenas de caminhos. O arquivo não é armazenado; só os caminhos normalizados ficam.',
            'presets_title' => 'Presets',
            'presets_description' => 'Fotos do formulário do construtor. Aplique um para preencher o formulário de novo.',
            'empty_logos' => 'Você ainda não enviou nenhum logo.',
            'empty_presets' => 'Você ainda não salvou nenhum preset.',
            'upload' => 'Enviar SVG',
            'uploading' => 'Enviando...',
            'upload_hint' => 'SVG de até {size} KB.',
            'logo_file' => 'Arquivo SVG',
            'name' => 'Nome',
            'name_placeholder' => 'Meu logo',
            'preset_name_placeholder' => 'Meu preset',
            'save' => 'Salvar',
            'cancel' => 'Cancelar',
            'rename' => 'Renomear',
            'delete' => 'Excluir',
            'delete_title' => 'Excluir "{name}"?',
            'delete_description' => 'Esta ação não pode ser desfeita.',
            'preview_loading' => 'Carregando prévia...',
            'preview_error' => 'Não foi possível carregar a prévia.',
            'path_count' => '{count} caminhos',
            'preview' => 'Pré-visualizar',
            'save_preset' => 'Salvar como preset',
            'apply_preset' => 'Aplicar',
            'preset_orphaned' => 'O logo deste preset não existe mais. Ele será aplicado sem logo.',
            'open_in_builder' => 'Abrir no construtor',
            'manage_in_dashboard' => 'Gerenciar no painel',
        ],

        'modes' => [
            'static' => 'Estático',
            'provider' => 'Provedores',
            'custom' => 'Personalizado',
        ],
        'provider' => [
            'sections' => [
                'metric' => 'Métrica',
            ],
            'fields' => [
                'provider' => 'Provedor',
                'definition' => 'Métrica',
            ],
            'invalid_param' => 'Confira os parâmetros e tente de novo.',
            'example' => 'Exemplo: {path}',
            'cache_note' => 'O cache dos badges de provedor é controlado pelo servidor.',
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
                'npm_dm' => 'Downloads mensais do npm',
                'packagist_dt' => 'Downloads do Packagist',
                'packagist_v' => 'Versão do Packagist',
                'docker_pulls' => 'Pulls do Docker Hub',
                'codecov_coverage' => 'Cobertura do Codecov',
                'coveralls_coverage' => 'Cobertura do Coveralls',
                'laravel_v' => 'Versão do Laravel',
                'laravel_dt' => 'Downloads do Laravel',
                'php_v' => 'Versão do PHP',
                'python_v' => 'Versão do Python',
            ],
        ],
        'custom_providers' => [
            'title' => 'Providers customizados',
            'description' => 'Badges a partir de qualquer documento JSON público que você mapeia. A URL de origem fica no servidor; a imagem usa um identificador opaco.',
            'create_button' => 'Novo provider',
            'readonly_note' => 'Providers customizados estão disponíveis nos planos pagos. As badges existentes continuam funcionando e podem ser copiadas ou excluídas.',
            'upgrade' => 'Melhorar plano',
            'limit_reached' => 'Você atingiu o limite de {limit} providers customizados.',
            'empty' => 'Você ainda não tem providers customizados.',
            'disabled' => 'Desabilitado',
            'copy_svg' => 'Copiar SVG',
            'copy_png' => 'Copiar PNG',
            'delete_description' => 'A URL pública deixa de funcionar e a cópia em cache é descartada.',
            'dialog_create_title' => 'Criar provider customizado',
            'dialog_edit_title' => 'Editar provider customizado',
            'dialog_description' => 'Aponte para um documento JSON público e escolha os valores com JSON Pointer.',
            'template_immutable' => 'O template de origem é imutável: para trocar, exclua e crie de novo. Os slugs das métricas também não podem ser renomeados.',
            'add_metric' => 'Adicionar métrica',
            'remove_metric' => 'Remover métrica',
            'create_submit' => 'Criar provider',
            'update_submit' => 'Salvar alterações',
            'fields' => [
                'name' => 'Nome',
                'url_template' => 'Template de origem',
                'path_params' => 'Parâmetros do path',
                'example_path' => 'Path de exemplo',
                'metrics' => 'Métricas',
                'metric_slug' => 'Slug',
                'metric_label' => 'Rótulo',
                'pointer' => 'JSON Pointer',
                'formatter' => 'Formato',
            ],
            'hints' => [
                'url_template' => 'URL HTTPS com os marcadores que você declarar, por exemplo https://api.example.com/{\'{\'}owner{\'}\'}/{\'{\'}repo{\'}\'}.',
                'template_note' => 'Os marcadores do path usam {\'{\'}name{\'}\'}. O template é validado ao salvar.',
                'path_params' => 'Marcadores separados por vírgula, na ordem do path.',
                'example_path' => 'Um path real usado para validar o mapeamento ao salvar.',
                'pointer' => 'RFC 6901, por exemplo /stargazers_count ou /items/0/score.',
            ],
            'formatters' => [
                'text' => 'Texto',
                'number' => 'Número',
                'percent' => 'Porcentagem',
            ],
            'builder' => [
                'sections' => [
                    'metric' => 'Métrica',
                ],
                'fields' => [
                    'provider' => 'Provider',
                    'definition' => 'Métrica',
                ],
                'invalid_param' => 'Confira os parâmetros e tente de novo.',
                'example' => 'Exemplo: {path}',
                'cache_note' => 'O cache das badges de provider customizado é controlado pelo servidor.',
                'preview_invalid' => 'Preencha os parâmetros para ver o badge.',
            ],
        ],
        'personal' => [
            'create_button' => 'Badge pessoal',
            'title' => 'Badges pessoais',
            'description' => 'Badges publicadas com o seu nome de usuário, prontas para incorporar. As de provedor se atualizam sozinhas.',
            'dialog_title' => 'Criar badge pessoal',
            'dialog_description' => 'Salve o badge atual com o seu nome de usuário e compartilhe a URL.',
            'name' => 'Nome',
            'name_placeholder' => 'Meu badge',
            'metric_note' => 'Métrica: {metric}',
            'private' => 'Privada',
            'private_hint' => 'Exige um token de acesso para ler repositório ou pacote privado.',
            'credential' => 'Token de acesso pessoal',
            'credential_placeholder' => 'Nunca é mostrado de novo depois de salvar',
            'credential_username' => 'Usuário da conta',
            'credential_username_placeholder' => 'Usuário do Docker',
            'params_invalid' => 'Corrija os parâmetros antes de salvar.',
            'create' => 'Criar badge',
            'creating' => 'Criando...',
            'upgrade_cta' => 'Badges pessoais estão disponíveis em planos pagos. Faça upgrade para publicar badges com o seu nome de usuário.',
            'upgrade' => 'Fazer upgrade',
            'readonly_note' => 'Seu plano não inclui mais a criação de badges pessoais. As existentes continuam funcionando e podem ser copiadas ou excluídas.',
            'empty' => 'Você ainda não tem badges pessoais. Crie uma no construtor.',
            'copy_svg' => 'Copiar SVG',
            'copy_png' => 'Copiar PNG',
            'refresh' => 'Atualizar',
            'refreshing' => 'Atualizando...',
            'refresh_failed' => 'Não foi possível atualizar o badge. Tente mais tarde.',
            'change_credential' => 'Trocar token',
            'credential_set' => 'Token de acesso salvo',
            'credential_missing' => 'Token de acesso ausente',
            'credential_not_required' => 'Item público',
        ],
        'endpoint' => [
            'cta_title' => 'Badge de endpoint JSON',
            'cta_description' => 'Transforme uma URL JSON em badge. Cadastre o endpoint no painel da sua conta; a URL de origem fica no servidor.',
            'cta_action' => 'Gerenciar endpoints',
            'create_button' => 'Novo endpoint',
            'title' => 'Badges de endpoint JSON',
            'description' => 'Badges geradas a partir de uma URL JSON que você cadastra. A URL de origem fica no servidor e nunca é exposta; a imagem pública usa um identificador opaco.',
            'readonly_note' => 'Confirme seu e-mail para cadastrar endpoints. As badges existentes continuam funcionando.',
            'empty' => 'Você ainda não tem badges de endpoint JSON.',
            'dialog_title' => 'Cadastrar endpoint JSON',
            'dialog_description' => 'O endpoint precisa devolver o contrato JSON do shields via HTTPS.',
            'name' => 'Nome',
            'name_placeholder' => 'Deploys',
            'url' => 'URL de origem',
            'url_placeholder' => 'https://example.com/badge.json',
            'url_hint' => 'Somente HTTPS, IP público e sem redirecionamentos. A URL fica no servidor e nunca volta ao navegador.',
            'create' => 'Cadastrar endpoint',
            'creating' => 'Cadastrando...',
            'copy_svg' => 'Copiar SVG',
            'copy_png' => 'Copiar PNG',
            'delete_description' => 'A URL pública deixa de funcionar e a cópia em cache é descartada. A URL de origem não é apagada em nenhum outro lugar.',
        ],
        'social_note' => 'O estilo social sempre usa as próprias cores.',
    ],
];
