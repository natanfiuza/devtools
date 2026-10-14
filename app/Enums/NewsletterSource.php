<?php

namespace App\Enums;

/**
 * De onde veio a inscricao na newsletter (atribuicao de primeiro toque).
 */
enum NewsletterSource: string
{
    case Footer = 'footer';
    case Register = 'register';
}
