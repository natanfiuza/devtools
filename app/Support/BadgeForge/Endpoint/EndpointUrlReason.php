<?php

namespace App\Support\BadgeForge\Endpoint;

/**
 * Motivos de recusa de uma URL de endpoint (F5).
 *
 * O valor e a chave de traducao usada em `validation.badge_endpoint.*` e
 * tambem o codigo seguro para log - nunca o texto da URL.
 */
enum EndpointUrlReason: string
{
    case Length = 'length';
    case Chars = 'chars';
    case Scheme = 'scheme';
    case UserInfo = 'userinfo';
    case Host = 'host';
    case Port = 'port';
    case Unresolvable = 'unresolvable';
    case UnsafeIp = 'unsafe_ip';
    case Redirect = 'redirect';
}
