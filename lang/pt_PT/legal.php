<?php

/*
| Paginas legais: Politica de Privacidade e Termos de Uso (namespace "legal").
|
| O e-mail de contato e a data da revisao vem de config/legal.php (nao ficam
| presos aqui). ":date" e montado no servidor (Laravel); "{app}" vem do Vue (vue-i18n).
*/

return [
    'updated' => 'Última atualização: :date',
    'contact' => 'Contacto',

    'privacy' => [
        'title' => 'Política de Privacidade',
        'intro' => 'Esta página explica que dados o {app} guarda, porquê e durante quanto tempo. Em resumo: as ferramentas correm no seu navegador e só guardamos o que a conta precisa.',
        'sections' => [
            'data' => [
                'title' => 'O que guardamos',
                'body' => 'O mínimo para uma conta: nome, e-mail, palavra-passe (como hash) e o idioma que usa. Se entra com Google ou GitHub, guardamos o id da conta no fornecedor e o seu e-mail — nunca a sua palavra-passe lá. Se subscreve a newsletter, guardamos o e-mail e o idioma da subscrição.',
            ],
            'purpose' => [
                'title' => 'Para que usamos',
                'body' => 'Para manter a sua conta a funcionar, enviar os e-mails que pediu (redefinição de palavra-passe, confirmação de e-mail, newsletter) e proteger o site contra abuso.',
            ],
            'browser' => [
                'title' => 'As ferramentas correm no seu navegador',
                'body' => 'O que cola numa ferramenta é processado no seu navegador e não é enviado para os nossos servidores. Os dados de conta acima são a única coisa que guardamos.',
            ],
            'sharing' => [
                'title' => 'Com quem partilhamos',
                'body' => 'Não vendemos nem alugamos os seus dados. Partilhamos apenas com quem faz o site funcionar: alojamento e envio de e-mails, além do Google ou GitHub quando escolhe entrar com eles.',
            ],
            'cookies' => [
                'title' => 'Cookies',
                'body' => 'Usamos apenas cookies essenciais: sessão, idioma e preferência de tema. Nada de cookies de publicidade. Uma medição de uso agregada pode entrar mais tarde, sempre com a opção de recusar.',
            ],
            'retention' => [
                'title' => 'Durante quanto tempo',
                'body' => 'Os dados da conta ficam enquanto a conta existir e desaparecem quando elimina a conta. Os dados da newsletter ficam até cancelar a subscrição.',
            ],
            'rights' => [
                'title' => 'Os seus direitos',
                'body' => 'Pode ver e alterar os seus dados nas configurações da conta, e pode pedir uma cópia ou a eliminação por e-mail. Respondemos dentro do prazo legal.',
            ],
            'security' => [
                'title' => 'Segurança',
                'body' => 'As palavras-passe ficam guardadas como hash, o site corre em HTTPS e o acesso ao servidor é restrito. Nenhum sistema é perfeito: se algo acontecer, avisamos.',
            ],
            'changes' => [
                'title' => 'Alterações a esta página',
                'body' => 'Este texto pode ser atualizado à medida que o site evolui. A data da última revisão fica sempre no topo da página.',
            ],
        ],
    ],

    'terms' => [
        'title' => 'Termos de Utilização',
        'intro' => 'As regras para usar o {app}. Ao navegar ou criar uma conta, aceita estas regras.',
        'sections' => [
            'service' => [
                'title' => 'O que é o serviço',
                'body' => 'O {app} é um conjunto de ferramentas de programação que correm no seu navegador, oferecido tal como está e de forma gratuita, para uso pessoal ou profissional.',
            ],
            'account' => [
                'title' => 'A sua conta',
                'body' => 'É responsável pela sua conta: guarde a palavra-passe e o acesso social com cuidado. Contas criadas com Google ou GitHub não têm palavra-passe e são acedidas pelo fornecedor.',
            ],
            'use' => [
                'title' => 'Utilização aceitável',
                'body' => 'Use as ferramentas para fins lícitos. Não ataque, sobrecarregue ou recolha dados do serviço de forma automatizada, e não o use para quebrar sistemas de terceiros. Os resultados gerados (como SQL) são apoio: a decisão final é sua.',
            ],
            'content' => [
                'title' => 'Conteúdo',
                'body' => 'O que cola nas ferramentas continua a ser seu. O site, a marca e o código continuam a ser nossos.',
            ],
            'availability' => [
                'title' => 'Disponibilidade',
                'body' => 'Tentamos manter o site disponível, mas não o prometemos. Funcionalidades podem mudar ou ser descontinuadas.',
            ],
            'warranty' => [
                'title' => 'Sem garantias',
                'body' => 'O site é oferecido sem garantias. Não somos responsáveis por danos ou prejuízos resultantes do uso dos resultados gerados.',
            ],
            'changes' => [
                'title' => 'Alterações aos termos',
                'body' => 'Estes termos podem mudar. Continuar a usar o site depois de uma alteração significa que aceita a nova versão.',
            ],
            'law' => [
                'title' => 'Lei aplicável',
                'body' => 'Estes termos regem-se pelas leis do Brasil, e o foro brasileiro é competente para resolver qualquer litígio.',
            ],
        ],
    ],
];
