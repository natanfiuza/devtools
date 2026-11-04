<?php

namespace App\Support\BadgeForge\Endpoint;

use Illuminate\Validation\ValidationException;

/**
 * Traduz as recusas do endpoint para codigos de validacao (F5, ADR-084).
 *
 * A mensagem visivel vem do i18n; o motivo tecnico nunca vai para a tela nem
 * para o log. A URL de origem tambem nao entra na mensagem.
 */
final class EndpointGuard
{
    /**
     * @throws ValidationException
     */
    public function fail(EndpointUrlException|EndpointFetchException $exception): never
    {
        $message = $exception instanceof EndpointUrlException
            ? $this->urlMessage($exception->reason)
            : 'validation.badge_endpoint.invalid_payload';

        throw ValidationException::withMessages([
            'url' => __($message),
        ]);
    }

    private function urlMessage(EndpointUrlReason $reason): string
    {
        return match ($reason) {
            EndpointUrlReason::Length => 'validation.badge_endpoint.url_length',
            EndpointUrlReason::Chars => 'validation.badge_endpoint.url_chars',
            EndpointUrlReason::Scheme => 'validation.badge_endpoint.scheme',
            EndpointUrlReason::UserInfo => 'validation.badge_endpoint.userinfo',
            EndpointUrlReason::Host => 'validation.badge_endpoint.host',
            EndpointUrlReason::Port => 'validation.badge_endpoint.port',
            EndpointUrlReason::Unresolvable => 'validation.badge_endpoint.unresolvable',
            EndpointUrlReason::UnsafeIp => 'validation.badge_endpoint.unsafe_ip',
            EndpointUrlReason::Redirect => 'validation.badge_endpoint.redirect',
        };
    }
}
