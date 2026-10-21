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
        'error_greeting' => 'Ops!',
        'salutation' => 'Atenciosamente,',
        'subcopy' => 'Se estiver com dificuldade para clicar no botão ":actionText", copie e cole o endereço abaixo no seu navegador:',
        'rights' => 'Todos os direitos reservados.',
    ],

    'reset' => [
        'subject' => 'Redefinir sua senha',
        'intro' => 'Você pediu para redefinir a senha da sua conta no :app.',
        'action' => 'Criar nova senha',
        'expire' => 'O link vale por :minutes minutos.',
        'ignore' => 'Se não foi você, ignore este e-mail: sua senha continua a mesma.',
    ],

    'verify' => [
        'subject' => 'Confirme seu e-mail',
        'intro' => 'Falta pouco: confirme que este e-mail é seu para liberar sua conta no :app.',
        'action' => 'Confirmar e-mail',
        'ignore' => 'Se você não criou a conta, ignore este e-mail.',
    ],

    'newsletter' => [
        'subject' => 'Confirme sua inscrição na newsletter',
        'intro' => 'Falta um clique para você começar a receber a newsletter do :app: é só confirmar que este e-mail é seu.',
        'action' => 'Confirmar inscrição',
        'ignore' => 'Se não foi você, ignore este e-mail: nada será enviado.',
    ],

    'intent' => [
        'subject' => '[:app] Nova intenção de assinatura (@:username)',
        'intro' => 'Nova intenção de assinatura Pro registrada por :name (:email).',
        'details' => 'Plano: :plan | Usuário: :username | Telefone: :phone | Documento: :document | Pagamento: :method',
        'notes' => 'Observações: :notes',
        'action' => 'Abrir intenções no painel',
    ],

    'payment' => [
        'confirmed' => [
            'subject' => 'Pagamento confirmado',
            'greeting' => 'Olá, :name!',
            'intro' => 'Recebemos seu pagamento no :app.',
            'amount' => 'Valor: :amount',
            'method' => 'Forma de pagamento: :method',
            'paid_at' => 'Data do pagamento: :date',
            'plan' => 'Plano: :plan',
            'period_end' => 'Sua assinatura vale até :date.',
            'action' => 'Ver faturas',
        ],
        'refunded' => [
            'subject' => 'Pagamento estornado',
            'greeting' => 'Olá, :name!',
            'intro' => 'Seu pagamento no :app foi estornado.',
            'amount' => 'Valor estornado: :amount',
            'method' => 'Forma de pagamento original: :method',
            'paid_at' => 'Data do pagamento original: :date',
            'refunded_at' => 'Data do estorno: :date',
            'plan' => 'Plano: :plan',
            'subscription_canceled' => 'A assinatura vinculada a este pagamento foi encerrada.',
            'action' => 'Ver faturas',
        ],
    ],
];
