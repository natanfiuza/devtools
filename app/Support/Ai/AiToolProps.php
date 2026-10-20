<?php

namespace App\Support\Ai;

use App\Enums\SchemaSource;
use App\Enums\SqlDialect;
use App\Models\OutputTarget;
use App\Models\Plan;
use App\Models\User;
use App\Support\Quota;

/**
 * O que a pagina do AI SQL Builder precisa saber antes de o cliente montar o
 * formulario: se a tool esta ligada, se a conta pode usar, quanto resta da cota
 * e quais alvos e limites valem.
 *
 * A tela economiza idas ao servidor para os estados que nao dependem de uma
 * acao - visitante, e-mail nao verificado, conta suspensa, cota esgotada e
 * provider desligado. O POST continua validando tudo: isto e conveniencia de
 * interface, nao autorizacao (a autorizacao mora nas rotas e na acao).
 *
 * `targets` traz `label` e `language` do catalogo; o texto exibido vem de
 * `ai.targets.<slug>` no i18n. O label do banco e identificacao do dado, nao
 * texto de interface (CLAUDE.md: nenhuma string visivel fora do i18n).
 */
final class AiToolProps
{
    /**
     * @return array<string, mixed>
     */
    public static function for(?User $user): array
    {
        $available = AiConfiguration::isAvailable();
        $quota = $user === null ? null : Quota::for($user);
        $plan = $quota->plan ?? Plan::free();
        $reason = self::blockedReason($user, $quota, $available);

        return [
            'available' => $available,
            'canUse' => [
                'allowed' => $reason === null,
                'reason' => $reason,
            ],
            'quota' => $quota?->toArray(),
            'targets' => self::targets(),
            'sources' => SchemaSource::values(),
            'dialects' => SqlDialect::values(),
            'limits' => [
                // O plano pode ser mais apertado que o teto tecnico; nunca mais
                // folgado. Visitante ve os limites do Free, que e o piso de
                // qualquer conta.
                'schema' => max(0, min(
                    (int) config('ai.limits.max_schema_bytes'),
                    $plan->max_schema_bytes,
                )),
                'description' => max(0, min(
                    (int) config('ai.limits.max_description_chars'),
                    $plan->max_prompt_chars,
                )),
            ],
            'poll' => [
                'initialMs' => max(0, (int) config('ai.polling.initial_delay_ms')),
                'maxMs' => max(0, (int) config('ai.polling.max_delay_ms')),
                'timeoutMs' => max(0, (int) config('ai.polling.timeout_ms')),
            ],
            'endpoints' => [
                'store' => route('api.ai.queries.store', absolute: false),
                // O cliente troca o marcador pelo ULID devolvido no POST.
                'show' => route('api.ai.queries.show', ['aiRequest' => '__ID__'], absolute: false),
            ],
        ];
    }

    /**
     * O primeiro motivo que impede o uso, na ordem em que a tela deve explica-lo.
     * A ordem e deliberada: sem sessao nenhuma, o proximo passo e entrar; conta
     * sem verificacao e conta suspensa tem saidas diferentes; provider desligado
     * nao depende da pessoa; cota so importa quando todo o resto esta em ordem.
     */
    private static function blockedReason(?User $user, ?Quota $quota, bool $available): ?string
    {
        if ($user === null) {
            return 'guest';
        }

        if (! $user->hasVerifiedEmail()) {
            return 'email_unverified';
        }

        if ($user->isSuspended()) {
            return 'account_suspended';
        }

        if (! $available) {
            return 'unavailable';
        }

        if ($quota?->exhausted()) {
            return 'quota_exhausted';
        }

        return null;
    }

    /**
     * Catalogo visivel, na ordem de `sort`. Um alvo desligado no admin some da
     * tela do mesmo jeito que some do POST.
     *
     * @return list<array{slug: string, label: string, language: string, dialects: list<string>}>
     */
    private static function targets(): array
    {
        $targets = OutputTarget::query()
            ->enabled()
            ->orderBy('sort')
            ->orderBy('slug')
            ->get(['slug', 'label', 'language', 'supported_dialects'])
            ->map(static fn (OutputTarget $target): array => [
                'slug' => $target->slug,
                'label' => $target->label,
                'language' => $target->language,
                'dialects' => $target->supported_dialects,
            ])
            ->all();

        return array_values($targets);
    }
}
