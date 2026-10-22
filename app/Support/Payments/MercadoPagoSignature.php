<?php

namespace App\Support\Payments;

/**
 * Assinatura `x-signature` das notificacoes da Mercado Pago (F6).
 *
 * O manifesto e exatamente o que a documentacao define:
 * `id:<data.id em minusculas>;request-id:<x-request-id>;ts:<ts>;`
 *
 * A comparacao e em tempo constante (`hash_equals`) e o segredo nunca aparece
 * em log ou excecao. Sem janela de timestamp: replay e contido pela chave
 * unica do evento e pela idempotencia das acoes, enquanto retries legitimos do
 * provedor continuam aceitos.
 */
final class MercadoPagoSignature
{
    public static function isValid(
        string $secret,
        string $signature,
        string $requestId,
        string $dataId,
    ): bool {
        $secret = trim($secret);
        $signature = trim($signature);
        $requestId = trim($requestId);
        $dataId = trim($dataId);

        if ($secret === '' || $signature === '' || $requestId === '' || $dataId === '') {
            return false;
        }

        $parts = self::parts($signature);
        $timestamp = $parts['ts'] ?? null;
        $hash = $parts['v1'] ?? null;

        if ($timestamp === null || $hash === null) {
            return false;
        }

        $manifest = 'id:'.mb_strtolower($dataId).';request-id:'.$requestId.';ts:'.$timestamp.';';
        $expected = hash_hmac('sha256', $manifest, $secret);

        return hash_equals($expected, mb_strtolower($hash));
    }

    /**
     * @return array<string, string>
     */
    private static function parts(string $signature): array
    {
        $parts = [];

        foreach (explode(',', $signature) as $part) {
            $pair = explode('=', trim($part), 2);

            if (count($pair) !== 2 || $pair[0] === '' || $pair[1] === '') {
                continue;
            }

            $parts[$pair[0]] = $pair[1];
        }

        return $parts;
    }
}
