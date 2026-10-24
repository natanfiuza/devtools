<?php

/*
| Mensagens e textos da pagina publica de contacto (Fale connosco).
*/

return [
    'meta' => [
        'title' => 'Contacto',
        'description' => 'Fale com a equipa do devtools. Envie as suas dúvidas, sugestões, reporte erros ou fale sobre doações.',
    ],

    'title' => 'Fale connosco',
    'subtitle' => 'Tem alguma dúvida, sugestão ou encontrou um problema? Envie uma mensagem para a nossa equipa.',

    'fields' => [
        'name' => 'Nome',
        'name_placeholder' => 'O seu nome completo ou como prefere ser tratado',
        'email' => 'E-mail',
        'email_placeholder' => "o.seu{'@'}email.com",
        'email_hint' => 'Usaremos este e-mail exclusivamente para responder à sua mensagem.',
        'subject' => 'Assunto',
        'subject_placeholder' => 'Selecione o motivo do contacto',
        'message' => 'Mensagem',
        'message_placeholder' => 'Descreva a sua dúvida, sugestão ou relatório com o máximo de pormenores…',
        'characters' => '{count} / {max} carateres',
    ],

    'subjects' => [
        'general' => 'Dúvida geral',
        'donation' => 'Problema com doação / pagamento',
        'feedback' => 'Sugestão ou melhoria',
        'bug' => 'Reportar erro',
        'other' => 'Outro assunto',
    ],

    'submit' => 'Enviar mensagem',
    'submitting' => 'A enviar…',

    'success_title' => 'Mensagem enviada com sucesso!',
    'success_body' => 'Recebemos a sua mensagem e responderemos com a maior brevidade possível para o e-mail indicado.',
    'send_another' => 'Enviar outra mensagem',

    'sent_toast' => 'A sua mensagem foi enviada com sucesso!',
    'throttle' => 'Demasiadas mensagens enviadas em pouco tempo. Por favor, aguarde :seconds segundos antes de tentar novamente.',
];
