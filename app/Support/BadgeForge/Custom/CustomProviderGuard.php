<?php

namespace App\Support\BadgeForge\Custom;

use App\Support\BadgeForge\Endpoint\EndpointUrlException;
use App\Support\BadgeForge\Endpoint\EndpointUrlReason;
use Illuminate\Validation\ValidationException;

/**
 * Converte recusas de provider customizado em erros de validacao (F7,
 * ADR-087).
 *
 * A mensagem nunca contem a URL/template: so o motivo tecnico vira chave i18n
 * em `validation.badge_custom_provider.*`.
 */
final class CustomProviderGuard
{
    public function failUrl(EndpointUrlException $exception, string $field = 'url_template'): never
    {
        throw ValidationException::withMessages([
            $field => __(self::urlMessage($exception->reason)),
        ]);
    }

    public function fail(string $field, string $key): never
    {
        throw ValidationException::withMessages([
            $field => __('validation.badge_custom_provider.'.$key),
        ]);
    }

    public static function urlMessage(EndpointUrlReason $reason): string
    {
        return match ($reason) {
            EndpointUrlReason::Length => 'validation.badge_custom_provider.url_length',
            EndpointUrlReason::Chars => 'validation.badge_custom_provider.url_chars',
            EndpointUrlReason::Scheme => 'validation.badge_custom_provider.scheme',
            EndpointUrlReason::UserInfo => 'validation.badge_custom_provider.userinfo',
            EndpointUrlReason::Host => 'validation.badge_custom_provider.host',
            EndpointUrlReason::Port => 'validation.badge_custom_provider.port',
            EndpointUrlReason::Unresolvable => 'validation.badge_custom_provider.unresolvable',
            EndpointUrlReason::UnsafeIp => 'validation.badge_custom_provider.unsafe_ip',
            EndpointUrlReason::Redirect => 'validation.badge_custom_provider.redirect',
        };
    }
}
