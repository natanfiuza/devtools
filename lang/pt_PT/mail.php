<?php

/*
| Assuntos e corpos dos e-mails transacionais.
|
| Namespace SO do servidor: nao entra em config/locales.php e nao vai para o
| front. Por isso a interpolacao aqui e sempre ":param" (Laravel).
|
| Os e-mails saem no idioma da conta (HasLocalePreference no User), nao no
| idioma de quem pediu.
*/

return [
    'template' => [
        'greeting' => 'Olá!',
        'error_greeting' => 'Ups!',
        'salutation' => 'Com os melhores cumprimentos,',
        'subcopy' => 'Se estiver a ter dificuldade em clicar no botão ":actionText", copie e cole o endereço abaixo no seu navegador:',
        'rights' => 'Todos os direitos reservados.',
    ],

    'reset' => [
        'subject' => 'Redefinir a sua palavra-passe',
        'intro' => 'Pediu para redefinir a palavra-passe da sua conta no :app.',
        'action' => 'Criar nova palavra-passe',
        'expire' => 'A ligação é válida durante :minutes minutos.',
        'ignore' => 'Se não foi você, ignore este e-mail: a sua palavra-passe continua igual.',
    ],

    'verify' => [
        'subject' => 'Confirme o seu e-mail',
        'intro' => 'Falta pouco: confirme que este e-mail é seu para libertar a sua conta no :app.',
        'action' => 'Confirmar e-mail',
        'ignore' => 'Se não criou a conta, ignore este e-mail.',
    ],

    'newsletter' => [
        'subject' => 'Confirme a sua subscrição da newsletter',
        'intro' => 'Falta um clique para começar a receber a newsletter do :app: basta confirmar que este e-mail é seu.',
        'action' => 'Confirmar subscrição',
        'ignore' => 'Se não foi você, ignore este e-mail: nada será enviado.',
    ],

    'intent' => [
        'subject' => '[:app] Nova intenção de subscrição (@:username)',
        'intro' => 'Nova intenção de subscrição Pro registada por :name (:email).',
        'details' => 'Plano: :plan | Utilizador: :username | Telefone: :phone | Documento: :document | Pagamento: :method',
        'notes' => 'Observações: :notes',
        'action' => 'Abrir intenções no painel',
    ],

    'payment' => [
        'confirmed' => [
            'subject' => 'Pagamento confirmado',
            'greeting' => 'Olá, :name!',
            'intro' => 'Recebemos o seu pagamento no :app.',
            'amount' => 'Valor: :amount',
            'method' => 'Forma de pagamento: :method',
            'paid_at' => 'Data do pagamento: :date',
            'plan' => 'Plano: :plan',
            'period_end' => 'A sua subscrição é válida até :date.',
            'action' => 'Ver faturas',
        ],
        'refunded' => [
            'subject' => 'Pagamento devolvido',
            'greeting' => 'Olá, :name!',
            'intro' => 'O seu pagamento no :app foi devolvido.',
            'amount' => 'Valor devolvido: :amount',
            'method' => 'Forma de pagamento original: :method',
            'paid_at' => 'Data do pagamento original: :date',
            'refunded_at' => 'Data da devolução: :date',
            'plan' => 'Plano: :plan',
            'subscription_canceled' => 'A subscrição associada a este pagamento foi terminada.',
            'action' => 'Ver faturas',
        ],
        'renewal_failed' => [
            'subject' => 'Não conseguimos renovar a sua subscrição',
            'greeting' => 'Olá, :name!',
            'intro' => 'A cobrança automática da sua subscrição no :app não foi aprovada pelo cartão.',
            'plan' => 'Plano: :plan',
            'period_end' => 'O seu acesso mantém-se até :date.',
            'action' => 'Atualizar pagamento',
        ],
    ],

    'donation' => [
        'receipt' => [
            'subject' => 'Recebemos a sua doação',
            'greeting' => 'Olá, :name!',
            'greeting_anonymous' => 'Olá!',
            'intro' => 'Obrigado por apoiar o :app. A sua doação foi confirmada.',
            'amount' => 'Valor doado: :amount',
            'paid_at' => 'Confirmada em: :date',
            'note' => 'A doação não dá acesso a planos nem consome cota: é um apoio ao projeto.',
        ],
    ],

    'account_purged' => [
        'subject' => 'A sua conta no :app foi eliminada definitivamente',
        'greeting' => 'Olá, :name!',
        'greeting_anonymous' => 'Olá!',
        'intro' => 'O período de carência de 30 dias terminou e a sua conta no :app foi eliminada definitivamente.',
        'anonymized' => 'Todos os seus dados pessoais identificáveis foram removidos e a sua conta foi anonimizada de acordo com a legislação de proteção de dados.',
        'attached' => 'Em anexo, enviamos uma cópia em formato JSON com todos os dados exportados da sua conta antes da anonimização.',
    ],
];
