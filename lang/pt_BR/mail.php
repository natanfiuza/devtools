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
];
