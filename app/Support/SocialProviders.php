<?php

namespace App\Support;

use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Contracts\User as SocialiteUser;

/**
 * Provedores de login social suportados (docs/SPEC.md secao 7).
 */
final class SocialProviders
{
    /**
     * @var list<string>
     */
    private const ALL = ['google', 'github'];

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return self::ALL;
    }

    public static function isSupported(string $provider): bool
    {
        return in_array($provider, self::ALL, true);
    }

    /**
     * O provedor so funciona com as duas chaves no .env. Ate la o botao
     * continua na tela (decisao do grill) e o clique volta com aviso, em vez
     * de estourar um erro.
     */
    public static function isConfigured(string $provider): bool
    {
        if (! self::isSupported($provider)) {
            return false;
        }

        $id = config('services.'.$provider.'.client_id');
        $secret = config('services.'.$provider.'.client_secret');

        return is_string($id) && $id !== ''
            && is_string($secret) && $secret !== '';
    }

    /**
     * Nome do provedor para exibir nas mensagens ("Google", "GitHub").
     */
    public static function label(string $provider): string
    {
        $label = trans('auth.social.providers.'.$provider);

        return is_string($label) ? $label : $provider;
    }

    /**
     * O provedor confirma que o e-mail e do usuario?
     *
     * O Google diz isso em email_verified. O GitHub so devolve o e-mail
     * primario verificado (escopo user:email) - e conta no GitHub so existe
     * com e-mail confirmado.
     */
    public static function emailIsVerified(string $provider, SocialiteUser $user): bool
    {
        // email_verified so existe no payload cru, e o payload cru so esta no
        // AbstractUser (o que o Google e o GitHub devolvem). Driver que devolva
        // outro User nao confirma nada - e "nao confirma" e o padrao seguro.
        $raw = $user instanceof AbstractUser ? $user->getRaw() : [];

        return match ($provider) {
            'google' => (bool) ($raw['email_verified'] ?? false),
            'github' => $user->getEmail() !== null,
            default => false,
        };
    }
}
