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
        'renewal_failed' => [
            'subject' => 'Não conseguimos renovar sua assinatura',
            'greeting' => 'Olá, :name!',
            'intro' => 'A cobrança automática da sua assinatura no :app não foi aprovada pelo cartão.',
            'plan' => 'Plano: :plan',
            'period_end' => 'Seu acesso segue até :date.',
            'action' => 'Atualizar pagamento',
        ],
    ],

    'donation' => [
        'receipt' => [
            'subject' => 'Recebemos sua doação',
            'greeting' => 'Olá, :name!',
            'greeting_anonymous' => 'Olá!',
            'intro' => 'Obrigado por apoiar o :app. Sua doação foi confirmada.',
            'amount' => 'Valor doado: :amount',
            'paid_at' => 'Confirmada em: :date',
            'note' => 'Doação não dá acesso a planos nem consome cota: é um apoio ao projeto.',
        ],
    ],

    'account_purged' => [
        'subject' => 'Sua conta no :app foi excluída definitivamente',
        'greeting' => 'Olá, :name!',
        'greeting_anonymous' => 'Olá!',
        'intro' => 'O período de carência de 30 dias terminou e sua conta no :app foi excluída definitivamente.',
        'anonymized' => 'Todos os seus dados pessoais identificáveis foram removidos e sua conta foi anonimizada conforme a LGPD.',
        'attached' => 'Em anexo, enviamos uma cópia em formato JSON contendo todos os dados exportados da sua conta antes da anonimização.',
    ],

    /*
    | Notificacoes sociais das paletas (F7, ADR-142). O e-mail nunca leva o
    | corpo do comentario nem o e-mail/IP de quem agiu.
    */
    'colors' => [
        'greeting' => 'Olá, :name!',
        'someone' => 'Alguém',
        'manage_hint' => 'Você pode escolher quais e-mails recebe na página de notificações.',
        'like' => [
            'subject' => 'Alguém curtiu sua paleta',
            'intro' => ':actor curtiu sua paleta ":palette".',
            'intro_anonymous' => 'Alguém curtiu sua paleta ":palette".',
            'action' => 'Ver paleta',
        ],
        'follow' => [
            'subject' => 'Você tem um novo seguidor',
            'intro' => ':actor começou a seguir você.',
            'action' => 'Ver perfil',
        ],
        'comment' => [
            'subject' => 'Novo comentário em ":palette"',
            'intro' => ':actor comentou na sua paleta ":palette".',
            'action' => 'Ver comentário',
        ],
        'reply' => [
            'subject' => 'Nova resposta ao seu comentário',
            'intro' => ':actor respondeu ao seu comentário em ":palette".',
            'action' => 'Ver resposta',
        ],
    ],

    'contact' => [
        'subject_prefix' => 'Mensagem de contato',
        'greeting' => 'Nova mensagem de contato recebida',
        'intro' => 'Uma nova mensagem foi enviada pelo formulário de contato do site.',
        'sender' => 'Remetente: :name (:email)',
        'subject_line' => 'Assunto: :subject',
        'message_header' => 'Mensagem:',
        'meta' => 'IP: :ip | Enviado em: :date',
    ],
];
