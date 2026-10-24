<?php

/*
| Mensagens e textos da pagina publica de contato (Fale conosco).
*/

return [
    'meta' => [
        'title' => 'Contato',
        'description' => 'Fale com a equipe do devtools. Envie suas dúvidas, sugestões, reporte bugs ou fale sobre doações.',
    ],

    'title' => 'Fale com a gente',
    'subtitle' => 'Tem alguma dúvida, sugestão ou encontrou um problema? Mande uma mensagem para a nossa equipe.',

    'fields' => [
        'name' => 'Nome',
        'name_placeholder' => 'Seu nome completo ou como prefere ser chamado',
        'email' => 'E-mail',
        'email_placeholder' => "seu{'@'}email.com",
        'email_hint' => 'Usaremos este e-mail exclusivamente para responder à sua mensagem.',
        'subject' => 'Assunto',
        'subject_placeholder' => 'Selecione o motivo do contato',
        'message' => 'Mensagem',
        'message_placeholder' => 'Descreva sua dúvida, sugestão ou relato com o máximo de detalhes…',
        'characters' => '{count} / {max} caracteres',
    ],

    'subjects' => [
        'general' => 'Dúvida geral',
        'donation' => 'Problema com doação / pagamento',
        'feedback' => 'Sugestão ou melhoria',
        'bug' => 'Reportar bug',
        'other' => 'Outro assunto',
    ],

    'submit' => 'Enviar mensagem',
    'submitting' => 'Enviando…',

    'success_title' => 'Mensagem enviada com sucesso!',
    'success_body' => 'Recebemos sua mensagem e responderemos o mais breve possível no e-mail informado.',
    'send_another' => 'Enviar outra mensagem',

    'sent_toast' => 'Sua mensagem foi enviada com sucesso!',
    'throttle' => 'Muitas mensagens enviadas em pouco tempo. Por favor, aguarde :seconds segundos antes de tentar novamente.',
];
