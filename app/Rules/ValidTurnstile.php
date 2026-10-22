<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\InvokableValidationRule;

/**
 * Verificacao do Cloudflare Turnstile no cadastro (ADR-052).
 *
 * A regra e implicita para valer tambem quando o campo nao vem: sem token nao
 * ha como passar. Quando `TURNSTILE_SECRET_KEY` esta vazio, o Turnstile esta
 * explicitamente desligado e a regra passa - o widget tambem nao aparece.
 *
 * Falha fechada: token invalido, resposta inesperada ou servico fora do ar
 * recusam o cadastro com a mesma mensagem generica, sem revelar o motivo.
 */
final class ValidTurnstile implements ValidationRule
{
    /**
     * Roda mesmo quando o campo esta ausente do payload (Laravel 11+).
     *
     * @see InvokableValidationRule::make()
     */
    public bool $implicit = true;

    public static function enabled(): bool
    {
        return is_string(config('services.turnstile.secret_key'))
            && config('services.turnstile.secret_key') !== '';
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::enabled()) {
            return;
        }

        if (! is_string($value) || trim($value) === '') {
            $fail(__('auth.turnstile.failed'));

            return;
        }

        try {
            $response = Http::asForm()
                ->timeout((int) config('services.turnstile.timeout', 5))
                ->post((string) config('services.turnstile.verify_url'), [
                    'secret' => config('services.turnstile.secret_key'),
                    'response' => $value,
                    'remoteip' => request()->ip(),
                ]);
        } catch (ConnectionException) {
            $fail(__('auth.turnstile.failed'));

            return;
        }

        if (! $response->successful() || $response->json('success') !== true) {
            $fail(__('auth.turnstile.failed'));
        }
    }
}
