<?php

namespace App\Enums;

/**
 * O que a pessoa escolheu em `/subscribe`.
 *
 * `one_time` abre o Checkout Pro e libera um periodo quando o pagamento e
 * aprovado. `recurring` cria uma preapproval no cartao: a autorizacao nao
 * libera nada, e o primeiro `subscription_authorized_payment` aprovado abre o
 * periodo - por isso a tela fala em processamento.
 */
enum CheckoutMode: string
{
    case OneTime = 'one_time';
    case Recurring = 'recurring';
}
