<?php

/*
| Doacao por Pix (F6). Namespace do frontend: as strings da pagina publica e
| da pagina de status. O e-mail de recibo fica em mail.php (so servidor).
*/

return [
    'title' => 'Faça uma doação',
    'subtitle' => 'O site é gratuito e continua assim. Se ele te ajuda no dia a dia, uma doação de qualquer valor mantém as ferramentas no ar.',
    'meta' => [
        'title' => 'Doar para o devtools',
        'description' => 'Apoie o devtools com uma doação por Pix. Qualquer valor ajuda a manter as ferramentas gratuitas e no ar.',
    ],

    'amount' => [
        'label' => 'Valor da doação',
        'suggested' => 'Valores sugeridos',
        'custom' => 'Outro valor',
        'custom_hint' => 'Entre {min} e {max}.',
        'invalid' => 'Escolha um valor entre {min} e {max}.',
    ],

    'donor' => [
        'title' => 'Quem está doando',
        'name' => 'Nome (opcional)',
        'name_placeholder' => 'Como você quer aparecer',
        'email' => 'E-mail para o recibo (opcional)',
        'email_hint' => 'Só usamos o e-mail para enviar o recibo desta doação.',
        'anonymous' => 'Doar anonimamente',
        'anonymous_hint' => 'Sem nome. O e-mail, se informado, serve apenas para o recibo.',
    ],

    'submit' => 'Gerar Pix',
    'processing' => 'Gerando o Pix…',

    'info' => [
        'title' => 'Doação indisponível no momento',
        'description_lead' => 'O Pix ainda não está configurado por aqui. Volte mais tarde ou fale com a gente pela',
        'contact_link' => 'página de contato',
        'description' => 'O Pix ainda não está configurado por aqui. Volte mais tarde ou fale com a gente pela página de contato.',
    ],

    'pix' => [
        'title' => 'Pague com Pix',
        'description' => 'Abra o app do seu banco, escolha Pix e escaneie o QR ou cole o código abaixo.',
        'copy' => 'Copiar código Pix',
        'copied' => 'Código copiado.',
        'copy_failed' => 'Não foi possível copiar. Selecione o código abaixo.',
        'ticket' => 'Abrir no app do banco',
        'dynamic_note' => 'A confirmação chega automaticamente assim que o pagamento for aprovado.',
        'static_note' => 'Este é um Pix estático: não há confirmação automática. Guarde o comprovante.',
    ],

    'status' => [
        'title' => 'Sua doação',
        'amount' => 'Valor',
        'created_at' => 'Criada em',
        'expires_at' => 'Válida até',
        'pending' => 'Aguardando pagamento',
        'paid' => 'Doação confirmada',
        'expired' => 'Pix expirado',
        'refunded' => 'Doação devolvida',
        'failed' => 'Doação não concluída',
        'pending_hint' => 'Assim que o pagamento for aprovado, esta página mostra a confirmação.',
        'paid_hint' => 'Obrigado por apoiar o projeto! Doação não dá acesso a planos nem consome cota: é um apoio ao site.',
        'expired_hint' => 'O prazo do QR passou e nada foi cobrado. Você pode gerar uma nova doação quando quiser.',
        'refunded_hint' => 'O valor desta doação foi devolvido.',
        'failed_hint' => 'O pagamento não foi concluído. Você pode tentar de novo quando quiser.',
        'new' => 'Fazer outra doação',
        'receipt' => 'Se você informou um e-mail, o recibo chega na caixa de entrada.',
    ],

    // Motivo exibido ao doador quando a doacao falha (ADR-059). A chave vem do
    // `status_detail`; o codigo cru do provedor nunca sai do servidor.
    'reasons' => [
        'high_risk' => 'O Mercado Pago recusou o Pix na análise de risco. Tente de novo ou use outro meio de pagamento.',
        'bank' => 'O banco recusou o Pix. Verifique limite, saldo ou bloqueios e tente de novo.',
        'regulations' => 'Uma regra regulatória impediu o Pix. Tente de novo mais tarde ou use outra conta.',
        'insufficient_data' => 'Faltaram dados para o Mercado Pago concluir o Pix. Tente de novo com seus dados completos.',
        'biz_rule' => 'Uma regra da conta que recebe a doação recusou o Pix. Tente de novo mais tarde.',
        'other' => 'O pagamento não foi concluído pelo provedor. Gere um novo Pix e tente de novo.',
    ],

    'check' => [
        'button' => 'Já paguei',
        'loading' => 'Consultando…',
        'hint' => 'Já pagou? Toque para confirmar agora.',
        'success' => 'Pagamento confirmado. Obrigado!',
        'pending' => 'O Mercado Pago ainda não confirmou este Pix. Aguarde um instante e tente de novo.',
        'failed' => 'O pagamento foi recusado. Veja o motivo nesta página.',
        'refunded' => 'O pagamento aparece como devolvido.',
        'already' => 'Esta doação já foi resolvida.',
        'unavailable' => 'Não há o que consultar agora para esta doação.',
        'mismatch' => 'O pagamento consultado não pertence a esta doação.',
        'error' => 'Não foi possível consultar o pagamento agora. Tente de novo em instantes.',
        'throttled' => 'Muitas consultas em pouco tempo. Aguarde um minuto e tente de novo.',
    ],

    'errors' => [
        'unavailable' => 'A doação por Pix não está disponível no momento.',
        'gateway' => 'Não foi possível gerar o Pix agora. Tente de novo em instantes.',
        'payer' => 'Não foi possível gerar o Pix sem um e-mail de contato. Tente de novo informando seu e-mail.',
    ],

    'pix_description' => 'Doação para :app',
];
