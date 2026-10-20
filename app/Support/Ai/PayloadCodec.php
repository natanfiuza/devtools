<?php

namespace App\Support\Ai;

use App\Exceptions\Ai\PayloadCodecException;
use JsonException;

/**
 * Guarda e le o payload canonico comprimido (`canonical_payload`).
 *
 * Compressao aqui e reducao de tamanho, nao protecao: o payload so fica no
 * banco enquanto a solicitacao esta em voo e e apagado na liquidacao. O limite
 * de descompressao evita que uma linha adulterada vire um zip bomb.
 */
final class PayloadCodec
{
    private const LEVEL = 6;

    private const MAX_DECODED_BYTES = 1_048_576;

    public function encode(CanonicalPayload $payload): string
    {
        $compressed = gzcompress($payload->json(), self::LEVEL);

        if ($compressed === false) {
            throw PayloadCodecException::encodeFailed();
        }

        return base64_encode($compressed);
    }

    public function decode(string $encoded): CanonicalPayload
    {
        $compressed = base64_decode($encoded, true);

        if ($compressed === false || $compressed === '') {
            throw PayloadCodecException::decodeFailed();
        }

        // O `@` cobre o warning de fluxo zlib corrompido; o retorno false ja e
        // tratado como payload invalido.
        $json = @gzuncompress($compressed, self::MAX_DECODED_BYTES);

        if ($json === false) {
            throw PayloadCodecException::decodeFailed();
        }

        try {
            $data = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw PayloadCodecException::decodeFailed();
        }

        if (! is_array($data)) {
            throw PayloadCodecException::invalidShape();
        }

        return CanonicalPayload::fromArray($data);
    }
}
