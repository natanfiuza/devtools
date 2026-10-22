<?php

namespace App\Support\Payments;

use Illuminate\Support\Str;

/**
 * Payload do Pix estatico no formato EMV (Banco Central, F6).
 *
 * O fallback sem credenciais do provedor nao tem API para gerar o QR: o codigo
 * copia-e-cola e montado aqui, com os campos que a especificacao do BR Code
 * exige e o CRC16-CCITT no fim. Chave, titular e cidade vem de
 * `config/donations.php` - nada fica no codigo.
 *
 * O valor e fixo na doacao e o `txid` e o ULID dela: cada QR e de uma doacao
 * so, mesmo sendo estatico.
 */
final class PixPayload
{
    /** Tamanho maximo do nome do recebedor (campo 59). */
    private const MAX_HOLDER = 25;

    /** Tamanho maximo da cidade do recebedor (campo 60). */
    private const MAX_CITY = 15;

    /** Tamanho maximo do identificador da transacao (campo 62-05). */
    private const MAX_TXID = 25;

    /**
     * Ha chave, titular e cidade configurados? Sem os tres, a tela de doacao
     * fica em modo informativo em vez de mostrar um QR invalido.
     */
    public static function isConfigured(): bool
    {
        foreach (['key', 'holder', 'city'] as $field) {
            $value = config('donations.static_pix.'.$field);

            if (! is_string($value) || trim($value) === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Monta o BR Code completo, com o CRC calculado.
     */
    public static function make(
        string $key,
        string $holder,
        string $city,
        int $amountCents,
        string $txid,
    ): string {
        $payload = self::field('00', '01')
            // Uso unico: o QR vale para a doacao que o gerou, nao e reutilizavel.
            .self::field('01', '12')
            .self::field('26', self::field('00', 'br.gov.bcb.pix').self::field('01', $key))
            .self::field('52', '0000')
            .self::field('53', '986')
            .self::field('54', self::amount($amountCents))
            .self::field('58', 'BR')
            .self::field('59', self::sanitize($holder, self::MAX_HOLDER))
            .self::field('60', self::sanitize($city, self::MAX_CITY))
            .self::field('62', self::field('05', self::txid($txid)))
            // O CRC cobre o payload inteiro, inclusive o proprio "6304".
            .'6304';

        return $payload.self::crc16($payload);
    }

    /**
     * CRC16-CCITT (polinomio 0x1021, valor inicial 0xFFFF), em hexadecimal.
     *
     * E o mesmo calculo do campo 63 do BR Code e o valor de conferencia para
     * "123456789" e 0x29B1.
     */
    public static function crc16(string $payload): string
    {
        $crc = 0xFFFF;

        foreach (str_split($payload) as $character) {
            $crc ^= ord($character) << 8;

            for ($bit = 0; $bit < 8; $bit++) {
                $crc = ($crc & 0x8000) !== 0
                    ? (($crc << 1) ^ 0x1021) & 0xFFFF
                    : ($crc << 1) & 0xFFFF;
            }
        }

        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }

    /**
     * Um campo EMV: identificador, tamanho em dois digitos e valor.
     */
    private static function field(string $id, string $value): string
    {
        return $id.str_pad((string) strlen($value), 2, '0', STR_PAD_LEFT).$value;
    }

    /**
     * Valor com ponto e sempre dois decimais (o campo 54 nao aceita virgula).
     */
    private static function amount(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    /**
     * Nome e cidade sem acento e em caixa alta, como o padrao pede.
     */
    private static function sanitize(string $value, int $max): string
    {
        $clean = Str::upper(preg_replace('/[^A-Za-z0-9 ]/', '', Str::ascii(trim($value))) ?? '');

        return substr(trim($clean), 0, $max);
    }

    /**
     * Identificador da transacao: alfanumerico, ate 25 caracteres.
     */
    private static function txid(string $value): string
    {
        $clean = substr(preg_replace('/[^A-Za-z0-9]/', '', $value) ?? '', 0, self::MAX_TXID);

        return $clean === '' ? '***' : $clean;
    }
}
