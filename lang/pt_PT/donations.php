<?php

/*
| Doação por Pix (F6). Namespace do frontend: as strings da página pública e
| da página de estado. O e-mail de recibo fica em mail.php (só servidor).
*/

return [
    'title' => 'Faça uma doação',
    'subtitle' => 'O site é gratuito e continua assim. Se ele te ajuda no dia a dia, uma doação de qualquer valor mantém as ferramentas online.',
    'meta' => [
        'title' => 'Doar para o devtools',
        'description' => 'Apoie o devtools com uma doação por Pix. Qualquer valor ajuda a manter as ferramentas gratuitas e online.',
    ],

    'amount' => [
        'label' => 'Valor da doação',
        'suggested' => 'Valores sugeridos',
        'custom' => 'Outro valor',
        'custom_hint' => 'Entre {min} e {max}.',
        'invalid' => 'Escolha um valor entre {min} e {max}.',
    ],

    'donor' => [
        'title' => 'Quem está a doar',
        'name' => 'Nome (opcional)',
        'name_placeholder' => 'Como quer aparecer',
        'email' => 'E-mail para o recibo (opcional)',
        'email_hint' => 'Só usamos o e-mail para enviar o recibo desta doação.',
        'anonymous' => 'Doar anonimamente',
        'anonymous_hint' => 'Sem nome. O e-mail, se indicado, serve apenas para o recibo.',
    ],

    'submit' => 'Gerar Pix',
    'processing' => 'A gerar o Pix…',

    'info' => [
        'title' => 'Doação indisponível de momento',
        'description_lead' => 'O Pix ainda não está configurado por aqui. Volte mais tarde ou fale connosco pela',
        'contact_link' => 'página de contacto',
        'description' => 'O Pix ainda não está configurado por aqui. Volte mais tarde ou fale connosco pela página de contacto.',
    ],

    'pix' => [
        'title' => 'Pague com Pix',
        'description' => 'Abra a app do seu banco, escolha Pix e leia o QR ou cole o código abaixo.',
        'copy' => 'Copiar código Pix',
        'copied' => 'Código copiado.',
        'copy_failed' => 'Não foi possível copiar. Selecione o código abaixo.',
        'ticket' => 'Abrir na app do banco',
        'dynamic_note' => 'A confirmação chega automaticamente assim que o pagamento for aprovado.',
        'static_note' => 'Este é um Pix estático: não há confirmação automática. Guarde o comprovativo.',
    ],

    'status' => [
        'title' => 'A sua doação',
        'amount' => 'Valor',
        'created_at' => 'Criada em',
        'expires_at' => 'Válida até',
        'pending' => 'A aguardar pagamento',
        'paid' => 'Doação confirmada',
        'expired' => 'Pix expirado',
        'refunded' => 'Doação devolvida',
        'failed' => 'Doação não concluída',
        'pending_hint' => 'Assim que o pagamento for aprovado, esta página mostra a confirmação.',
        'paid_hint' => 'Obrigado por apoiar o projeto! A doação não dá acesso a planos nem consome cota: é um apoio ao site.',
        'expired_hint' => 'O prazo do QR passou e nada foi cobrado. Pode gerar uma nova doação quando quiser.',
        'refunded_hint' => 'O valor desta doação foi devolvido.',
        'failed_hint' => 'O pagamento não foi concluído. Pode tentar de novo quando quiser.',
        'new' => 'Fazer outra doação',
        'receipt' => 'Se indicou um e-mail, o recibo chega à caixa de entrada.',
    ],

    // Motivo mostrado ao doador quando a doação falha (ADR-059). A chave vem do
    // `status_detail`; o código cru do fornecedor nunca sai do servidor.
    'reasons' => [
        'high_risk' => 'O Mercado Pago recusou o Pix na análise de risco. Tente de novo ou use outro meio de pagamento.',
        'bank' => 'O banco recusou o Pix. Verifique limite, saldo ou bloqueios e tente de novo.',
        'regulations' => 'Uma regra regulatória impediu o Pix. Tente de novo mais tarde ou use outra conta.',
        'insufficient_data' => 'Faltaram dados para o Mercado Pago concluir o Pix. Tente de novo com os seus dados completos.',
        'biz_rule' => 'Uma regra da conta que recebe a doação recusou o Pix. Tente de novo mais tarde.',
        'other' => 'O pagamento não foi concluído pelo fornecedor. Gere um novo Pix e tente de novo.',
    ],

    'check' => [
        'button' => 'Já paguei',
        'loading' => 'A consultar…',
        'hint' => 'Já pagou? Toque para confirmar agora.',
        'success' => 'Pagamento confirmado. Obrigado!',
        'pending' => 'O Mercado Pago ainda não confirmou este Pix. Aguarde um instante e tente de novo.',
        'failed' => 'O pagamento foi recusado. Veja o motivo nesta página.',
        'refunded' => 'O pagamento aparece como devolvido.',
        'already' => 'Esta doação já foi resolvida.',
        'unavailable' => 'Não há nada para consultar agora nesta doação.',
        'mismatch' => 'O pagamento consultado não pertence a esta doação.',
        'error' => 'Não foi possível consultar o pagamento agora. Tente de novo dentro de instantes.',
        'throttled' => 'Demasiadas consultas em pouco tempo. Aguarde um minuto e tente de novo.',
    ],

    'errors' => [
        'unavailable' => 'A doação por Pix não está disponível de momento.',
        'gateway' => 'Não foi possível gerar o Pix agora. Tente de novo dentro de instantes.',
        'payer' => 'Não foi possível gerar o Pix sem um e-mail de contacto. Tente de novo indicando o seu e-mail.',
    ],

    'pix_description' => 'Doação para :app',
];
