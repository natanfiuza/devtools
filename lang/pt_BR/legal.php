<?php

/*
| Paginas legais: Politica de Privacidade e Termos de Uso (namespace "legal").
|
| O e-mail de contato e a data da revisao vem de config/legal.php (nao ficam
| presos aqui). ":date" e montado no servidor (Laravel); "{app}" vem do Vue (vue-i18n).
*/

return [
    'updated' => 'Última atualização: :date',
    'contact' => 'Contato',

    'privacy' => [
        'title' => 'Política de Privacidade',
        'intro' => 'Esta página explica quais dados o {app} guarda, por quê e por quanto tempo. Resumindo: as ferramentas rodam no seu navegador e só guardamos o que a conta precisa.',
        'sections' => [
            'data' => [
                'title' => 'O que guardamos',
                'body' => 'O mínimo para uma conta: nome, e-mail, senha (como hash) e o idioma que você usa. Se você entra com Google ou GitHub, guardamos o id da conta no provedor e o seu e-mail — nunca a sua senha lá. Se você assina a newsletter, guardamos o e-mail e o idioma da inscrição.',
            ],
            'purpose' => [
                'title' => 'Para que usamos',
                'body' => 'Para manter sua conta funcionando, enviar os e-mails que você pediu (redefinição de senha, confirmação de e-mail, newsletter) e proteger o site contra abuso.',
            ],
            'browser' => [
                'title' => 'As ferramentas rodam no seu navegador',
                'body' => 'O que você cola em uma ferramenta é processado no seu navegador e não é enviado para os nossos servidores. Os dados de conta acima são a única coisa que guardamos.',
            ],
            'sharing' => [
                'title' => 'Com quem compartilhamos',
                'body' => 'Não vendemos nem alugamos seus dados. Compartilhamos apenas com quem faz o site funcionar: hospedagem e envio de e-mails, além do Google ou GitHub quando você escolhe entrar com eles.',
            ],
            'cookies' => [
                'title' => 'Cookies',
                'body' => 'Usamos apenas cookies essenciais: sessão, idioma e preferência de tema. Nada de cookie de publicidade. Uma medição de uso agregada pode entrar depois, sempre com a opção de recusar.',
            ],
            'retention' => [
                'title' => 'Por quanto tempo',
                'body' => 'Os dados da conta ficam enquanto a conta existir e vão embora quando você exclui a conta. Os dados da newsletter ficam até você se descadastrar.',
            ],
            'rights' => [
                'title' => 'Seus direitos',
                'body' => 'Você pode ver e alterar seus dados nas configurações da conta, e pode pedir uma cópia ou a exclusão por e-mail. Respondemos dentro do prazo legal.',
            ],
            'security' => [
                'title' => 'Segurança',
                'body' => 'As senhas ficam guardadas como hash, o site roda em HTTPS e o acesso ao servidor é restrito. Nenhum sistema é perfeito: se algo acontecer, avisamos você.',
            ],
            'changes' => [
                'title' => 'Mudanças nesta página',
                'body' => 'Este texto pode ser atualizado conforme o site evolui. A data da última revisão fica sempre no topo da página.',
            ],
        ],
    ],

    'terms' => [
        'title' => 'Termos de Uso',
        'intro' => 'As regras para usar o {app}. Ao navegar ou criar uma conta, você concorda com elas.',
        'sections' => [
            'service' => [
                'title' => 'O que é o serviço',
                'body' => 'O {app} é um conjunto de ferramentas de desenvolvimento que rodam no seu navegador, oferecido como está e de graça, para uso pessoal ou profissional.',
            ],
            'account' => [
                'title' => 'Sua conta',
                'body' => 'Você é responsável pela sua conta: guarde sua senha e seu acesso social com cuidado. Contas criadas com Google ou GitHub não têm senha e são acessadas pelo provedor.',
            ],
            'use' => [
                'title' => 'Uso aceitável',
                'body' => 'Use as ferramentas para fins lícitos. Não ataque, sobrecarregue ou raspe o serviço, e não o use para quebrar sistemas de terceiros. Resultados gerados (como SQL) são apoio: a decisão final é sua.',
            ],
            'content' => [
                'title' => 'Conteúdo',
                'body' => 'O que você cola nas ferramentas continua seu. O site, a marca e o código continuam nossos.',
            ],
            'availability' => [
                'title' => 'Disponibilidade',
                'body' => 'Tentamos manter o site no ar, mas não prometemos isso. Recursos podem mudar ou sair do ar.',
            ],
            'warranty' => [
                'title' => 'Sem garantias',
                'body' => 'O site é oferecido sem garantias. Não nos responsabilizamos por danos ou prejuízos decorrentes do uso dos resultados gerados.',
            ],
            'changes' => [
                'title' => 'Mudanças nos termos',
                'body' => 'Estes termos podem mudar. Continuar usando o site depois de uma mudança significa que você concorda com a nova versão.',
            ],
            'law' => [
                'title' => 'Lei aplicável',
                'body' => 'Estes termos são regidos pelas leis do Brasil, e o foro brasileiro é competente para resolver qualquer disputa.',
            ],
        ],
    ],
];
