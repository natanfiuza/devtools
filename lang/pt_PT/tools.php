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

    /*
    | Superficie partilhada entre tools. O seletor de cor e usado por mais de
    | uma ferramenta, por isso os seus textos vivem aqui.
    */
    'shared' => [
        'color_picker' => [
            'color_input' => [
                'label' => 'Cor',
                'placeholder' => '#7c3aed, rgb(124 58 237), oklch(54% 0.24 293)…',
                'hint' => 'Aceita HEX, RGB, HSL, OKLCH, OKLab e nomes CSS. A cor é aplicada enquanto escreve, assim que o texto é válido.',
                'empty' => 'Escreva uma cor para começar.',
                'syntax' => 'Não foi possível compreender esse formato. Verifique o código e tente novamente.',
                'range' => 'Um dos valores está fora do intervalo permitido.',
                'unknown_name' => 'Não existe nenhuma cor CSS com esse nome.',
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

            'preview' => [
                'eyedropper' => 'Conta-gotas do ecrã',
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
                'name_hint' => 'O nome CSS é a cor mais próxima; pode diferir ligeiramente do código.',
            ],
        ],

        'library' => [
            'history' => [
                'title' => 'Histórico',
                'description' => [
                    'account' => 'As cores que aplicou mais recentemente, guardadas na sua conta e partilhadas com o gerador.',
                    'local' => 'As últimas cores que aplicou, apenas neste navegador. Inicie sessão para as guardar na conta.',
                ],
                'clear' => 'Limpar',
                'empty' => 'Ainda não aplicou nenhuma cor.',
            ],

            'favorites' => [
                'title' => 'Favoritos',
                'description' => [
                    'account' => 'Cores guardadas na sua conta, partilhadas entre o seletor de cor e o gerador de paletas.',
                    'local' => 'Cores guardadas apenas neste navegador.',
                ],
                'add' => 'Adicionar a favoritos',
                'remove' => 'Remover dos favoritos',
                'clear' => 'Limpar',
                'empty' => 'Ainda não há favoritos.',
            ],

            'import' => [
                'button' => 'Importar histórico do navegador ({count})',
                'title' => 'Importar histórico do navegador',
                'description' => 'Enviar {count} cor(es) guardadas neste navegador para a sua conta. A lista local é limpa após a importação e os duplicados são ignorados.',
                'confirm' => 'Importar',
                'cancel' => 'Cancelar',
                'busy' => 'A importar…',
                'success' => 'Importadas {count} cor(es) para a sua conta.',
                'error' => 'A importação falhou. O histórico local foi mantido; tente novamente.',
            ],

            'load_more' => 'Carregar mais',
        ],

        'token_import' => [
            'button' => 'Importar tokens',
            'applied' => 'Tokens aplicados.',
            'title' => 'Importar tokens de design',
            'description' => 'Leia um JSON nativo da ferramenta, W3C DTCG (Tokens Studio ou Figma Variables) ou Style Dictionary. O ficheiro é lido no navegador e nunca é enviado.',
            'choose' => 'Escolher um ficheiro JSON',
            'hint' => 'Até {size}. Também pode largar o ficheiro nesta área.',
            'reading' => 'A ler o ficheiro…',

            'file_errors' => [
                'file_too_large' => 'O ficheiro passa do limite de {limit}.',
                'file_type' => 'Escolha um ficheiro .json.',
                'file_unreadable' => 'Não foi possível ler o ficheiro. Tente outro.',
            ],

            'diagnostics' => [
                'title' => 'Diagnóstico',
                'errors' => 'Erros',
                'warnings' => 'Avisos',
                'hidden' => 'Mais {count} diagnóstico(s) não apresentados.',
                'path' => 'em {path}',
            ],

            'preview' => [
                'title' => 'Prévia',
                'format' => 'Formato',
                'kind' => 'Tipo',
                'base' => 'Cor base',
                'colors_label' => 'Cores',
                'colors' => '{count} cores',
                'roles' => 'Papéis semânticos incluídos',
                'no_roles' => 'Sem papéis semânticos completos',
                'name' => 'Nome no ficheiro: {name}',
                'filename' => 'Ficheiro: {name}',
                'apply' => 'Aplicar',
                'cancel' => 'Cancelar',
                'replace' => 'Escolher outro ficheiro',
                'apply_hint' => 'A paleta atual só muda ao aplicar.',
            ],

            'formats' => [
                'native' => 'JSON da ferramenta',
                'dtcg' => 'W3C DTCG',
                'style-dictionary' => 'Style Dictionary',
            ],

            'kinds' => [
                'scale' => 'Escala de 11 tons',
                'extracted' => 'Paleta extraída',
            ],

            'codes' => [
                'empty_file' => 'O ficheiro está vazio.',
                'too_large' => 'O ficheiro passa do limite de {max}.',
                'invalid_json' => 'O JSON não é válido (linha {line}, coluna {column}).',
                'root_not_object' => 'A raiz do ficheiro tem de ser um objeto JSON.',
                'unsupported_format' => 'Não foi encontrado nenhum token de cor reconhecível.',
                'ambiguous_format' => 'O ficheiro mistura mais de um estilo de token.',
                'unsupported_format_version' => 'A versão do formato ({found}) não é compatível; esta ferramenta lê a versão {expected}.',
                'too_deep' => 'A estrutura passa da profundidade máxima de {max}.',
                'too_many_tokens' => 'O ficheiro tem mais de {max} tokens.',
                'forbidden_key' => 'A chave “{key}” não é permitida.',
                'mixed_token_styles' => 'Tokens de estilos diferentes aparecem no mesmo grupo.',
                'invalid_structure' => 'A estrutura da paleta não é reconhecível.',
                'no_colors' => 'O ficheiro não tem cores para importar.',
                'ambiguous_palette' => 'Há mais de uma paleta possível; deixe apenas uma para importar.',
                'unsupported_color_count' => 'O grupo tem {count} cores; use 5, 7, 9 ou 11.',
                'missing_tones' => 'Faltam tons na escala: {missing}.',
                'invalid_color' => 'Um valor de cor não é um HEX válido.',
                'unknown_reference' => 'A referência “{reference}” não existe no ficheiro.',
                'reference_not_color' => 'A referência “{reference}” não aponta para uma cor.',
                'reference_cycle' => 'As referências formam um ciclo.',
                'reference_depth' => 'As referências passam da profundidade máxima de {max}.',
                'algorithm_version_mismatch' => 'O ficheiro foi gerado com o algoritmo v{found}; as cores serão mantidas como estão.',
                'non_color_ignored' => 'Tokens que não são cor foram ignorados.',
                'semantic_incomplete' => 'O bloco de papéis semânticos está incompleto; os papéis atuais serão mantidos.',
                'extra_colors_ignored' => 'Cores além da paleta foram ignoradas.',
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
        'description' => 'Escolha uma cor, converta entre HEX, RGB, HSL e OKLCH e confira o contraste WCAG antes de a usar.',
        'keywords' => 'cor, hex, rgb, hsl, oklch, contraste, wcag, conta-gotas, tons, tintas',
        'intro' => 'Escolha uma cor no ecrã ou escreva um código, ajuste na área 2D e nos controlos e confira o contraste WCAG contra preto e branco. Tudo corre no seu navegador: nenhuma cor sai da página.',

        'preview' => [
            'alpha' => '{percent}% de opacidade',
            'eyedropper' => 'Conta-gotas do ecrã',
            'eyedropper_image' => 'Conta-gotas por imagem',
            'gamut' => 'Esta cor está fora do gamut sRGB. O valor que escreveu ({source}) foi reduzido em croma para {value}, mantendo a luminosidade e o matiz.',
        ],

        'image' => [
            'title' => 'Conta-gotas por imagem',
            'description' => 'A imagem é lida no seu navegador e nunca é enviada. Clique na imagem ou use as setas para escolher o píxel e confirme.',
            'choose' => 'Escolher uma imagem',
            'error' => 'Não foi possível ler essa imagem. Tente outra.',
            'instructions' => 'Clique ou use as setas para escolher o píxel; Enter aplica.',
            'apply' => 'Usar esta cor',
        ],

        'contrast' => [
            'title' => 'Contraste WCAG',
            'description' => 'A opacidade é composta sobre o fundo escolhido antes do cálculo.',
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
            'large_note' => 'Texto grande: 18,66 px a negrito ou 24 px normal.',
        ],

        'shades' => [
            'title' => 'Tons',
            'description' => 'Dez versões mais escuras, em OKLab, com a mesma opacidade.',
        ],

        'tints' => [
            'title' => 'Tintas',
            'description' => 'Dez versões mais claras, em OKLab, com a mesma opacidade.',
        ],

        'tabs' => [
            'contrast' => 'Contraste',
            'shades' => 'Tons',
            'library' => 'Biblioteca',
        ],
    ],

    'palette-generator' => [
        'name' => 'Color Palette Generator',
        'description' => 'Gere uma escala de 11 tons, veja harmonias e extraia as cores dominantes de uma imagem; exporte para CSS, SCSS, JSON e Tailwind.',
        'keywords' => 'paleta, escala de cores, tons, tintas, tailwind, variáveis css, scss, tokens de design, oklch, harmonias, cores dominantes, extração de imagem',
        'intro' => 'Escolha uma cor base e obtenha de imediato uma escala de 11 tons em OKLCH, com contraste WCAG em cada tom e os valores convertidos da base. Veja as harmonias complementar, análoga, triádica, tetrádica e monocromática em OKLCH ou HSL, e extraia até 11 cores dominantes de uma imagem local ou de um URL HTTPS. Copie um tom ou a escala completa e descarregue-a em CSS, SCSS, JSON, Tailwind v3 ou Tailwind v4. Tudo corre no seu navegador: nenhuma cor e nenhuma imagem sai da página.',

        'tabs' => [
            'scale' => 'Escala',
            'harmonies' => 'Harmonias',
            'extraction' => 'Extração',
            'library' => 'Biblioteca',
        ],

        'harmonies' => [
            'title' => 'Harmonias',
            'description' => 'Cores que combinam com a base. Clique numa para a usar como nova base.',
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
            'achromatic_note' => 'A base é acromática: rodar a matiz não cria cores novas, por isso a harmonia fica reduzida.',
            'color_label' => 'Cor {index} da harmonia',
            'copy' => 'Copiar',
            'use_as_base' => 'Usar como base',
            'copied' => 'Copiado: {value}',
        ],

        'extraction' => [
            'title' => 'Cores dominantes da imagem',
            'description' => 'Extraia as cores dominantes de uma imagem local ou de um URL HTTPS. Nada é enviado para o servidor.',
            'file_label' => 'Imagem',
            'file_hint' => 'JPEG, PNG, WebP, GIF ou AVIF, até {size}.',
            'url_label' => 'URL da imagem',
            'url_placeholder' => 'https://exemplo.com/foto.jpg',
            'url_hint' => 'Tem de ser HTTPS e permitir CORS; se a origem bloquear, use o ficheiro local.',
            'import_url' => 'Importar',
            'importing' => 'A importar…',
            'count_label' => 'Quantidade de cores',
            'count_option' => '{count} cores',
            'cancel' => 'Cancelar',
            'preview_alt' => 'Pré-visualização da imagem importada',
            'status' => [
                'reading' => 'A descarregar a imagem…',
                'decoding' => 'A descodificar…',
                'analyzing' => 'A extrair as cores dominantes…',
            ],
            'results_title' => 'Cores dominantes · {count}',
            'percentage' => '{value}% dos píxeis',
            'percentage_unknown' => 'peso não guardado',
            'copy' => 'Copiar',
            'copied' => 'Copiado: {value}',
            'use_as_base' => 'Usar como base',
            'use_file' => 'Usar um ficheiro local',
            'insufficient' => 'A imagem tem menos de {requested} cores distintas; foram encontradas {found}.',
            'algorithm_version' => 'Algoritmo v{version}',
            'source_unknown' => 'origem não registada',
            'source_file' => 'Ficheiro: {name}',
            'source_url' => 'Imagem de {host}',
            'errors' => [
                'invalid_url' => 'O URL não é válido.',
                'not_https' => 'Só são aceites URLs HTTPS.',
                'cors' => 'A origem não permite a leitura direta (CORS). Use um ficheiro local.',
                'network' => 'Não foi possível descarregar a imagem.',
                'timeout' => 'A imagem demorou demasiado a responder. Tente de novo.',
                'too_large' => 'A imagem excede o limite de tamanho configurado.',
                'not_image' => 'O endereço não aponta para uma imagem aceite (JPEG, PNG, WebP, GIF ou AVIF).',
                'decode_failed' => 'Não foi possível descodificar a imagem.',
                'dimensions_too_large' => 'A imagem excede os limites de dimensão configurados.',
                'too_many_pixels' => 'A imagem tem demasiados píxeis para ser analisada.',
                'empty_image' => 'A imagem não tem píxeis visíveis (tudo transparente).',
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
            'not_500' => 'A base não é o tom 500. Foi mantida intacta; a escala continua a mesma.',
            'matches_500' => 'A base é o tom 500 da escala.',
            'clipped' => 'Croma reduzido para caber no sRGB.',
            'alpha_note' => 'O contraste considera o tom composto sobre o fundo da mesma polaridade do texto.',
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
            'description' => 'Copie ou descarregue a escala pronta para o seu projeto.',
            'name_label' => 'Nome da paleta',
            'name_hint' => 'Usado nas variáveis: {slug}',
            'name_invalid' => 'O nome será normalizado para {slug}.',
            'copy' => 'Copiar',
            'copied' => '{name} copiado',
            'download' => 'Descarregar',
            'semantic_included' => 'Os papéis semânticos do protótipo estão incluídos.',
            'tabs' => [
                'css' => 'CSS',
                'scss' => 'SCSS',
                'json' => 'JSON',
                'dtcg' => 'DTCG',
                'style_dictionary' => 'Style Dictionary',
                'typescript' => 'TypeScript',
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
            'stale' => 'A cor escrita não é válida; a escala anterior continua no ecrã.',
        ],

        'save' => [
            'action' => 'Guardar paleta',
            'draft_restored' => 'A sua paleta voltou. Reveja e guarde.',
        ],
    ],

    'layout-harmonizer' => [
        'name' => 'Layout Harmonizer',
        'description' => 'Gere os tokens semânticos de cor de uma interface clara e escura a partir de uma seed: 33 tokens, diagnóstico de contraste e valores copiáveis.',
        'keywords' => 'layout, tokens semânticos, design tokens, sistema de cores, tema claro, tema escuro, contraste, WCAG, OKLCH',
        'intro' => 'Escolha uma cor seed para obter o quadro semântico completo nos temas claro e escuro: marca, superfícies, feedback, conteúdo e interação, cada um com a finalidade e o contraste medido. Nada é gerado antes de uma cor válida, e nada sai do seu navegador.',

        'seed' => [
            'title' => 'Cor seed',
            'description' => 'O quadro deriva desta cor. Escreva um valor ou use o seletor.',
            'empty_value' => 'Nenhuma cor ainda',
            'background_label' => 'Fundo da composição',
            'background_hint' => 'Obrigatório porque a seed tem transparência. Qualquer formato serve: HEX, RGB ou OKLCH.',
            'background_invalid' => 'Este fundo não é uma cor válida.',
            'background_alpha_invalid' => 'O fundo de composição tem de ser opaco: a transparência não é aceite aqui.',
            'summary' => [
                'original' => 'Original',
                'effective' => 'Efetiva',
                'background' => 'Fundo',
                'strategy' => 'Estratégia',
                'secondary' => 'Secundária',
                'alpha_note' => 'Com transparência, a seed efetiva é a cor composta sobre o fundo.',
                'achromatic' => 'A seed é acromática: as rotações de matiz são reduzidas ao tom neutro e o quadro mantém-se acromático.',
            ],
        ],

        'strategy' => [
            'label' => 'Estratégia de harmonia',
            'hint' => 'Os 33 tokens não mudam; a estratégia muda a derivação da secundária e do accent.',
            'analogous' => [
                'name' => 'Análoga',
                'description' => 'Secundária a 30° da seed: vizinhas próximas, pouca tensão.',
            ],
            'complementary' => [
                'name' => 'Complementar',
                'description' => 'Secundária a 180° da seed: contraste máximo.',
            ],
            'triadic' => [
                'name' => 'Triádica',
                'description' => 'Secundária a 240° da seed: três matizes equidistantes.',
            ],
            'monochromatic' => [
                'name' => 'Monocromática',
                'description' => 'Secundária na mesma matiz: variação só em luminosidade e croma.',
            ],
        ],

        'view' => [
            'light' => 'Claro',
            'dark' => 'Escuro',
            'compare' => 'Comparar',
            'board_light' => 'Tema claro',
            'board_dark' => 'Tema escuro',
        ],

        'families' => [
            'brand' => 'Marca',
            'surface' => 'Superfícies',
            'feedback' => 'Feedback',
            'content' => 'Conteúdo',
            'interaction' => 'Interação',
        ],

        'tokens' => [
            'primary' => 'Cor principal da marca; base de botões sólidos e ênfase.',
            'primary-variant' => 'Tom mais profundo da marca para blocos grandes, separado do hover.',
            'secondary' => 'Cor de apoio para ações menos proeminentes.',
            'secondary-variant' => 'Tom mais profundo da secundária para superfícies maiores.',
            'surface' => 'Fundo padrão de painéis.',
            'surface-variant' => 'Fundo subtil para conteúdo agrupado.',
            'background' => 'Fundo da página, a camada mais externa.',
            'hairline' => 'Bordas e divisores.',
            'error' => 'Fundo e cor de texto do estado de erro.',
            'success' => 'Fundo e cor de texto do estado de sucesso.',
            'warning' => 'Fundo e cor de texto do estado de alerta.',
            'info' => 'Fundo e cor de texto do estado informativo.',
            'on-primary' => 'Texto e ícones sobre a primária, hover e active.',
            'on-secondary' => 'Texto e ícones sobre a secundária e os seus estados.',
            'on-background' => 'Texto principal sobre o fundo da página.',
            'on-surface' => 'Texto sobre superfícies e cartões.',
            'on-surface-variant' => 'Texto secundário: legendas, dicas e metadados.',
            'on-error' => 'Texto e ícones sobre o erro.',
            'on-success' => 'Texto e ícones sobre o sucesso.',
            'on-warning' => 'Texto e ícones sobre o alerta.',
            'on-info' => 'Texto e ícones sobre o informativo.',
            'primary-hover' => 'Primária ao pairar e em estados de ponteiro.',
            'primary-active' => 'Primária durante o clique.',
            'secondary-hover' => 'Secundária ao pairar.',
            'secondary-active' => 'Secundária durante o clique.',
            'link' => 'Links dentro do conteúdo.',
            'link-hover' => 'Link ao pairar.',
            'link-active' => 'Link durante o clique.',
            'focus-ring' => 'Anel de foco do teclado.',
            'accent' => 'Terceira cor da harmonia, presente em todas as estratégias.',
            'on-accent' => 'Texto e ícones sobre o accent e os seus estados.',
            'accent-hover' => 'Accent ao pairar.',
            'accent-active' => 'Accent durante o clique.',
        ],

        'provenance' => [
            'seed' => 'Da seed',
            'manual' => 'Manual',
            'rule' => 'Por regra',
            'feedback' => 'Feedback',
        ],

        'contrast' => [
            'aa' => 'AA',
            'aa_fail' => 'Falha AA',
            'against' => 'Contra {background}',
            'on' => 'sobre',
            'summary_title' => 'Contraste AA',
            'all_pass' => 'Todos os pares medidos atingem AA para texto normal.',
        ],

        'copy' => [
            'hex_copied' => 'Copiado {token}: HEX',
            'oklch_copied' => 'Copiado {token}: OKLCH',
            'hex_aria' => 'Copiar HEX de {token}',
            'oklch_aria' => 'Copiar OKLCH de {token}',
        ],

        'clipped' => [
            'badge' => 'Croma reduzido',
            'note' => 'O croma pedido está fora do sRGB e foi reduzido para a cor apresentável mais próxima.',
        ],

        'states' => [
            'empty_title' => 'Ainda sem quadro',
            'empty_description' => 'Escolha uma cor seed para gerar os quadros claro e escuro.',
            'invalid_seed' => 'A cor escrita não é válida; o quadro anterior continua no ecrã.',
            'seed_alpha_without_background' => 'A seed tem transparência: indique um fundo de composição para gerar o quadro.',
            'seed_background_alpha_invalid' => 'O fundo da seed tem transparência: indique uma cor opaca.',
            'secondary_alpha_without_background' => 'A secundária tem transparência: indique um fundo de composição opaco para gerar o quadro.',
            'invalid_strategy' => 'A estratégia escolhida não é reconhecida; o quadro anterior continua no ecrã.',
            'stale_board' => 'O quadro apresentado reflete a última entrada válida.',
        ],

        'edit' => [
            'title' => 'Editar {token}',
            'open_aria' => 'Editar {token}',
            'apply' => 'Aplicar',
            'cancel' => 'Cancelar',
            'alpha_ignored' => 'Os tokens são opacos: o alfa escolhido é ignorado.',
        ],

        'lock' => [
            'lock' => 'Bloquear',
            'unlock' => 'Desbloquear',
            'lock_aria' => 'Bloquear {token}',
            'unlock_aria' => 'Desbloquear {token}',
        ],

        'restore' => [
            'token' => 'Restaurar token',
            'token_aria' => 'Restaurar {token} para o automático',
            'theme' => 'Restaurar {theme} ({count})',
            'all' => 'Restaurar tudo ({count})',
            'confirm_title' => 'Descartar alterações manuais?',
            'confirm_body' => 'Isto remove valores manuais e bloqueios de {count} token(s) neste âmbito e volta à geração automática.',
            'confirm' => 'Restaurar',
            'cancel' => 'Cancelar',
        ],

        'secondary' => [
            'label' => 'Cor secundária (opcional)',
            'hint' => 'Vazio mantém a secundária automática da estratégia. As variantes e os estados continuam derivados por tema.',
            'invalid' => 'Esta secundária não é uma cor válida; o último valor válido mantém-se.',
            'background_label' => 'Fundo da composição',
            'background_hint' => 'Obrigatório porque a secundária tem transparência. Qualquer formato serve: HEX, RGB ou OKLCH.',
            'background_invalid' => 'Este fundo não é uma cor válida.',
            'background_alpha_invalid' => 'O fundo de composição tem de ser opaco: a transparência não é aceite aqui.',
            'manual' => 'Secundária manual',
            'auto' => 'Secundária automática',
            'reset' => 'Voltar ao automático',
        ],

        'suggestions' => [
            'title' => 'Sugestões de contraste',
            'description' => 'Cada sugestão altera apenas a cor do texto, no menor passo de luminosidade que atinge a meta escolhida contra todos os fundos desse token. Nada é aplicado sem a sua aceitação.',
            'badge' => 'Sugestão',
            'target' => [
                'aa' => 'Meta AA',
                'aaa' => 'Meta AAA',
            ],
            'none' => 'Todos os pares medidos atingem a meta escolhida.',
            'delta_lightness' => 'ΔL {delta}',
            'accept_theme' => 'Aceitar em {theme}',
            'accept_both' => 'Aceitar nos dois temas',
            'inviable' => 'Nenhuma luminosidade atinge {target} contra todos os fundos deste token.',
            'inviable_badge' => 'Inviável',
        ],

        'edit_notice' => [
            'discarded' => 'Os valores manuais sem bloqueio foram descartados porque a seed, a estratégia ou a secundária mudaram. Os valores bloqueados foram mantidos.',
        ],

        'feedback_preview' => [
            'title' => 'Pares de feedback',
            'description' => 'Cada cor de feedback com a cor de conteúdo medida sobre ela.',
        ],

        'a11y' => [
            'view_mode' => 'Visualização do tema',
            'board_section' => 'Quadro: {theme}',
            'contrast_summary' => 'Diagnóstico de contraste',
            'restore' => 'Restaurar a geração automática',
            'suggestions' => 'Sugestões de contraste',
            'suggestion_target' => 'Meta de contraste das sugestões',
        ],

        'prototype' => [
            'title' => 'Protótipo',
            'description' => 'Veja os 33 tokens do sistema aplicados a uma interface, no modelo e no tema escolhidos.',
            'template_label' => 'Modelo',
            'template_site' => 'Site',
            'template_mobile' => 'Telemóvel',
            'theme_label' => 'Tema',
            'theme_light' => 'Claro',
            'theme_dark' => 'Escuro',
            'preview_label' => 'Pré-visualização do protótipo: {template}, {theme}',
        ],

        'save' => [
            'title' => 'Guardar sistema de layout',
            'description' => 'O quadro gravado abre novamente exatamente como está, sem recalcular.',
            'name_label' => 'Nome',
            'name_placeholder' => 'O meu sistema',
            'name_required' => 'Indique um nome.',
            'name_too_long' => 'Use no máximo {max} caracteres.',
            'name_from_palette' => 'Sistema de layout — {name}',
            'name_from_strategy' => '{strategy} — {hex}',
            'description_label' => 'Descrição (opcional)',
            'description_placeholder' => 'Para que serve este sistema?',
            'description_too_long' => 'Use no máximo {max} caracteres.',
            'visibility_label' => 'Quem pode ver',
            'visibility_private' => 'Privado',
            'visibility_private_hint' => 'Só você o vê. Pode publicar mais tarde.',
            'visibility_public' => 'Público',
            'visibility_public_hint' => 'Ganha uma ligação pública e aparece na pesquisa.',
            'save_button' => 'Guardar',
            'submit' => 'Guardar sistema',
            'submit_update' => 'Guardar alterações',
            'save_as_new' => 'Guardar como novo',
            'saving' => 'A guardar…',
            'cancel' => 'Cancelar',
            'login_to_save' => 'Entrar para guardar',
            'limit_reached' => 'Atingiu o limite de {limit} itens. Arquive ou elimine algo para guardar.',
        ],

        'draft' => [
            'title' => 'Retomar o rascunho?',
            'description' => 'Encontrámos um quadro guardado neste navegador antes de iniciar sessão. Importar mantém o que fez e não cria um sistema.',
            'unsupported_title' => 'Rascunho de uma versão mais recente',
            'unsupported_description' => 'Este rascunho foi criado por uma versão mais recente da ferramenta e não pode ser importado aqui. Pode descartá-lo.',
            'import' => 'Importar rascunho',
            'discard' => 'Descartar',
            'discard_stored' => 'Descartar rascunho guardado',
            'discard_title' => 'Descartar o rascunho guardado?',
            'discard_body' => 'O quadro guardado neste navegador é apagado e a ferramenta começa vazia.',
        ],

        'origin' => [
            'from' => 'Criado a partir da paleta {name}.',
            'open' => 'Ver paleta',
            'remove' => 'Remover origem',
        ],

        'lifecycle' => [
            'restore_disabled' => 'Restaurar fica indisponível neste sistema: o estado gravado não guarda os valores automáticos. Use “Regenerar com a versão atual”.',
            'suggestions_off' => 'As sugestões de contraste ficam indisponíveis neste sistema porque a versão do algoritmo de sugestões mudou.',
            'pending_regeneration' => 'A seed, a estratégia ou a secundária mudou. O quadro continua no estado guardado até regenerar.',
            'regenerate' => 'Regenerar com a versão atual',
            'regenerate_available' => 'Este sistema foi guardado com uma versão anterior. O quadro continua exatamente como foi guardado; regenerar aplica a versão atual às mesmas entradas.',
            'regenerate_confirm_title' => 'Regenerar com a versão atual?',
            'regenerate_confirm_body' => 'Os tokens bloqueados são mantidos. {count} valor(es) manual(is) ou aceite(s) sem bloqueio serão descartados.',
            'readonly_notice' => 'Este sistema usa uma versão mais recente do que este editor. O quadro aparece apenas para leitura; duplique para continuar a editar.',
        ],

        'guard' => [
            'title' => 'Sair sem guardar?',
            'description' => 'Tem alterações que ainda não foram guardadas. Se sair agora, serão perdidas.',
            'stay' => 'Continuar a editar',
            'leave' => 'Sair sem guardar',
        ],
        'export' => [
            'title' => 'Exportar',
            'description' => 'Descarregue o quadro no ecrã como instruções para uma IA, como variáveis CSS ou os dois num ZIP. Tudo é gerado aqui, no seu navegador, sem voltar a gerar cores.',
            'login_cta' => 'Entrar para exportar',
            'login_hint' => 'Criar uma conta é gratuito. O seu quadro mantém-se exatamente como está — não precisa de o guardar para exportar.',
            'language_label' => 'Idioma do documento',
            'prefix_label' => 'Prefixo CSS',
            'prefix_hint' => 'As variáveis passam a `--{prefix}-primary`, `--{prefix}-background` e assim por diante.',
            'scope_label' => 'Âmbito CSS',
            'scope_root' => 'Raiz do documento (:root)',
            'scope_class' => 'Classe personalizada',
            'scope_class_label' => 'Nome da classe',
            'download_markdown' => 'Descarregar Markdown',
            'download_css' => 'Descarregar CSS',
            'download_zip' => 'Descarregar ZIP',
            'pending_regeneration' => 'As entradas mudaram depois da última geração. Gere novamente o quadro antes de exportar.',
            'compatible_notice' => 'Este estado foi gerado por um algoritmo mais antigo. Os ficheiros levam as versões guardadas.',
            'readonly_notice' => 'Este estado é só de leitura. Pode na mesma exportar os valores apresentados.',
            'errors' => [
                'prefix_required' => 'Indique um prefixo.',
                'prefix_too_long' => 'Use no máximo {max} caracteres.',
                'prefix_invalid' => 'Use letras minúsculas, dígitos e hífenes simples, começando por uma letra.',
                'scope_required' => 'Indique um âmbito.',
                'scope_too_long' => 'Use no máximo {max} caracteres.',
                'scope_invalid' => 'Use :root ou uma classe simples como .app.',
            ],
            'targets' => [
                'aa' => 'AA',
                'aaa' => 'AAA',
            ],
            'css' => [
                'header' => 'Layout Harmonizer — {name}',
                'meta' => '{language} · Estado {state} · Schema {schema} · Algoritmo {algorithm} · Sugestões {suggestions} · Snapshot {snapshot}',
                'theme_default' => 'Tema predefinido: claro. Para o escuro, defina data-theme="dark" no âmbito.',
            ],
            'warnings' => [
                'title' => 'Avisos',
                'none' => 'Nenhum aviso.',
                'contrast' => '{theme}: {foreground} sobre {background} — {ratio} (objetivo {target})',
                'locked' => '{theme}: {token} está bloqueado.',
                'manual' => '{theme}: {token} foi definido manualmente ({hex}).',
                'accepted' => '{theme}: {token} ajustado de {from} para {to} (objetivo {target}).',
                'clipped' => '{theme}: croma de {token} reduzido para caber em sRGB.',
                'compatible' => 'Modo compatível: algoritmo {algorithm} e sugestões {suggestions} diferentes do editor atual.',
                'readonly' => 'Estado só de leitura: schema {schema}, algoritmo {algorithm}, sugestões {suggestions}.',
                'limitations' => 'Os objetivos de contraste são indicativos: as falhas são reportadas e nenhuma cor é alterada automaticamente.',
            ],
            'md' => [
                'purpose' => 'Instruções de design para uma interface construída com este sistema de cores semântico. Use apenas os tokens listados aqui e mantenha as finalidades.',
                'metadata_title' => 'Metadados',
                'language' => 'Idioma: {language}',
                'strategy' => 'Estratégia: {strategy}',
                'target' => 'Objetivo de contraste: {target}',
                'seed_original' => 'Seed original: {hex}',
                'seed_effective' => 'Seed efetiva: {hex}',
                'seed_background' => 'Fundo da composição: {hex}',
                'secondary' => 'Secundária: {mode}',
                'secondary_auto' => 'automática',
                'secondary_manual' => 'manual',
                'secondary_original' => 'Secundária original: {hex}',
                'secondary_effective' => 'Secundária efetiva: {hex}',
                'secondary_background' => 'Fundo de composição da secundária: {hex}',
                'versions' => 'Versões: estado {state}, schema {schema}, algoritmo {algorithm}, sugestões {suggestions}, snapshot {snapshot}',
                'origin' => 'Origem: {name} (paleta pública).',
                'instructions_title' => 'Instruções para a IA',
                'instructions_intro' => 'Aplique os tokens abaixo à interface. Não invente cores nem fixe valores fora desta lista.',
                'instructions_semantic' => 'Use os tokens semânticos em vez de cores cruas da paleta: `background` para a página, `surface` para painéis, `on-*` para texto e ícones.',
                'instructions_manual' => 'Os tokens marcados como manuais ou bloqueados devem ficar exatamente como listados; não os recalcule.',
                'instructions_contrast' => 'Mantenha os pares listados acima do objetivo {target}; quando um par falhar, reporte em vez de mudar cores.',
                'instructions_map' => 'Mapeie fundos para `background` e `surface`, texto para `on-background`, `on-surface` e `on-surface-variant`, botões para `primary`, `secondary` e `accent` com os estados `-hover` e `-active`, links para `link`, `link-hover` e `link-active`, feedback para `error`, `success`, `warning` e `info` com os seus `on-*`, e contornos de foco para `focus-ring`.',
                'themes_title' => 'Temas',
                'themes_note' => 'O tema claro é o predefinido; o escuro aplica-se quando a interface declara data-theme="dark".',
                'tokens_title' => 'Tokens — {theme}',
                'columns' => [
                    'token' => 'Token',
                    'purpose' => 'Finalidade',
                    'hex' => 'HEX',
                    'oklch' => 'OKLCH',
                    'source' => 'Origem',
                    'locked' => 'Bloqueado',
                    'foreground' => 'Frente',
                    'background' => 'Fundo',
                    'ratio' => 'Razão',
                    'aa' => 'AA',
                    'aaa' => 'AAA',
                ],
                'locked_yes' => 'Sim',
                'locked_no' => 'Não',
                'rules_title' => 'Regras de aplicação',
                'rules' => [
                    'backgrounds' => 'Use `background` para a página e `surface` para cartões e painéis; `surface-variant` agrupa conteúdo aninhado e `hairline` desenha contornos e divisores.',
                    'text' => 'Use `on-background` para o texto principal, `on-surface` para texto sobre cartões e `on-surface-variant` para legendas, pistas e metadados.',
                    'buttons' => 'Botões sólidos usam `primary`; ações de apoio usam `secondary`; a terceira cor da harmonia é `accent`. Os estados hover e premido usam os tokens `-hover` e `-active` correspondentes, com o texto no token `on-*` dessa família.',
                    'links' => 'Os links usam `link`, `link-hover` e `link-active` dentro do conteúdo, nunca outra cor de marca diretamente.',
                    'feedback' => 'As mensagens de feedback usam `error`, `success`, `warning` ou `info` como fundo, com texto e ícones no token `on-*` correspondente.',
                    'focus' => 'O foco por teclado usa `focus-ring` como contorno, nunca uma cor fora desta lista.',
                ],
                'contrast_title' => 'Contraste — {theme}',
                'pass' => 'Passa',
                'fail' => 'Falha',
                'generated_title' => 'Gerado',
                'generation_note' => 'Gerado no navegador a partir do snapshot apresentado (estado {state}, snapshot {snapshot}). Nenhuma IA foi chamada e nada foi enviado ao servidor.',
            ],
        ],
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
            'upload_hint' => 'SVG até {size} KB com viewBox e formas vetoriais simples (path, rect, circle, ellipse, polygon, polyline, line e g; sem texto, style, transform ou imagens embutidas).',
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
            'custom' => 'Personalizado',
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
                'codecov_coverage' => 'Cobertura do Codecov',
                'coveralls_coverage' => 'Cobertura do Coveralls',
                'laravel_v' => 'Versão do Laravel',
                'laravel_dt' => 'Transferências do Laravel',
                'php_v' => 'Versão do PHP',
                'python_v' => 'Versão do Python',
            ],
        ],
        'custom_providers' => [
            'title' => 'Fornecedores personalizados',
            'description' => 'Badges a partir de qualquer documento JSON público que mapeie. O URL de origem fica no servidor; a imagem usa um identificador opaco.',
            'create_button' => 'Novo fornecedor',
            'readonly_note' => 'Os fornecedores personalizados estão disponíveis nos planos pagos. As badges existentes continuam a funcionar e podem ser copiadas ou eliminadas.',
            'upgrade' => 'Melhorar plano',
            'limit_reached' => 'Atingiu o limite de {limit} fornecedores personalizados.',
            'empty' => 'Ainda não tem fornecedores personalizados.',
            'disabled' => 'Desativado',
            'copy_svg' => 'Copiar SVG',
            'copy_png' => 'Copiar PNG',
            'delete_description' => 'O URL público deixa de funcionar e a cópia em cache é descartada.',
            'dialog_create_title' => 'Criar fornecedor personalizado',
            'dialog_edit_title' => 'Editar fornecedor personalizado',
            'dialog_description' => 'Aponte para um documento JSON público e escolha os valores com JSON Pointer.',
            'template_immutable' => 'O template de origem é imutável: para o trocar, elimine e crie de novo. Os slugs das métricas também não podem ser renomeados.',
            'add_metric' => 'Adicionar métrica',
            'remove_metric' => 'Remover métrica',
            'create_submit' => 'Criar fornecedor',
            'update_submit' => 'Guardar alterações',
            'fields' => [
                'name' => 'Nome',
                'url_template' => 'Template de origem',
                'path_params' => 'Parâmetros do caminho',
                'example_path' => 'Caminho de exemplo',
                'metrics' => 'Métricas',
                'metric_slug' => 'Slug',
                'metric_label' => 'Rótulo',
                'pointer' => 'JSON Pointer',
                'formatter' => 'Formato',
            ],
            'hints' => [
                'url_template' => 'URL HTTPS com os marcadores que declarar, por exemplo https://api.example.com/{\'{\'}owner{\'}\'}/{\'{\'}repo{\'}\'}.',
                'template_note' => 'Os marcadores do caminho usam {\'{\'}name{\'}\'}. O template é validado ao guardar.',
                'path_params' => 'Marcadores separados por vírgulas, pela ordem do caminho.',
                'example_path' => 'Um caminho real usado para validar o mapeamento ao guardar.',
                'pointer' => 'RFC 6901, por exemplo /stargazers_count ou /items/0/score.',
            ],
            'formatters' => [
                'text' => 'Texto',
                'number' => 'Número',
                'percent' => 'Percentagem',
            ],
            'builder' => [
                'sections' => [
                    'metric' => 'Métrica',
                ],
                'fields' => [
                    'provider' => 'Fornecedor',
                    'definition' => 'Métrica',
                ],
                'invalid_param' => 'Verifique os parâmetros e tente novamente.',
                'example' => 'Exemplo: {path}',
                'cache_note' => 'A cache das badges de fornecedor personalizado é controlada pelo servidor.',
                'preview_invalid' => 'Preencha os parâmetros para ver a badge.',
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

    'data-format-converter' => [
        'name' => 'Data Format Converter',
        'description' => 'Converta JSON, YAML, TOML e XML entre si, com aviso do que o formato de destino não representa.',
        'keywords' => 'converter, json, yaml, toml, xml, formato',
        'intro' => 'Escolha o formato de entrada e o de saída e cole o documento: a conversão corre no seu navegador e o que o destino não representa aparece como aviso. Nada do que colar aqui é enviado para o servidor.',

        'from' => 'Formato de entrada',
        'to' => 'Formato de saída',
        'swap' => 'Trocar',
        'download_name' => 'documento',
        'file_types' => 'JSON, YAML, TOML, XML ou TXT',

        'formats' => [
            'json' => 'JSON',
            'yaml' => 'YAML',
            'toml' => 'TOML',
            'xml' => 'XML',
        ],

        'errors' => [
            'toml-root' => 'A raiz do TOML tem de ser uma tabela: uma lista ou um valor solto não têm onde caber.',
            'toml-mixed-array' => 'O TOML não aceita uma lista com tipos misturados (em {key}).',
            'toml-invalid' => 'O TOML recusou o documento (em {key}).',
            'xml-name' => 'A chave {key} não é um nome válido de elemento XML.',
            'xml-attribute' => 'O atributo {key} tem de ser um valor simples; objetos e listas não passam a atributo.',
            'xml-invalid' => 'Não foi possível escrever o XML.',
        ],

        'losses' => [
            'title' => 'O que ficou de fora',
            'date-as-text' => 'Datas do TOML passam a texto no destino: {count}.',
            'binary-as-base64' => 'Dados binários passam a texto em Base64: {count}.',
            'bigint-as-text' => 'Inteiros maiores do que o JavaScript representa passam a texto: {count}.',
            'comments-dropped' => 'Comentários não são mantidos: {count}.',
            'null-dropped' => 'Os valores nulos não existem no TOML e saíram: {count}.',
            'null-as-empty-element' => 'Os valores nulos passam a elemento vazio no XML: {count}.',
            'non-finite-as-null' => 'NaN e Infinity não existem no JSON e passaram a null: {count}.',
            'non-finite-as-text' => 'NaN e Infinity passam a texto no XML: {count}.',
            'mixed-content-flattened' => 'O texto misturado com elementos foi junto antes dos filhos: {count}.',
            'root-wrapped' => 'O documento ganhou um elemento raiz <root>: {count}.',
            'value-dropped' => 'Valores sem representação na árvore saíram: {count}.',
        ],
    ],

    'diff' => [
        'name' => 'Diff',
        'description' => 'Compare dois textos linha a linha ou dois documentos JSON por caminho, com realce e relatório pronto a copiar.',
        'keywords' => 'comparar, diferença, diff, texto, json',
        'intro' => 'Cole o original e o modificado e veja onde os dois divergem. No modo texto a comparação é linha a linha, com realce das regiões; no modo JSON é semântica, chave a chave, e a ordem das chaves não conta como alteração. Tudo corre no seu navegador: nada do que colar aqui é enviado para o servidor.',

        'left' => 'Original',
        'right' => 'Modificado',
        'open_left' => 'Abrir o original',
        'open_right' => 'Abrir o modificado',
        'previous' => 'Diferença anterior',
        'next' => 'Diferença seguinte',
        'summary' => [
            'text' => 'Adicionadas: {added} · Removidas: {removed}',
            'json' => 'Adicionadas: {added} · Removidas: {removed} · Alteradas: {changed}',
        ],
        'truncated' => 'Diff grosseiro: passou do orçamento de edições.',
        'no_changes' => 'Sem diferença.',
        'changes_title' => 'Caminhos que mudaram',
        'invalid_side' => 'O lado {side} não é JSON válido: {message}',
        'download_name' => 'diff',
        'file_types' => 'TXT, JSON, YAML, TOML ou XML',

        'modes' => [
            'text' => 'Texto',
            'json' => 'JSON',
        ],

        'report' => [
            'title' => 'Diff',
            'text' => 'Texto',
            'json' => 'JSON',
            'original' => 'original',
            'modified' => 'modificado',
            'added' => 'Adicionadas',
            'removed' => 'Removidas',
            'changed' => 'Alteradas',
            'no_changes' => 'Sem diferença.',
            'truncated' => 'Diff grosseiro: passou do orçamento de edições.',
            'invalid' => 'JSON inválido',
            'line' => 'linha',
            'column' => 'coluna',
        ],
    ],
];
