<?php

use App\Support\Payments\PixPayload;
use Tests\TestCase;

uses(TestCase::class);

/*
| BR Code do Pix estatico (F6).
|
| O payload e EMV: cada campo tem id, tamanho em dois digitos e valor, e o
| CRC16-CCITT fecha o codigo. O vetor "123456789" -> 29B1 e o classico de
| qualquer CRC-16/CCITT-FALSE: se a conta mudar, o app do banco recusa o QR.
*/

it('calcula o crc16-ccitt do vetor de referencia', function () {
    expect(PixPayload::crc16('123456789'))->toBe('29B1');
});

it('monta o br code com os campos obrigatorios e o crc no fim', function () {
    $payload = PixPayload::make(
        key: 'chave@exemplo.com',
        holder: 'Fulano de Tal',
        city: 'Recife',
        amountCents: 2550,
        txid: 'DON123',
    );

    expect($payload)
        ->toStartWith('000201')
        ->toContain('br.gov.bcb.pix')
        ->toContain('chave@exemplo.com')
        // Campo 54: valor com ponto decimal, nunca virgula.
        ->toContain('540525.50')
        ->toContain('5303986')
        ->toContain('5802BR')
        ->toContain('5913FULANO DE TAL')
        ->toContain('6006RECIFE')
        ->toContain('62100506DON123')
        ->toMatch('/6304[0-9A-F]{4}$/');
});

it('fecha o payload com o crc do proprio conteudo', function () {
    $payload = PixPayload::make('chave', 'Fulano', 'Recife', 1000, 'DON1');

    // O CRC cobre tudo ate o identificador 6304, exclusive.
    expect(PixPayload::crc16(substr($payload, 0, -4)))->toBe(substr($payload, -4));
});

it('limpa e encurta o nome e a cidade do recebedor', function () {
    $payload = PixPayload::make('chave', 'João D Ávila', 'São Paulo', 1000, 'DON2');

    expect($payload)
        ->toContain('5912JOAO D AVILA')
        ->toContain('6009SAO PAULO');
});

it('aceita so letras e numeros no txid', function () {
    $payload = PixPayload::make('chave', 'Fulano', 'Recife', 1000, 'abc-123/XYZ!');

    expect($payload)->toContain('62130509abc123XYZ');
});

it('usa o marcador curinga quando o txid fica vazio', function () {
    $payload = PixPayload::make('chave', 'Fulano', 'Recife', 1000, '---');

    expect($payload)->toContain('62070503***');
});

it('exige chave, titular e cidade para o pix estatico existir', function () {
    config(['donations.static_pix' => ['key' => null, 'holder' => 'Fulano', 'city' => 'Recife']]);
    expect(PixPayload::isConfigured())->toBeFalse();

    config(['donations.static_pix' => ['key' => 'chave', 'holder' => '   ', 'city' => 'Recife']]);
    expect(PixPayload::isConfigured())->toBeFalse();

    config(['donations.static_pix' => ['key' => 'chave', 'holder' => 'Fulano', 'city' => 'Recife']]);
    expect(PixPayload::isConfigured())->toBeTrue();
});
